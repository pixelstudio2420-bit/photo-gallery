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
     * Returns ['ok' => bool, 'message' => string] for the admin UI.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'ยังไม่ได้ตั้งค่า API Key'];
        }

        try {
            $year = (int) now()->year;
            $items = $this->fetchYear($year);
            $count = $items->count();
            return [
                'ok'      => $count > 0,
                'message' => $count > 0
                    ? "เชื่อมต่อสำเร็จ — พบ {$count} วันสำคัญในปฏิทินไทย ปี {$year}"
                    : 'เชื่อมต่อได้ แต่ไม่มีข้อมูล (ตรวจสอบ Calendar ID)',
                'count'   => $count,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => 'เชื่อมต่อไม่สำเร็จ: ' . $e->getMessage(),
            ];
        }
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
}
