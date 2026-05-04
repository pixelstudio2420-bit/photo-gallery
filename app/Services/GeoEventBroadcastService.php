<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GeoEventBroadcastService — fires notifications to users in the same
 * geographic area as a newly-published event.
 *
 * Two delivery channels per broadcast:
 *
 *   1. Announcement popup
 *      Inserts a row into `announcements` with:
 *         show_as_popup = true
 *         target_province_id = event.province_id
 *         priority = 'normal'
 *         starts_at = now()
 *         ends_at   = event.shoot_date OR now() + 14 days
 *      Users in that province see the popup on their next page load
 *      via partials/announcement-popup.blade.php — already gated by
 *      announcement_dismissals so each user only sees it once.
 *
 *   2. LINE OA push
 *      For users in the same province who are also LINE friends
 *      (auth_users.line_is_friend=true AND line_user_id NOT NULL),
 *      pushes a templated message via LineNotifyService::pushToUser.
 *      Sent in chunks of 50 to respect LINE's multicast batch limit
 *      and to keep the worker loop bounded if the recipient list is
 *      thousands deep.
 *
 * Both channels are best-effort — failures log warnings but never
 * surface to the photographer who just clicked Publish. The data
 * model that backs them (announcements row) is the source of truth,
 * so a failed LINE push can be re-tried later by an admin without
 * re-issuing the popup announcement.
 */
class GeoEventBroadcastService
{
    public function __construct(
        private readonly LineNotifyService $line,
    ) {}

    /**
     * Fire the broadcast for a newly-public event. Caller (the Event
     * `saved` hook) is responsible for deciding when the transition
     * to public happened — we just run unconditionally here.
     */
    public function broadcastNewEvent(Event $event): void
    {
        if (empty($event->province_id)) {
            // No province = can't geo-target. Skip silently — admin
            // can still manually create an announcement.
            return;
        }

        // Idempotency check via DB instead of Laravel's wasChanged()
        // — wasChanged returns TRUE forever after the first save that
        // actually changed status, even on subsequent no-op saves
        // (touch, save() without dirty attrs). Bypass that quirk by
        // looking up whether we already broadcast this event in the
        // last hour. Same-event re-publishes farther apart than 1h
        // count as "intentional admin re-broadcast" and fire fresh.
        $recentBroadcastExists = DB::table('announcements')
            ->where('slug', 'like', 'event-' . $event->id . '-%')
            ->where('created_at', '>=', now()->subHour())
            ->exists();
        if ($recentBroadcastExists) {
            return;
        }

        $announcement = $this->createAnnouncement($event);
        if ($announcement) {
            $this->pushToLineFriends($event);
        }
    }

    /**
     * Create the popup announcement row. Returns the inserted ID or
     * null on failure.
     */
    private function createAnnouncement(Event $event): ?int
    {
        try {
            $eventUrl = route('events.show', $event->slug ?: $event->id);

            // Derive the announcement window — ends at the shoot date
            // (no point showing "new event!" after it's over) or 14
            // days from now if shoot_date is missing/in-past.
            //
            // Floor at now()+3 days so a same-day or past-date event
            // (e.g. admin publishing a "yesterday's event" for browsing)
            // still gets a brief promotion window. Without this floor
            // a past shoot_date would set ends_at < starts_at and
            // violate the announcements_window_chk constraint.
            $shootEnd = $event->shoot_date
                ? \Carbon\Carbon::parse($event->shoot_date)->endOfDay()
                : null;
            $defaultEnd = now()->addDays(14);
            $minEnd     = now()->addDays(3);
            $endsAt = $shootEnd && $shootEnd->gt($minEnd) ? $shootEnd : $defaultEnd;

            $id = DB::table('announcements')->insertGetId([
                'title'   => '🎉 อีเวนต์ใหม่ใกล้คุณ — ' . Str::limit($event->name, 60),
                'slug'    => 'event-' . $event->id . '-' . Str::random(6),
                'excerpt' => $event->shoot_date
                    ? 'ถ่ายภาพวันที่ ' . \Carbon\Carbon::parse($event->shoot_date)->format('d M Y')
                    : 'จองสิทธิ์ก่อนใคร — เพิ่งเปิดให้ลงทะเบียน',
                'body'   => "**{$event->name}**\n\nช่างภาพในจังหวัดของคุณเพิ่งเปิดอีเวนต์ใหม่ — สแกนหน้าอีเวนต์เพื่อดูรายละเอียดและจองที่นั่งก่อนใคร",
                'cover_image_path'      => $event->cover_image,
                // BUG FIX 2026-05-04: was 'public' which is not in
                // Announcement::AUDIENCE_* constants — the model's
                // visibleTo() scope filters audience IN ('customer','all')
                // so 'public' rows were invisible to /announcements
                // (customer feed) AND to /photographer/announcements.
                // Use 'customer' since geo broadcasts are marketing
                // events targeted at end-customers, not photographers.
                'audience'              => Announcement::AUDIENCE_CUSTOMER,
                'priority'              => 'normal',
                'cta_label'             => 'ดูอีเวนต์',
                'cta_url'               => $eventUrl,
                'status'                => 'published',
                'starts_at'             => now(),
                'ends_at'               => $endsAt,
                'is_pinned'             => false,
                'show_as_popup'         => true,
                'target_province_id'    => $event->province_id,
                // Keep district NULL so the whole province sees it —
                // setting district would be too narrow for a marketplace
                // where intent isn't strongly local. Admin can manually
                // create more-targeted announcements via /admin/announcements.
                'target_district_id'    => null,
                'target_subdistrict_id' => null,
                'view_count'            => 0,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            // Bust per-user popup caches so the new announcement can be
            // picked up by the next page load. The caches are keyed
            // per-user so a flush on JUST users-in-this-province is
            // ideal — but Laravel Cache doesn't support tag-based
            // flush without a Redis backend, so we settle for the
            // partial's 60s TTL and let it auto-expire.
            return (int) $id;
        } catch (\Throwable $e) {
            Log::warning('GeoEventBroadcast: announcement create failed', [
                'event_id' => $event->id,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Push a LINE message to every user in the same province who is a
     * confirmed friend. Chunked to keep LINE rate limits happy.
     */
    private function pushToLineFriends(Event $event): void
    {
        if (!$this->line->isMessagingEnabled()) {
            return;
        }

        $eventUrl = route('events.show', $event->slug ?: $event->id);
        $message  = "🎉 อีเวนต์ใหม่ในจังหวัดของคุณ!\n\n"
                  . "📸 {$event->name}\n"
                  . ($event->shoot_date
                        ? "📅 " . \Carbon\Carbon::parse($event->shoot_date)->format('d M Y') . "\n"
                        : '')
                  . "\nดูรายละเอียด: {$eventUrl}";

        // Eager fetch userIds in chunks of 50 — covers the LINE
        // multicast batch limit and bounds the worker loop.
        User::query()
            ->where('province_id', $event->province_id)
            ->where('line_is_friend', true)
            ->whereNotNull('line_user_id')
            ->select('id', 'line_user_id')
            ->chunk(50, function ($users) use ($message, $event) {
                foreach ($users as $u) {
                    try {
                        $this->line->pushToUser($u->line_user_id, $message);
                    } catch (\Throwable $e) {
                        Log::warning('GeoEventBroadcast: LINE push failed', [
                            'event_id'  => $event->id,
                            'user_id'   => $u->id,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
