<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Booking lifecycle service.
 *
 * Centralises every state transition (create / confirm / cancel / complete)
 * so the business rules (conflict detection, LINE notifications, in-app
 * notifications) live in one place. Controllers just call these methods.
 */
class BookingService
{
    public function __construct(
        private LineNotifyService $line,
        private ?AvailabilityService $availability = null,
        private ?GoogleCalendarSyncService $gcal = null,
    ) {
        $this->availability ??= app(AvailabilityService::class);
        // GCal is optional — it self-disables if Google credentials are missing.
        $this->gcal ??= app(GoogleCalendarSyncService::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Create — customer requests a booking
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Customer creates a new booking request. Photographer must confirm before
     * it counts as locked-in (status starts as 'pending').
     *
     * @param array{
     *   customer_user_id:int,
     *   photographer_id:int,
     *   title:string,
     *   scheduled_at:string|Carbon,
     *   duration_minutes?:int,
     *   description?:string,
     *   location?:string,
     *   location_lat?:float,
     *   location_lng?:float,
     *   package_name?:string,
     *   expected_photos?:int,
     *   agreed_price?:float,
     *   customer_phone?:string,
     *   customer_notes?:string,
     * } $data
     */
    public function create(array $data, ?string $idempotencyKey = null): Booking
    {
        // ── Idempotency short-circuit ────────────────────────────────
        // If the caller passed a key (Idempotency-Key header on the
        // POST), and a booking already exists with that key, return it
        // instead of creating a duplicate. The unique partial index on
        // bookings.idempotency_key enforces this at the DB layer too.
        if ($idempotencyKey) {
            $existing = Booking::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) return $existing;
        }

        // The TOCTOU bug we're closing: previously, two customers
        // POSTing to /book at the same millisecond could both read the
        // same "no overlap" state and both insert. Holding a transaction
        // + photographer-row advisory lock collapses that to one
        // serial path through this method per photographer.
        return DB::transaction(function () use ($data, $idempotencyKey) {
            $photographerId = (int) ($data['photographer_id'] ?? 0);
            $this->acquirePhotographerLock($photographerId);

            $startsAt = $data['scheduled_at'] instanceof Carbon
                ? $data['scheduled_at']
                : Carbon::parse((string) $data['scheduled_at']);
            $duration = (int) ($data['duration_minutes'] ?? 120);
            $endsAt   = (clone $startsAt)->addMinutes($duration);

            // Real conflict check now that we're holding the lock —
            // the customer form does a soft pre-check, but only here
            // can we make it authoritative.
            $conflict = Booking::overlapping($photographerId, $startsAt, $endsAt)->first();
            if ($conflict && empty($data['is_waitlist'])) {
                throw new \DomainException(
                    'ช่วงเวลานี้มีคิวงานอื่นอยู่แล้ว — ลองเลือกเวลาอื่น หรือเข้า waitlist'
                );
            }

            try {
                $booking = Booking::create([
                    ...$data,
                    'duration_minutes' => $duration,
                    'status'           => Booking::STATUS_PENDING,
                    'idempotency_key'  => $idempotencyKey,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Concurrent retry won the race against our pre-check —
                // unique index on idempotency_key fired. Return the winner.
                if ($idempotencyKey && $this->isUniqueViolation($e)) {
                    $winner = Booking::where('idempotency_key', $idempotencyKey)->first();
                    if ($winner) return $winner;
                }
                throw $e;
            }

            // Notify photographer (in-app + LINE)
            $this->notifyPhotographerOfNewBooking($booking);

            // Best-effort export to the Bookings spreadsheet (no-op when
            // disabled). The job audits its own success/failure into
            // booking_sheets_exports.
            \App\Jobs\Booking\ExportBookingToSheetJob::dispatch($booking->id, 'append');

            return $booking;
        });
    }

    /**
     * Acquire a transactional lock keyed by photographer id. Postgres has
     * pg_advisory_xact_lock (cheap, no row needed). MySQL has GET_LOCK.
     * SQLite (test env) serialises writes anyway — no-op there.
     *
     * The lock is released automatically on transaction commit/rollback.
     */
    private function acquirePhotographerLock(int $photographerId): void
    {
        if ($photographerId <= 0) return;
        $driver = DB::connection()->getDriverName();
        try {
            match ($driver) {
                'pgsql'  => DB::statement('SELECT pg_advisory_xact_lock(?, ?)', [
                                /* magic namespace */ 0xB0_0C_15,  // 'BOOC15' ≈ booking-conflict
                                $photographerId,
                            ]),
                'mysql', 'mariadb' => DB::select('SELECT GET_LOCK(?, 5) AS got', [
                                "booking_pg_{$photographerId}",
                            ]),
                default  => null, // sqlite serialises writes; nothing to do
            };
        } catch (\Throwable $e) {
            // Advisory locks are best-effort — if they fail we still have
            // the transaction's own write-ordering guarantees plus the
            // unique partial index as a final safety net.
            Log::info('booking.advisory_lock_skipped', ['err' => $e->getMessage()]);
        }
    }

    private function isUniqueViolation(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return $e->getCode() === '23505'
            || $e->getCode() === '23000'
            || str_contains($msg, 'duplicate')
            || str_contains($msg, 'unique constraint');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Confirm — photographer accepts the booking
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Photographer confirms a pending booking. Throws if a time-window conflict
     * exists with any other pending/confirmed booking — caller must handle.
     */
    public function confirm(Booking $booking): Booking
    {
        // The TOCTOU bug we're closing: previously the conflict scan
        // ran outside any lock — between the SELECT and the UPDATE,
        // another request could insert a conflicting booking and the
        // photographer would end up with two confirmed bookings on the
        // same slot. The transaction + advisory lock + lockForUpdate
        // pattern collapses the entire confirm path to a serial section
        // per photographer.
        return DB::transaction(function () use ($booking) {
            $this->acquirePhotographerLock((int) $booking->photographer_id);

            // Re-read the booking with FOR UPDATE — its status may have
            // changed since the controller loaded it.
            $fresh = Booking::lockForUpdate()->find($booking->id);
            if (!$fresh) {
                throw new \DomainException('Booking not found');
            }
            if (!$fresh->isPending()) {
                throw new \DomainException('สามารถยืนยันได้เฉพาะ booking ที่อยู่ในสถานะ pending');
            }

            // Conflict guard — same photographer, same time window.
            // Now race-safe: any concurrent insert is blocked behind
            // the advisory lock until this transaction commits.
            $conflict = Booking::overlapping(
                $fresh->photographer_id,
                $fresh->scheduled_at,
                $fresh->ends_at,
                $fresh->id,
            )->first();
            if ($conflict) {
                throw new \DomainException(sprintf(
                    'มี booking #%d ทับเวลาเดียวกัน (%s) — ขัดแย้งกับการยืนยันงานนี้',
                    $conflict->id,
                    $conflict->scheduled_at?->format('d/m/Y H:i') ?? '?'
                ));
            }

            $fresh->update([
                'status'       => Booking::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);

            $this->notifyCustomerOfConfirmation($fresh);

            // GCal sync — moved off the critical path. The job retries
            // on transient 5xx and writes booking_calendar_sync rows
            // for audit. Synchronous errors here used to be swallowed;
            // queueing makes them properly observable.
            \App\Jobs\Booking\SyncBookingToCalendarJob::dispatch($fresh->id, 'upsert');
            \App\Jobs\Booking\ExportBookingToSheetJob::dispatch($fresh->id, 'update');

            return $fresh;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cancel — either side
    // ─────────────────────────────────────────────────────────────────────

    public function cancel(Booking $booking, string $cancelledBy, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $cancelledBy, $reason) {
            // Lock so a parallel "confirm" can't slip past the
            // status-already-cancelled check.
            $fresh = Booking::lockForUpdate()->find($booking->id);
            if (!$fresh) {
                throw new \DomainException('Booking not found');
            }
            if ($fresh->isCompleted() || $fresh->isCancelled()) {
                throw new \DomainException('ไม่สามารถยกเลิก booking ที่เสร็จหรือยกเลิกไปแล้ว');
            }

            $fresh->update([
                'status'              => Booking::STATUS_CANCELLED,
                'cancelled_at'        => now(),
                'cancelled_by'        => $cancelledBy,
                'cancellation_reason' => $reason,
            ]);

            $this->notifyCounterpartOfCancellation($fresh, $cancelledBy);

            // GCal removal goes via the queue — same audit trail as
            // upsert. The job is a no-op when gcal_event_id is null.
            \App\Jobs\Booking\SyncBookingToCalendarJob::dispatch($fresh->id, 'delete');
            \App\Jobs\Booking\ExportBookingToSheetJob::dispatch($fresh->id, 'update');

            // Auto-promote first waitlist entry for this slot, if any.
            $this->promoteWaitlistFor($fresh);

            return $fresh;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Waitlist — auto-promote first waiter when primary cancels
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Find the oldest waitlist entry pointing at this booking and
     * promote it: clear waitlist flag, mark promoted_at, notify customer.
     * Photographer still has to confirm explicitly — we don't auto-confirm.
     */
    private function promoteWaitlistFor(Booking $cancelledBooking): void
    {
        $next = Booking::where('waitlist_for_id', $cancelledBooking->id)
            ->where('is_waitlist', true)
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->orderBy('created_at')
            ->first();

        if (!$next) return;

        $next->update([
            'is_waitlist'              => false,
            'waitlist_for_id'          => null,
            'promoted_from_waitlist_at'=> now(),
            'status'                   => Booking::STATUS_PENDING,
        ]);

        $this->pushLine($next->customer_user_id, sprintf(
            "🎉 คุณได้เลื่อนคิวจาก waitlist!\n📷 งาน: %s\n📅 %s\n\nรอช่างภาพยืนยันใน 24 ชม.",
            $next->title,
            $next->scheduled_at?->format('d/m/Y H:i'),
        ), gateByPhotographerId: (int) $next->photographer_id);
        $this->pushLine($next->photographer_id, sprintf(
            "🆕 มีคิวงานใหม่จาก waitlist (เพราะคุณยกเลิกงานเก่า)\n📷 %s\n📅 %s",
            $next->title,
            $next->scheduled_at?->format('d/m/Y H:i'),
        ));
    }

    /**
     * Add a booking to the waitlist for an existing pending/confirmed
     * booking (e.g. customer wants the same slot but it's taken).
     */
    public function addToWaitlist(array $data, int $waitForBookingId): Booking
    {
        return Booking::create([
            ...$data,
            'duration_minutes' => $data['duration_minutes'] ?? 120,
            'status'           => Booking::STATUS_PENDING,
            'is_waitlist'      => true,
            'waitlist_for_id'  => $waitForBookingId,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Deposit — payment record for confirmed bookings
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Record that a deposit payment landed for the booking.
     * Called by the payment webhook handler after Stripe/Omise confirms.
     */
    public function markDepositPaid(Booking $booking, float $amount, ?string $paymentId = null, ?string $idempotencyKey = null): Booking
    {
        // Idempotent webhook: a Stripe/Omise retry must not double the
        // deposit_paid total. We use a deterministic key (gateway txn id
        // when present, else explicit caller-supplied) and short-circuit
        // if it already lives on the booking.
        $idempotencyKey = $idempotencyKey ?? ($paymentId ? "payment.{$paymentId}" : null);

        return DB::transaction(function () use ($booking, $amount, $paymentId, $idempotencyKey) {
            $fresh = Booking::lockForUpdate()->find($booking->id);
            if (!$fresh) {
                throw new \DomainException('Booking not found');
            }

            // Replay path — same key already credited. Return without
            // modifying anything; caller still gets the booking back.
            if ($idempotencyKey && $fresh->deposit_idempotency_key === $idempotencyKey) {
                return $fresh;
            }

            $fresh->update([
                'deposit_paid'             => $fresh->deposit_paid + $amount,
                'deposit_paid_at'          => $fresh->deposit_paid_at ?? now(),
                'deposit_payment_id'       => $paymentId ?? $fresh->deposit_payment_id,
                'deposit_idempotency_key'  => $idempotencyKey ?? $fresh->deposit_idempotency_key,
            ]);

            $this->queuePushLine($fresh->customer_user_id, sprintf(
                "✅ ได้รับเงินมัดจำแล้ว!\n📷 %s\n💰 มัดจำ: %s ฿\n\nคิวงานยืนยันสมบูรณ์",
                $fresh->title,
                number_format((float) $fresh->deposit_paid),
            ), idempotencyKey: $idempotencyKey ? "deposit.{$fresh->id}.cust" : null,
               gateByPhotographerId: (int) $fresh->photographer_id);
            $this->queuePushLine($fresh->photographer_id, sprintf(
                "💰 ลูกค้าจ่ายมัดจำแล้ว!\n📷 %s\n💵 %s ฿",
                $fresh->title,
                number_format((float) $amount),
            ), idempotencyKey: $idempotencyKey ? "deposit.{$fresh->id}.pg" : null);

            return $fresh;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Complete — photographer marks shoot done
    // ─────────────────────────────────────────────────────────────────────

    public function complete(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $fresh = Booking::lockForUpdate()->find($booking->id);
            if (!$fresh) throw new \DomainException('Booking not found');
            if (!$fresh->isConfirmed()) {
                throw new \DomainException('ต้องเป็น booking ที่ยืนยันแล้ว ถึงจะ mark เสร็จได้');
            }

            $fresh->update([
                'status'       => Booking::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            \App\Jobs\Booking\ExportBookingToSheetJob::dispatch($fresh->id, 'update');

            // Post-shoot review reminder fires later via the cron — see SendBookingReminders
            return $fresh;
        });
    }

    /**
     * Admin-only: mark a confirmed booking as no-show. Goes through the
     * service so admin no-shows generate the same notifications + sheets
     * sync + GCal removal as a regular cancel — the previous direct
     * model-update bypass meant the photographer never got told.
     */
    public function markNoShow(Booking $booking, ?int $adminId, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $adminId, $reason) {
            $fresh = Booking::lockForUpdate()->find($booking->id);
            if (!$fresh) throw new \DomainException('Booking not found');
            if ($fresh->status === Booking::STATUS_NO_SHOW) return $fresh;

            $fresh->update([
                'status'              => Booking::STATUS_NO_SHOW,
                'cancelled_at'        => now(),
                'cancelled_by'        => Booking::CANCELLED_BY_ADMIN,
                'cancellation_reason' => $reason ?? 'No show',
                'admin_id'            => $adminId,
            ]);

            // Tell the photographer (the customer didn't show — useful
            // for record-keeping and future flag-this-customer logic).
            $this->queuePushLine($fresh->photographer_id, sprintf(
                "🚫 Admin มาร์ค booking #%d เป็น no-show\n📷 %s\n📅 %s",
                $fresh->id,
                $fresh->title,
                $fresh->scheduled_at?->format('d/m/Y H:i'),
            ), idempotencyKey: "booking.{$fresh->id}.no_show.pg");

            \App\Jobs\Booking\SyncBookingToCalendarJob::dispatch($fresh->id, 'delete');
            \App\Jobs\Booking\ExportBookingToSheetJob::dispatch($fresh->id, 'update');

            return $fresh;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Conflict-aware availability check (used by customer booking form)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Is the photographer available between $start and $end? Returns false if
     * any pending/confirmed booking already covers any part of that window.
     */
    public function isAvailable(int $photographerId, Carbon $start, Carbon $end): bool
    {
        return !Booking::overlapping($photographerId, $start, $end)->exists();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Notifications — LINE + in-app
    // ─────────────────────────────────────────────────────────────────────

    private function notifyPhotographerOfNewBooking(Booking $booking): void
    {
        try {
            // In-app notification — uses existing UserNotification model
            UserNotification::create([
                'user_id'     => $booking->photographer_id,
                'type'        => 'booking_new',
                'title'       => 'ลูกค้าจองคิวงานใหม่',
                'message'     => sprintf(
                    'งาน "%s" วันที่ %s — กรุณายืนยันภายใน 24 ชม.',
                    $booking->title,
                    $booking->scheduled_at?->format('d/m/Y H:i')
                ),
                'action_url'  => '/photographer/bookings/' . $booking->id,
                'ref_id'      => 'booking:' . $booking->id,
                'is_read'     => 0,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('booking.notify_pg_inapp_failed', ['err' => $e->getMessage(), 'booking_id' => $booking->id]);
        }

        // LINE push to photographer
        $this->pushLine($booking->photographer_id, sprintf(
            "🆕 มีคิวงานใหม่!\n📷 งาน: %s\n📅 วันที่: %s\n👤 ลูกค้า: %s\n💰 ราคา: %s\n\nยืนยันภายใน 24 ชม.",
            $booking->title,
            $booking->scheduled_at?->format('d/m/Y H:i'),
            $booking->customer?->first_name ?? 'ไม่ระบุ',
            $booking->agreed_price ? number_format($booking->agreed_price) . ' บาท' : 'รอตกลง',
        ));
    }

    private function notifyCustomerOfConfirmation(Booking $booking): void
    {
        try {
            UserNotification::create([
                'user_id'    => $booking->customer_user_id,
                'type'       => 'booking_confirmed',
                'title'      => 'ช่างภาพยืนยันคิวงานแล้ว',
                'message'    => sprintf(
                    'งาน "%s" ยืนยันสำหรับ %s',
                    $booking->title,
                    $booking->scheduled_at?->format('d/m/Y H:i')
                ),
                'action_url' => '/profile/bookings',
                'ref_id'     => 'booking:' . $booking->id,
                'is_read'    => 0,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('booking.notify_cust_inapp_failed', ['err' => $e->getMessage(), 'booking_id' => $booking->id]);
        }

        // Append a Google Maps directions link if we have any usable
        // location data — coords preferred, address fallback.
        $mapsLink = \App\Services\GoogleMapsLink::directions(
            $booking->location_lat ? (float) $booking->location_lat : null,
            $booking->location_lng ? (float) $booking->location_lng : null,
            $booking->location,
        );

        $this->queuePushLine(
            $booking->customer_user_id,
            sprintf(
                "✅ ช่างภาพยืนยันคิวงานของคุณแล้ว\n📷 งาน: %s\n📅 วันที่: %s\n📍 สถานที่: %s%s\n\nระบบจะส่ง reminder ก่อนวันงาน 3 วัน · 1 วัน · 1 ชม.",
                $booking->title,
                $booking->scheduled_at?->format('d/m/Y H:i'),
                $booking->location ?? 'ตามที่ตกลง',
                $mapsLink ? "\n🗺️ นำทาง: {$mapsLink}" : '',
            ),
            idempotencyKey: "booking.{$booking->id}.confirmed",
            gateByPhotographerId: (int) $booking->photographer_id,
        );
    }

    private function notifyCounterpartOfCancellation(Booking $booking, string $cancelledBy): void
    {
        // Tell the OTHER party (not the one who cancelled).
        $targetUserId = $cancelledBy === Booking::CANCELLED_BY_CUSTOMER
            ? $booking->photographer_id
            : $booking->customer_user_id;

        $msg = sprintf(
            "❌ Booking ถูกยกเลิก\n📷 งาน: %s\n📅 วันที่: %s\n👤 ยกเลิกโดย: %s\n%s",
            $booking->title,
            $booking->scheduled_at?->format('d/m/Y H:i'),
            $cancelledBy === Booking::CANCELLED_BY_CUSTOMER ? 'ลูกค้า' : ($cancelledBy === Booking::CANCELLED_BY_PHOTOGRAPHER ? 'ช่างภาพ' : 'แอดมิน'),
            $booking->cancellation_reason ? "📝 เหตุผล: {$booking->cancellation_reason}" : '',
        );

        // Gate ONLY when target is the customer (photographer-context push).
        // When target is the photographer themselves (cancelled-by-customer),
        // the message is a self-notification and stays unconditional.
        $gateBy = $targetUserId === $booking->customer_user_id
            ? (int) $booking->photographer_id
            : null;
        $this->pushLine($targetUserId, trim($msg), $gateBy);
    }

    /**
     * Tiny wrapper around LINE push so this class doesn't have to handle
     * the `isMessagingEnabled` + `getLineUserId` plumbing each time.
     * Best-effort: failures logged, never thrown.
     */
    /**
     * Push a LINE message, optionally plan-gated.
     *
     * When $gateByPhotographerId is set, PlanGate::canUseLine() must
     * pass before the message is sent — used for CUSTOMER-direction
     * pushes (booking confirms, reminders, cancellations) where the
     * platform charge follows the photographer's plan. Photographer
     * SELF-notifications (eg. "your customer just confirmed") pass
     * null here so they always go through, regardless of subscription
     * — they're a platform feature for every photographer to keep
     * track of their own bookings.
     */
    private function pushLine(int $userId, string $text, ?int $gateByPhotographerId = null): void
    {
        if ($gateByPhotographerId !== null
            && !\App\Support\PlanGate::canUseLine($gateByPhotographerId)) {
            Log::info('booking.line_blocked_by_plan', [
                'recipient_user_id' => $userId,
                'photographer_id'   => $gateByPhotographerId,
            ]);
            return;
        }
        try {
            $this->line->pushText($userId, $text);
        } catch (\Throwable $e) {
            Log::warning('booking.line_push_failed', [
                'user_id' => $userId,
                'err'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Queued-push variant — enqueues a SendLinePushJob with a stable
     * idempotency key. Use this for booking-event notifications: the
     * job retries 5x with backoff on transient LINE 5xx, and the
     * idempotency key collapses webhook retries to a single delivery.
     *
     * Same plan-gate semantics as pushLine() — pass photographer_id when
     * sending to the customer; pass null when sending to the photographer
     * themselves.
     */
    private function queuePushLine(int $userId, string $text, ?string $idempotencyKey = null, ?int $gateByPhotographerId = null): void
    {
        if ($gateByPhotographerId !== null
            && !\App\Support\PlanGate::canUseLine($gateByPhotographerId)) {
            Log::info('booking.line_queue_blocked_by_plan', [
                'recipient_user_id' => $userId,
                'photographer_id'   => $gateByPhotographerId,
                'idempotency_key'   => $idempotencyKey,
            ]);
            return;
        }
        try {
            $this->line->queuePushToUser(
                $userId,
                [['type' => 'text', 'text' => $text]],
                idempotencyKey: $idempotencyKey,
            );
        } catch (\Throwable $e) {
            Log::warning('booking.line_queue_failed', [
                'user_id' => $userId,
                'err'     => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Reminder dispatch — called by SendBookingReminders cron
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Send the T-3-days reminder. Called by cron at the right time window.
     */
    public function sendReminder3Days(Booking $booking): bool
    {
        if (!$this->claimReminderSlot($booking, '3d')) return false;

        $this->queuePushLine($booking->customer_user_id, sprintf(
            "📅 อีก 3 วันเจอกัน!\n📷 งาน: %s\n🕐 %s\n📍 %s\n\nเตรียมพร้อมไว้นะครับ",
            $booking->title,
            $booking->scheduled_at?->format('d/m/Y H:i'),
            $booking->location ?? 'ตามที่ตกลง',
        ), idempotencyKey: "booking.{$booking->id}.reminder.3d.cust",
           gateByPhotographerId: (int) $booking->photographer_id);
        $this->queuePushLine($booking->photographer_id, sprintf(
            "📅 อีก 3 วันมีงาน!\n📷 %s\n🕐 %s\n👤 %s\n📞 %s",
            $booking->title,
            $booking->scheduled_at?->format('d/m/Y H:i'),
            $booking->customer?->first_name ?? '?',
            $booking->customer_phone ?? '?',
        ), idempotencyKey: "booking.{$booking->id}.reminder.3d.pg");

        $this->markReminderSent($booking, '3d', 'reminder_3d_sent_at');
        return true;
    }

    public function sendReminder1Day(Booking $booking): bool
    {
        if (!$this->claimReminderSlot($booking, '1d')) return false;

        $this->queuePushLine($booking->customer_user_id, sprintf(
            "⏰ พรุ่งนี้แล้ว!\n📷 %s\n🕐 %s\n📍 %s\n\nเจอกันครับ",
            $booking->title,
            $booking->scheduled_at?->format('H:i'),
            $booking->location ?? 'ตามที่ตกลง',
        ), idempotencyKey: "booking.{$booking->id}.reminder.1d.cust",
           gateByPhotographerId: (int) $booking->photographer_id);
        $this->queuePushLine($booking->photographer_id, sprintf(
            "⏰ พรุ่งนี้มีงาน — เตรียมอุปกรณ์!\n📷 %s\n🕐 %s\n👤 %s · 📞 %s",
            $booking->title,
            $booking->scheduled_at?->format('H:i'),
            $booking->customer?->first_name ?? '?',
            $booking->customer_phone ?? '?',
        ), idempotencyKey: "booking.{$booking->id}.reminder.1d.pg");

        $this->markReminderSent($booking, '1d', 'reminder_1d_sent_at');
        return true;
    }

    public function sendReminder1Hour(Booking $booking): bool
    {
        if (!$this->claimReminderSlot($booking, '1h')) return false;

        $this->queuePushLine($booking->photographer_id, sprintf(
            "🚨 อีก 1 ชั่วโมงงานเริ่ม!\n📷 %s\n🕐 %s\n📍 %s",
            $booking->title,
            $booking->scheduled_at?->format('H:i'),
            $booking->location ?? '?',
        ), idempotencyKey: "booking.{$booking->id}.reminder.1h.pg");

        $this->markReminderSent($booking, '1h', 'reminder_1h_sent_at');
        return true;
    }

    /**
     * Atomic reminder slot claim.
     *
     * The previous read-then-write pattern (`if (column) return; update`)
     * had a race window: two cron processes could both see column=null,
     * both send LINE pushes, both update the column. The customer would
     * receive duplicate reminders.
     *
     * This new pattern uses an INSERT into booking_reminder_claims with
     * a unique (booking_id, slot) constraint. Whichever process inserts
     * first owns the slot; the loser gets a unique-violation and skips.
     * The claim is created BEFORE we send anything, so even if the
     * push itself fails, the slot is still claimed and the next cron
     * tick won't re-attempt — keep the noise out of LINE quota.
     */
    private function claimReminderSlot(Booking $booking, string $slot): bool
    {
        // Legacy column read kept as a fast-path for backfilled rows.
        $col = "reminder_{$slot}_sent_at";
        if (in_array($slot, ['3d', '1d', '1h', 'day', 'post'], true)) {
            $colReal = match ($slot) {
                '3d'   => 'reminder_3d_sent_at',
                '1d'   => 'reminder_1d_sent_at',
                '1h'   => 'reminder_1h_sent_at',
                'day'  => 'reminder_day_sent_at',
                'post' => 'reminder_post_sent_at',
            };
            if (!empty($booking->{$colReal})) return false;
        }

        try {
            DB::table('booking_reminder_claims')->insert([
                'booking_id' => $booking->id,
                'slot'       => $slot,
                'claimed_at' => now(),
                'claimed_by' => substr(gethostname() ?: 'unknown', 0, 32) . ':' . getmypid(),
                'status'     => 'claimed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                // Another process already claimed this slot — the
                // dedup contract is satisfied, just skip silently.
                return false;
            }
            throw $e;
        }
    }

    /**
     * Mark a previously-claimed slot as sent. Updates both the
     * legacy column on bookings (for backwards compatibility with
     * code that still reads it) AND the claim row's status.
     */
    private function markReminderSent(Booking $booking, string $slot, string $legacyColumn): void
    {
        $booking->update([$legacyColumn => now()]);
        DB::table('booking_reminder_claims')
            ->where('booking_id', $booking->id)
            ->where('slot', $slot)
            ->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
    }

    public function sendReminderDayOf(Booking $booking): bool
    {
        if (!$this->claimReminderSlot($booking, 'day')) return false;

        $this->queuePushLine($booking->customer_user_id, sprintf(
            "📸 วันนี้คือวันงาน!\n📷 %s\n🕐 %s",
            $booking->title,
            $booking->scheduled_at?->format('H:i'),
        ), idempotencyKey: "booking.{$booking->id}.reminder.day.cust",
           gateByPhotographerId: (int) $booking->photographer_id);

        $this->markReminderSent($booking, 'day', 'reminder_day_sent_at');
        return true;
    }

    /**
     * Post-shoot review-prompt — sent 1 day after a completed booking.
     */
    public function sendPostShootReviewPrompt(Booking $booking): bool
    {
        if (!$booking->isCompleted()) return false;
        if (!$this->claimReminderSlot($booking, 'post')) return false;

        $this->queuePushLine($booking->customer_user_id, sprintf(
            "🌟 หวังว่างานเมื่อวานจะเป็นที่พอใจ!\nช่วยเขียนรีวิวให้ช่างภาพหน่อยนะครับ — ใช้เวลา 30 วินาที\n📝 %s",
            url('/orders'),
        ), idempotencyKey: "booking.{$booking->id}.reminder.post.cust",
           gateByPhotographerId: (int) $booking->photographer_id);

        $this->markReminderSent($booking, 'post', 'post_shoot_review_sent_at');
        return true;
    }
}
