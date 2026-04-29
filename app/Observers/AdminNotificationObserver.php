<?php

namespace App\Observers;

use App\Models\AdminNotification;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\PaymentSlip;
use App\Models\PhotographerProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Admin Notification Generator
 * ============================
 * Single observer attached to multiple models. Generates entries in
 * `admin_notifications` whenever a noteworthy thing happens — new
 * order, new signup, payment received, etc. — so the admin's bell
 * icon lights up without anyone having to call AdminNotification::*
 * from inside business code.
 *
 * Why one observer for many models?
 *   - Each model only has ONE notification type, so per-model classes
 *     would just be boilerplate.
 *   - The class_basename match below routes events to the right
 *     factory method on AdminNotification, keeping the contract in
 *     one file that's easy to audit.
 *
 * Wired up in AppServiceProvider::boot().
 *
 * Status transitions (e.g. Order: pending → paid) are handled in
 * `updated()` to fire paymentSuccess only on the actual flip, not on
 * unrelated edits to the same row.
 *
 * Failures here NEVER block the underlying write — the whole
 * generator is wrapped in try/catch so a notification table outage
 * can't take down checkout.
 */
class AdminNotificationObserver
{
    public function created(Model $model): void
    {
        // Defer the notification insert until the parent transaction
        // commits. Two reasons:
        //   1. If the parent rolls back (e.g. a failed payment write),
        //      we don't leave a phantom "ชำระเงินสำเร็จ" notification
        //      pointing to an order that no longer exists.
        //   2. The notification INSERT shouldn't extend the lock window
        //      on the row we're observing. Especially relevant for
        //      Order::created during checkout — keeping the orders row
        //      locked for the duration of the notification write would
        //      serialise concurrent checkouts.
        // Outside a transaction, afterCommit() runs the closure
        // immediately, so the observer still works in console commands
        // and queue workers.
        DB::afterCommit(function () use ($model) {
            try {
                match (class_basename($model)) {
                    'Order'                => $this->onOrderCreated($model),
                    'User'                 => $this->onUserCreated($model),
                    'PhotographerProfile'  => $this->onPhotographerCreated($model),
                    'ContactMessage'       => $this->onContactCreated($model),
                    'Review'               => $this->onReviewCreated($model),
                    'PaymentSlip'          => $this->onSlipCreated($model),
                    default                => null,
                };
            } catch (\Throwable $e) {
                // Notifications are best-effort; never block the parent write.
                \Log::warning('AdminNotificationObserver.created failed', [
                    'model' => class_basename($model),
                    'id'    => $model->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public function updated(Model $model): void
    {
        // Capture the changed-attributes snapshot now (before deferring)
        // because Eloquent clears wasChanged()/getChanges() once the
        // transaction commits and the model becomes "syncOriginal".
        $wasChanged = method_exists($model, 'getChanges') ? $model->getChanges() : [];

        DB::afterCommit(function () use ($model, $wasChanged) {
            try {
                // Stash the captured changes back onto the model so the
                // handlers can keep using $model->wasChanged('status').
                if (!empty($wasChanged)) {
                    $model->syncChanges();
                }
                match (class_basename($model)) {
                    'Order'                => $this->onOrderUpdated($model, $wasChanged),
                    'PhotographerProfile'  => $this->onPhotographerUpdated($model, $wasChanged),
                    'PaymentSlip'          => $this->onSlipUpdated($model, $wasChanged),
                    default                => null,
                };
            } catch (\Throwable $e) {
                \Log::warning('AdminNotificationObserver.updated failed', [
                    'model' => class_basename($model),
                    'id'    => $model->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    // ─── Per-model handlers ─────────────────────────────────────────

    private function onOrderCreated(Order $order): void
    {
        // Only notify when the order needs admin attention — skip the noise
        // of guest carts that auto-cancel.
        if (in_array($order->status, ['cart', 'abandoned'], true)) {
            return;
        }
        AdminNotification::newOrder($order);
    }

    private function onOrderUpdated(Order $order, array $wasChanged = []): void
    {
        // Fire paymentSuccess only on the pending → paid transition.
        // We accept an explicit $wasChanged array because by the time
        // afterCommit runs, $order->wasChanged() may have been cleared
        // by syncOriginal() during the parent transaction commit.
        $statusChanged = array_key_exists('status', $wasChanged) || $order->wasChanged('status');
        if ($statusChanged && $order->status === 'paid') {
            AdminNotification::paymentSuccess($order);
        }
    }

    private function onUserCreated(User $user): void
    {
        AdminNotification::newUser($user);
    }

    private function onPhotographerCreated(PhotographerProfile $profile): void
    {
        AdminNotification::newPhotographer($profile);
    }

    private function onPhotographerUpdated(PhotographerProfile $profile, array $wasChanged = []): void
    {
        // If a photographer flips their account into pending review (e.g.
        // submitted onboarding for the first time), nudge admins.
        $statusChanged = array_key_exists('status', $wasChanged) || $profile->wasChanged('status');
        if ($statusChanged && $profile->status === 'pending') {
            AdminNotification::notify(
                'photographer',
                'ช่างภาพรอการอนุมัติ',
                ($profile->display_name ?? 'Unknown') . ' ส่งข้อมูลขออนุมัติ',
                'admin/photographers',
                (string) $profile->id
            );
        }
    }

    private function onContactCreated(ContactMessage $msg): void
    {
        AdminNotification::newContact($msg);
    }

    private function onReviewCreated(Review $review): void
    {
        AdminNotification::newReview($review);
    }

    private function onSlipCreated(PaymentSlip $slip): void
    {
        if (!$slip->order) {
            return;
        }
        AdminNotification::newSlip($slip->order, $slip);
    }

    private function onSlipUpdated(PaymentSlip $slip, array $wasChanged = []): void
    {
        // When a slip moves to verified, mark the matching slip notifications
        // as read so the bell counter doesn't keep showing stale entries.
        // ref_id matches what newSlip stores — i.e. the order id, not the
        // slip id. See AdminNotification::newSlip for the rationale.
        $verifyChanged = array_key_exists('verify_status', $wasChanged) || $slip->wasChanged('verify_status');
        if ($verifyChanged && $slip->verify_status === 'verified') {
            AdminNotification::markReadByRef('slip', (string) $slip->order_id);
        }
    }
}
