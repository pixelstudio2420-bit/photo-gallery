<?php

namespace App\Services;

use App\Models\AppSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GoogleCalendarService — fetches Thai holiday calendar events from
 * Google's public "Thailand Holidays" calendar.
 *
 * Why this exists
 * ───────────────
 * Our internal lunar table covers Loy Krathong + Chinese NY but
 * NOTHING for Buddhist holy days (Magha/Visakha/Asanha Puja, Khao/
 * Ok Phansa). Those are also lunar but Google maintains them
 * authoritatively per the Thai government calendar. Plus Google
 * auto-handles royal-decree shifts to public holidays.
 *
 * Auth model
 * ──────────
 * Public read of an unlisted calendar — needs ONLY an API key, no
 * OAuth flow. Admin pastes the key into AppSetting (table
 * `app_settings.google_calendar_api_key`) once.
 *
 * Cost
 * ────
 * Google Calendar API free tier = 1M calls/day. We make ~12 calls/year
 * (monthly sync × 1 query). Effectively free.
 *
 * Failure modes
 * ─────────────
 * Network failure / invalid key / rate limit / parse error all return
 * an empty Collection. Caller (FestivalsSeeder) treats empty as "no
 * Google data, use internal table." User-visible behaviour is a graceful
 * degrade — the sync still completes, just with whatever data it could
 * gather.
 *
 * Caching
 * ───────
 * 24h TTL — holiday dates don't change within a day, so re-fetching
 * during the same admin session is wasteful.
 */
class GoogleCalendarService
{
    /**
     * The public Google calendar ID for Thai holidays (English-named
     * events with Thai date metadata). The %23 is URL-encoded #.
     *
     * Alternative localised calendar (Thai-named events):
     *   th.thai%23holiday%40group.v.calendar.google.com
     */
    private const CALENDAR_ID = 'en.th%23holiday%40group.v.calendar.google.com';

    private const ENDPOINT = 'https://www.googleapis.com/calendar/v3/calendars/{id}/events';

    /**
     * Fetch the Thai holiday events for a given calendar year.
     * Returns a Collection of holidays with start/end dates ready
     * for matching against our festival slugs.
     *
     * @return Collection<int, array{name: string, description: ?string, start_date: string, end_date: string}>
     */
    public function getThaiHolidays(int $year): Collection
    {
        if (!$this->isConfigured()) {
            return collect();
        }

        return Cache::remember(
            "google_calendar_th_holidays_{$year}",
            60 * 60 * 24,  // 24h
            fn () => $this->fetchYear($year)
        );
    }

