<?php

namespace App\Observers;

use App\Models\EventPhoto;
use App\Services\CreditService;
use App\Services\StorageQuotaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keep photographer_profiles.storage_used_bytes in sync as photos come and go,
 * and (for credits-mode photographers) debit/refund upload credits alongside.
 *
 * Why observer not direct calls:
 *   • Uploads happen through PhotoController but also through sync jobs,
 *     admin batch imports, and the future API — attaching the accounting
 *     to the model itself means we never forget to call it somewhere.
 *   • The existing EventPhoto model already has a `deleting` hook that
 *     sweeps files from storage; this observer keeps the byte counter
 *     in lock-step so "file deleted from R2" and "counter decremented"
 *     happen as a pair.
 *   • Consuming credits from the observer means controllers, batch jobs,
 *     CLI imports, and future API uploads all go through the same meter
 *     without having to remember to call CreditService themselves.
 *
 * Credit rules applied here:
 *   • Consume 1 credit per row for credits-mode photographers only.
 *   • Commission-mode photographers are ignored (their counter is enforced
 *     by storage quota instead).
 *   • If middleware already admitted the upload, a consume that now fails
 *     (e.g. the row is the last one before balance hits zero under a
 *     concurrent race) is logged and swallowed — we don't want to fail the
 *     insert that already happened on R2. The balance will just go to its
 *     grace floor and the next upload will be refused up-front.
 *   • On quick-delete (<= 5 min since upload), we refund 1 credit. This
 *     covers the "I uploaded the wrong file, deleted it 30 seconds later"
 *     case without opening a loophole where photographers mass-delete to
 *     farm credits back — the window is deliberately short.
 *
 * Caveat: Eloquent model events don't fire on mass deletes such as
 * `EventPhoto::where(...)->delete()` or FK cascades. Those paths need
 * to hit StorageQuotaService::recalculate() directly (done in
 * PurgeEventJob after the cascade) or wait for the nightly recalc to
 * self-heal. Credits are NOT auto-refunded on cascade/mass delete — they
 * were consumed at upload time and staying spent is the correct default.
 */
class EventPhotoStorageObserver
{
    /** Photos deleted within this many seconds of creation get a credit refund. */
    private const QUICK_DELETE_REFUND_WINDOW_SECONDS = 300; // 5 minutes

    public function __construct(
        private StorageQuotaService $quota,
        private CreditService $credits,
    ) {}

    /**
     * New row = original file just landed on R2. We credit the photographer
     * who owns the event (not who uploaded — they may be staff acting on
     * behalf of the photographer) and, if they're on the credits plan,
     * decrement their balance by one.
     */
    public function created(EventPhoto $photo): void
    {
        $bytes = (int) $photo->file_size;
        if ($bytes <= 0 || !$photo->event_id) return;

        $info = $this->lookupPhotographer($photo->event_id);
        if (!$info) return;

        [$photogId, $billingMode] = [$info['photographer_id'], $info['billing_mode']];

        // Always record bytes — quota tracking is mode-agnostic, credits mode
        // still wants a used_bytes figure for the dashboard & R2 billing math.
        $this->quota->recordUpload($photogId, $bytes);

        // Only debit credits for photographers who opted into the credits plan.
        if ($billingMode === 'credits' && $this->credits->systemEnabled()) {
            try {
                $ok = $this->credits->consume(
                    $photogId,
                    1,
                    'event_photo',
                    (string) $photo->id,
                );

                if (!$ok) {
                    // Middleware admitted this upload but consume failed — likely
                    // a race where balance was exactly 1 and another upload won.
                    // Log so ops can reconcile; the insert itself stays.
                    Log::warning('Credit consume failed after upload accepted', [
                        'photographer_id' => $photogId,
                        'event_photo_id'  => $photo->id,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Credit consume threw in observer', [
                    'photographer_id' => $photogId,
                    'event_photo_id'  => $photo->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Row deleted = files are being swept from R2 by the EventPhoto
     * `deleting` hook. Refund the bytes to the photographer. We use the
     * `deleted` event (not `deleting`) so we only refund if the delete
     * actually went through.
     *
     * For credits-mode photographers we also refund 1 credit, but only if
     * the photo was created within QUICK_DELETE_REFUND_WINDOW_SECONDS —
     * this is the "oops, wrong photo" escape hatch, not a replacement for
     * the 0% commission model.
     */
    public function deleted(EventPhoto $photo): void
    {
        $bytes = (int) $photo->file_size;
        if ($bytes <= 0 || !$photo->event_id) return;

        $info = $this->lookupPhotographer($photo->event_id);
        if (!$info) return;

        [$photogId, $billingMode] = [$info['photographer_id'], $info['billing_mode']];

        $this->quota->recordDelete($photogId, $bytes);

        if ($billingMode === 'credits' && $this->credits->systemEnabled()) {
            $createdAt = $photo->created_at;
            if ($createdAt instanceof Carbon
                && $createdAt->diffInSeconds(now()) <= self::QUICK_DELETE_REFUND_WINDOW_SECONDS
            ) {
                try {
                    $this->credits->refund(
                        $photogId,
                        1,
                        'event_photo',
                        (string) $photo->id,
                        'Quick delete within ' . self::QUICK_DELETE_REFUND_WINDOW_SECONDS . 's window',
                    );
                } catch (\Throwable $e) {
                    Log::warning('Credit refund on quick-delete failed', [
                        'photographer_id' => $photogId,
                        'event_photo_id'  => $photo->id,
                        'error'           => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Lookup with tiny fallback safety — the event row may already be gone
     * during a cascade delete, in which case we can't credit the photographer
     * (and the nightly recalc will fix any drift).
     *
     * Returns both the photographer user_id and their billing_mode in one
     * query so we can decide whether to touch the credit ledger without a
     * second round-trip.
     *
     * @return array{photographer_id:int, billing_mode:string}|null
     */
    private function lookupPhotographer(int $eventId): ?array
    {
        try {
            $row = DB::table('event_events as e')
                ->leftJoin('photographer_profiles as p', 'p.user_id', '=', 'e.photographer_id')
                ->where('e.id', $eventId)
                ->select('e.photographer_id', 'p.billing_mode')
                ->first();

            if (!$row || !$row->photographer_id) {
                return null;
            }

            return [
                'photographer_id' => (int) $row->photographer_id,
                'billing_mode'    => (string) ($row->billing_mode ?? 'commission'),
            ];
        } catch (\Throwable $e) {
            Log::debug('EventPhotoStorageObserver lookup failed', [
                'event_id' => $eventId,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }
}