    /**
     * Has admin set up the API key? Used by callers to decide whether
     * to even try Google before falling back to the internal table.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey());
    }

    /**
     * Test the configured API key by making a real (cheap) call.
     * Returns ['ok' => bool, 'message' => string, 'fix' => ?string]
     * for the admin UI. The `fix` field, when present, is actionable
     * Thai-language guidance for the most common errors.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'ยังไม่ได้ตั้งค่า API Key'];
        }

        try {
            $year = (int) now()->year;
            $items = $this->fetchYearRaw($year);   // raw to surface HTTP errors
            $count = $items->count();
            return [
                'ok'      => $count > 0,
                'message' => $count > 0
                    ? "✓ เชื่อมต่อสำเร็จ — พบ {$count} วันสำคัญในปฏิทินไทย ปี {$year}"
                    : 'เชื่อมต่อได้ แต่ไม่มีข้อมูล (ตรวจสอบ Calendar ID)',
                'count'   => $count,
            ];
        } catch (\Throwable $e) {
            return $this->humaniseError($e->getMessage());
        }
    }

    /**
     * Map raw Google API errors to actionable Thai-language messages
     * with specific fix instructions. Most common error: HTTP referrer
     * restriction blocking server-side calls (admin set "HTTP referrers"
     * as Application restriction in Cloud Console — that's for browser
     * code, not for our PHP server).
     */
    private function humaniseError(string $raw): array
    {
        // Trim & lowercase for matching
        $lower = strtolower($raw);

        // ── Referrer restriction (most common) ────────────────────
        if (str_contains($lower, 'requests from referer') && str_contains($lower, 'blocked')) {
            return [
                'ok'      => false,
                'message' => 'API Key ถูกตั้งให้รับเฉพาะคำขอจากเว็บ (HTTP referrers) — แต่ระบบเราเรียกจาก server',
                'fix'     => "วิธีแก้:\n"
                    . "1. ไปที่ Google Cloud Console → APIs & Services → Credentials\n"
                    . "2. คลิก API key ของคุณ\n"
                    . "3. ใต้ \"Application restrictions\" — เปลี่ยนเป็น **None** หรือ **IP addresses** (ใส่ IP ของ Laravel Cloud)\n"
                    . "4. บันทึก แล้วกดทดสอบอีกครั้ง\n\n"
                    . "หมายเหตุ: \"HTTP referrers\" ใช้ได้เฉพาะกับ JavaScript ในเบราว์เซอร์ ไม่ใช่ server-side PHP",
                'category'=> 'referrer_restriction',
            ];
        }

        // ── IP restriction blocking us ────────────────────────────
        if (str_contains($lower, 'requests from ip') && str_contains($lower, 'blocked')) {
            return [
                'ok'      => false,
                'message' => 'API Key ถูกจำกัดให้ใช้ได้เฉพาะ IP บางตัว — แต่ IP ของ server เราไม่อยู่ในรายชื่อ',
                'fix'     => "วิธีแก้:\n"
                    . "1. Cloud Console → Credentials → คลิก API key\n"
                    . "2. Application restrictions → IP addresses\n"
                    . "3. เพิ่ม IP ของ server (Laravel Cloud) เข้าไป\n"
                    . "   หรือเปลี่ยนเป็น \"None\" ถ้าไม่อยากจำกัด",
                'category'=> 'ip_restriction',
            ];
        }

        // ── API key invalid / expired ─────────────────────────────
        if (str_contains($lower, 'api key not valid') || str_contains($lower, 'invalid api key')) {
            return [
                'ok'      => false,
                'message' => 'API Key ไม่ถูกต้อง',
                'fix'     => "ตรวจสอบว่า:\n"
                    . "• Copy key ครบถ้วน (ไม่มี space หรือ newline เผลอติด)\n"
                    . "• Key ยังไม่ถูกลบใน Cloud Console",
                'category'=> 'invalid_key',
            ];
        }

        // ── API not enabled in this project ───────────────────────
        if (str_contains($lower, 'has not been used') && str_contains($lower, 'calendar')) {
            return [
                'ok'      => false,
                'message' => 'Google Calendar API ยังไม่ถูก enable ใน project นี้',
                'fix'     => "วิธีแก้:\n"
                    . "1. Cloud Console → APIs & Services → Library\n"
                    . "2. ค้นหา \"Google Calendar API\"\n"
                    . "3. กด Enable\n"
                    . "4. รออีก ~1 นาทีแล้วลองทดสอบใหม่",
                'category'=> 'api_not_enabled',
            ];
        }

        // ── Quota exceeded ────────────────────────────────────────
        if (str_contains($lower, 'quota') || str_contains($lower, 'rate')) {
            return [
                'ok'      => false,
                'message' => 'เกิน quota ของ Google Calendar API',
                'fix'     => "Free tier = 1,000,000 calls/day — แทบเป็นไปไม่ได้ที่จะถึง\n"
                    . "ถ้าเจอ: ตรวจสอบว่าไม่มี script รั่วเรียก API ซ้ำ ๆ\n"
                    . "หรือรอ 24 ชม. ให้ quota reset",
                'category'=> 'quota',
            ];
        }

        // ── Generic fallback ──────────────────────────────────────
        return [
            'ok'      => false,
            'message' => 'เชื่อมต่อไม่สำเร็จ',
            'fix'     => 'รายละเอียด: ' . \Illuminate\Support\Str::limit($raw, 300),
            'category'=> 'unknown',
        ];
    }

    /**
     * Like fetchYear() but throws on HTTP error so testConnection()
     * can humanise the response. fetchYear() swallows errors for the
     * normal sync path where graceful degrade is what we want.
     */
    private function fetchYearRaw(int $year): Collection
    {
        $url = str_replace('{id}', $this->calendarId(), self::ENDPOINT);

        $response = Http::timeout(10)
            ->retry(2, 200)
            ->get($url, [
                'key'           => $this->apiKey(),
                'timeMin'       => Carbon::create($year, 1, 1, 0, 0, 0)->toIso8601String(),
                'timeMax'       => Carbon::create($year, 12, 31, 23, 59, 59)->toIso8601String(),
                'singleEvents'  => 'true',
                'orderBy'       => 'startTime',
                'maxResults'    => 250,
            ]);

        if (!$response->successful()) {
            // Re-throw with the Google error message preserved so
            // humaniseError() can string-match against it.
            $errorMessage = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException($errorMessage);
        }

        return collect($response->json('items', []));
    }

    /**
     * Force-refresh the cache by re-fetching from Google. Used when
     * admin updates the API key or wants fresh data.
     */
    public function bustCache(int $year): void
    {
        Cache::forget("google_calendar_th_holidays_{$year}");
    }

    /* ────────────────── internals ────────────────── */

    private function apiKey(): string
    {
        return (string) (AppSetting::get('google_calendar_api_key', '') ?: env('GOOGLE_CALENDAR_API_KEY', ''));
    }

    /**
     * Calendar ID admin can override via AppSetting if they want a
     * different region (e.g. they prefer th.thai for Thai-named events).
     */
    private function calendarId(): string
    {
        $custom = (string) AppSetting::get('google_calendar_id', '');
        return $custom !== '' ? $custom : self::CALENDAR_ID;
    }

    /**
     * Make the actual Google API call for a year. Uses singleEvents
     * to expand recurring events into individual instances + sorts
     * by start time. Throws on HTTP errors so caller decides whether
     * to swallow or surface.
     */
    private function fetchYear(int $year): Collection
    {
        $url = str_replace('{id}', $this->calendarId(), self::ENDPOINT);

        $response = Http::timeout(10)
            ->retry(2, 200)
            ->get($url, [
                'key'           => $this->apiKey(),
                'timeMin'       => Carbon::create($year, 1, 1, 0, 0, 0)->toIso8601String(),
                'timeMax'       => Carbon::create($year, 12, 31, 23, 59, 59)->toIso8601String(),
                'singleEvents'  => 'true',
                'orderBy'       => 'startTime',
                'maxResults'    => 250,
            ]);

        if (!$response->successful()) {
            Log::warning('Google Calendar API failed', [
                'year'   => $year,
                'status' => $response->status(),
                'body'   => $response->json('error.message') ?? $response->body(),
            ]);
            return collect();
        }

        $items = collect($response->json('items', []));

        return $items
            ->map(function ($item) {
                // Google's all-day events have date in start.date /
                // end.date. Timed events use start.dateTime — we only
                // care about all-day for holidays.
                $start = $item['start']['date']     ?? null;
                $end   = $item['end']['date']       ?? null;

                if (!$start || !$end) return null;

                // Google's end_date is EXCLUSIVE (e.g. an event from
                // Apr 13 to Apr 15 has end.date = Apr 16). Subtract
                // a day so we get inclusive boundaries that match our
                // festival.starts_at/ends_at semantics.
                $inclusiveEnd = Carbon::parse($end)->subDay()->toDateString();

                return [
                    'name'         => trim((string) ($item['summary'] ?? '')),
                    'description'  => $item['description'] ?? null,
                    'start_date'   => $start,
                    'end_date'     => $inclusiveEnd,
                    // Useful for debugging / admin UI
                    '_google_id'   => $item['id'] ?? null,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Match a Google holiday to one of our canonical festival slugs.
     * Returns the matched holiday or null. Lookup table keyed by slug
     * → array of substring keywords (English first, Thai fallback).
     *
     * Adding a new festival to Google sync: extend MAPPING below.
     */
    public const MAPPING = [
        'songkran' => [
            'keywords' => ['Songkran', 'สงกรานต์'],
            'note'     => 'Government public holiday Apr 13-15',
        ],
        'new-year' => [
            'keywords' => ["New Year's Day", 'วันขึ้นปีใหม่'],
            'note'     => "Jan 1 — Google's calendar treats NYE separately",
        ],
        'mothers-day' => [
            'keywords' => ["Queen's Birthday", "Queen Mother", 'วันแม่', "Mother's Day"],
            'note'     => 'Aug 12 — Queen Sirikit birthday = TH Mother\'s Day',
        ],
    ];

    /**
     * Find the Google holiday that matches a festival slug. Returns
     * the holiday array or null.
     */
    public function matchToSlug(Collection $holidays, string $slug): ?array
    {
        $config = self::MAPPING[$slug] ?? null;
        if (!$config) return null;

        return $holidays->first(function (array $holiday) use ($config) {
            $name = $holiday['name'] ?? '';
            foreach ($config['keywords'] as $keyword) {
                if (stripos($name, $keyword) !== false) return true;
            }
            return false;
        });
    }

    /**
     * Fetch all Google holidays for the next ~12 months and tag each
     * one with its match status against existing festivals + a smart
     * theme/emoji guess so the import UI can pre-fill sensible defaults.
     *
     * Returns a Collection of ['name', 'start_date', 'end_date',
     * 'match_status' (matched|importable|already-imported|skip),
     * 'matched_slug', 'suggested_slug', 'suggested_theme',
     * 'suggested_emoji'].
     */
    public function previewWithStatus(): Collection
    {
        $year   = (int) now()->year;
        $cutoff = now()->endOfDay();

        // Combine current + next year so admin sees stuff coming up
        $holidays = $this->getThaiHolidays($year)
            ->merge($this->getThaiHolidays($year + 1))
            ->filter(fn ($h) => \Carbon\Carbon::parse($h['end_date'])->endOfDay()->isAfter($cutoff))
            ->sortBy('start_date')
            ->values();

        // Pre-load existing festivals for match lookup
        $existingSlugs = \App\Models\Festival::pluck('slug')->toArray();
        $existingByGoogleId = \App\Models\Festival::whereNotNull('cta_url')   // best-effort marker
            ->pluck('slug', 'cta_url')->toArray();

        return $holidays->map(function (array $h) use ($existingSlugs) {
            $name = $h['name'] ?? '';

            // 1. Try to match against canonical MAPPING (= already covered)
            $matchedSlug = null;
            foreach (self::MAPPING as $slug => $config) {
                foreach ($config['keywords'] as $kw) {
                    if (stripos($name, $kw) !== false) {
                        $matchedSlug = $slug;
                        break 2;
                    }
                }
            }

            // 2. Compute suggested slug for "importable" rows
            $suggestedSlug = \Illuminate\Support\Str::slug($name) ?: 'event-' . substr(md5($name), 0, 6);

            // 3. Determine status
            if ($matchedSlug) {
                $status = 'matched';   // Google already provides date for our existing canonical festival
            } elseif (in_array($suggestedSlug, $existingSlugs, true)) {
                $status = 'already-imported';
            } else {
                $status = 'importable';
            }

            // 4. Smart theme + emoji guess from name keywords
            [$theme, $emoji] = self::guessThemeAndEmoji($name);

            return array_merge($h, [
                'match_status'    => $status,
                'matched_slug'    => $matchedSlug,
                'suggested_slug'  => $suggestedSlug,
                'suggested_theme' => $theme,
                'suggested_emoji' => $emoji,
            ]);
        });
    }

    /**
     * Heuristic: guess the most appropriate theme + emoji from the
     * holiday's name. Buddhist days → lantern-gold + 🏮, royal days →
     * lantern-gold + 🌷, fixed default → water-blue + ✨.
     *
     * Admin can override both at import time, but having a sensible
     * default makes one-click import feel less like blank guessing.
     *
     * @return array{0: string, 1: string}  [theme_variant, emoji]
     */
    public static function guessThemeAndEmoji(string $name): array
    {
        $lower = mb_strtolower($name, 'UTF-8');

        // Buddhist holy days (lunar)
        $buddhistKeywords = ['buddha', 'พุทธ', 'บูชา', 'ปวารณา', 'พรรษา', 'มาฆ', 'วิสาขะ', 'อาสาฬห', 'magha', 'visakha', 'asanha', 'lent'];
        foreach ($buddhistKeywords as $kw) {
            if (str_contains($lower, $kw)) return ['lantern-gold', '🏮'];
        }

        // Royal / monarchy / coronation
        $royalKeywords = ['king', 'queen', 'royal', 'coronation', 'chakri', 'ราชา', 'ราชินี', 'จักรี', 'พระบรมราช', 'พระราช', 'พระมหา'];
        foreach ($royalKeywords as $kw) {
            if (str_contains($lower, $kw)) return ['lantern-gold', '🌷'];
        }

        // Constitution / political
        $stateKeywords = ['constitution', 'รัฐธรรมนูญ', 'labour', 'labor', 'แรงงาน'];
        foreach ($stateKeywords as $kw) {
            if (str_contains($lower, $kw)) return ['red-firework', '🇹🇭'];
        }

        // Songkran / new year (water themed)
        if (str_contains($lower, 'songkran') || str_contains($lower, 'สงกรานต์')) return ['water-blue', '💦'];
        if (str_contains($lower, 'new year') || str_contains($lower, 'ปีใหม่')) return ['red-firework', '🎆'];

        // Christmas
        if (str_contains($lower, 'christmas') || str_contains($lower, 'คริสต์มาส')) return ['snow-white', '🎄'];

        // Default — neutral
        return ['water-blue', '✨'];
    }
}
