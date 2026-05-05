<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentRefund;
use App\Models\RefundRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RefundService — customer-facing refund workflow.
 *
 * Distinct from direct admin-only PaymentRefund creation: this handles the
 * pre-refund request / review / approval lifecycle.
 */
class RefundService
{
    /**
     * Window (days) during which a customer may request a refund after payment.
     */
    private const REFUND_WINDOW_DAYS = 7;

    public function __construct(private readonly MailService $mail)
    {
    }

    /**
     * Create a new refund request on behalf of the customer.
     *
     * Expected $data keys: requested_amount, reason, description, attachments
     */
    public function createRequest(Order $order, array $data): RefundRequest
    {
        return DB::transaction(function () use ($order, $data) {
            $request = RefundRequest::create([
                'request_number'   => RefundRequest::generateRequestNumber(),
                'order_id'         => $order->id,
                'user_id'          => $order->user_id,
                'requested_amount' => (float) ($data['requested_amount'] ?? $order->total),
                'reason'           => $data['reason'] ?? 'other',
                'description'      => $data['description'] ?? null,
                'attachments'      => $data['attachments'] ?? [],
                'status'           => 'pending',
            ]);

            // Notify the customer (best-effort)
            $this->sendReceivedEmail($request, $order);

            // Notify admins so a refund request shows up in the bell
            // dropdown immediately. Deferred to afterCommit so a failed
            // RefundRequest insert doesn't leave a phantom notification.
            // refundRequest() helper exists on the model but was never
            // wired up — this is the missing call.
            DB::afterCommit(function () use ($order, $request) {
                try {
                    \App\Models\AdminNotification::refundRequest($order, (float) $request->requested_amount);
                } catch (\Throwable $e) {
                    Log::warning('Refund admin notification failed: ' . $e->getMessage());
                }
            });

            return $request;
        });
    }

    /**
     * Approve a request and trigger the real refund through PaymentRefund.
     */
    public function approve(
        RefundRequest $request,
        ?float $approvedAmount = null,
        int $adminId = 0,
        string $adminNote = ''
    ): bool {
        if (!$request->canBeReviewed()) {
            return false;
        }

        $amount = $approvedAmount !== null
            ? round($approvedAmount, 2)
            : (float) $request->requested_amount;

        try {
            DB::transaction(function () use ($request, $amount, $adminId, $adminNote) {
                // Create the actual PaymentRefund record
                $refund = PaymentRefund::create([
                    'order_id'       => $request->order_id,
                    'user_id'        => $request->user_id,
                    'amount'         => $amount,
                    'reason'         => $request->reason_label ?? $request->reason,
                    'status'         => 'approved',
                    'requested_by'   => $request->user_id,
                    'approved_by'    => $adminId ?: null,
                    'approved_at'    => now(),
                    'note'           => $adminNote,
                ]);

                $request->update([
                    'status'                => 'approved',
                    'approved_amount'       => $amount,
                    'admin_note'            => $adminNote,
                    'reviewed_by_admin_id'  => $adminId ?: null,
                    'reviewed_at'           => now(),
                    'resolved_at'           => now(),
                    'payment_refund_id'     => $refund->id,
                ]);

                // ── Subscription-specific refund handling ──────────────────
                // When the underlying Order is a subscription, refunding the
                // payment must ALSO revoke the photographer's plan access.
                // Without this hook the photographer keeps Pro/Studio
                // privileges after the refund — the audit trail showed
                // money returned but the entitlement column on the profile
                // still pointed at the paid plan. Detected via
                // order.order_type = 'subscription' (the RefundService is
                // generic enough to handle non-sub orders too).
                $order = \App\Models\Order::find($request->order_id);
                if ($order && $order->order_type === \App\Models\Order::TYPE_SUBSCRIPTION
                    && $order->subscription_invoice_id) {
                    $invoice = \App\Models\SubscriptionInvoice::find($order->subscription_invoice_id);
                    if ($invoice) {
                        $invoice->update([
                            'status' => \App\Models\SubscriptionInvoice::STATUS_REFUNDED,
                        ]);
                        $sub = $invoice->subscription;
                        if ($sub && $sub->isUsable()) {
                            $oldPlanId = $sub->plan_id;
                            // Cancel the paid sub immediately + drop to free.
                            // Use the centralised cancel(immediate=true) so all
                            // the existing side-effects (profile cache flip,
                            // history record, lifecycle notifier hook) fire
                            // through the same code path.
                            $svc = app(\App\Services\SubscriptionService::class);
                            $svc->cancel($sub, immediate: true);

                            // Override the cancel-sourced history row with a
                            // refund-specific entry so admins searching for
                            // "why did they lose access?" see the refund as
                            // the proximate cause, not "user cancelled".
                            $svc->recordHistory(
                                $sub->fresh(),
                                \App\Models\SubscriptionHistory::EVT_REFUNDED,
                                fromPlanId: $oldPlanId,
                                toPlanId:   null,
                                amount:     $amount,
                                metadata: [
                                    'refund_request_id' => $request->id,
                                    'payment_refund_id' => $refund->id,
                                    'invoice_id'        => $invoice->id,
                                    'order_id'          => $order->id,
                                    'admin_note'        => $adminNote,
                                ],
                                triggeredBy: \App\Models\SubscriptionHistory::TRIG_ADMIN,
                                triggeredById: $adminId ?: null,
                            );
                        }
                    }
                }
            });

            $fresh = $request->fresh(['order', 'user']);
            $this->sendApprovedEmail($fresh);

            // In-app bell notification for the customer — was missing
            // before; the helper existed (`UserNotification::refundProcessed`)
            // but was never wired up.
            try {
                if ($fresh && $fresh->order && $fresh->user_id) {
                    \App\Models\UserNotification::refundProcessed(
                        $fresh->user_id, $fresh->order, (float) $amount, 'approved'
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('refund.approved_user_notify_failed: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('RefundService::approve failed [' . $request->id . ']: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject a refund request.
     */
    public function reject(RefundRequest $request, int $adminId, string $reason): bool
    {
        if (!$request->canBeReviewed()) {
            return false;
        }

        try {
            $request->update([
                'status'                => 'rejected',
                'rejection_reason'      => $reason,
                'reviewed_by_admin_id'  => $adminId ?: null,
                'reviewed_at'           => now(),
                'resolved_at'           => now(),
            ]);

            $fresh = $request->fresh(['order', 'user']);
            $this->sendRejectedEmail($fresh, $reason);

            // In-app bell notification — missing before, now wired.
            try {
                if ($fresh && $fresh->order && $fresh->user_id) {
                    \App\Models\UserNotification::refundProcessed(
                        $fresh->user_id, $fresh->order, (float) $request->requested_amount, 'rejected'
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('refund.rejected_user_notify_failed: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('RefundService::reject failed [' . $request->id . ']: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Customer-initiated cancellation (while still under review).
     */
    public function cancel(RefundRequest $request): bool
    {
        if (!$request->canBeCancelledByUser()) {
            return false;
        }

        try {
            $request->update([
                'status'      => 'cancelled',
                'resolved_at' => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('RefundService::cancel failed [' . $request->id . ']: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Decide whether the given order is eligible for a new refund request.
     *
     * @return array{allowed:bool, reason:string}
     */
    public function canRequestRefund(Order $order): array
    {
        // Must be paid
        if ($order->status !== 'paid') {
            return ['allowed' => false, 'reason' => 'คำสั่งซื้อนี้ยังไม่ได้ชำระเงิน ไม่สามารถขอคืนเงินได้'];
        }

        // Must be within the refund window
        $paidAt = $order->paid_at ?? $order->updated_at ?? $order->created_at;
        if ($paidAt && now()->diffInDays($paidAt) > self::REFUND_WINDOW_DAYS) {
            return [
                'allowed' => false,
                'reason'  => 'คำสั่งซื้อนี้อยู่นอกช่วงเวลาขอคืนเงิน (' . self::REFUND_WINDOW_DAYS . ' วัน)',
            ];
        }

        // No existing active request
        $existing = RefundRequest::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'under_review', 'approved', 'processing'])
            ->exists();
        if ($existing) {
            return ['allowed' => false, 'reason' => 'มีคำขอคืนเงินที่กำลังดำเนินการอยู่แล้ว'];
        }

        // Not already refunded
        $alreadyRefunded = PaymentRefund::where('order_id', $order->id)
            ->whereIn('status', ['approved', 'processed', 'completed'])
            ->exists();
        if ($alreadyRefunded) {
            return ['allowed' => false, 'reason' => 'คำสั่งซื้อนี้ได้รับการคืนเงินไปแล้ว'];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /* ──────────────────────────── Private helpers ──────────────────────────── */

    private function sendReceivedEmail(RefundRequest $request, Order $order): void
    {
        try {
            $user  = $request->user()->first();
            $email = $user->email ?? $order->email ?? null;
            if (!$email) {
                return;
            }
            $name = $user
                ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'ลูกค้า'
                : 'ลูกค้า';

            $this->mail->refundRequestReceived($email, $name, [
                'request_number'   => $request->request_number,
                'order_number'     => $order->order_number ?? $order->id,
                'requested_amount' => (float) $request->requested_amount,
                'reason'           => $request->reason_label,
                'description'      => $request->description,
                'created_at'       => $request->created_at?->format('d/m/Y H:i'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('RefundService send received email failed: ' . $e->getMessage());
        }
    }

    private function sendApprovedEmail(?RefundRequest $request): void
    {
        if (!$request) {
            return;
        }

        try {
            $user  = $request->user;
            $email = $user->email ?? $request->order->email ?? null;
            if (!$email) {
                return;
            }
            $name = $user
                ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'ลูกค้า'
                : 'ลูกค้า';

            $this->mail->refundApproved($email, $name, [
                'request_number'  => $request->request_number,
                'order_number'    => $request->order->order_number ?? $request->order_id,
                'approved_amount' => (float) $request->approved_amount,
                'admin_note'      => $request->admin_note,
                'approved_at'     => $request->reviewed_at?->format('d/m/Y H:i'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('RefundService send approved email failed: ' . $e->getMessage());
        }
    }

    private function sendRejectedEmail(?RefundRequest $request, string $reason): void
    {
        if (!$request) {
            return;
        }

        try {
            $user  = $request->user;
            $email = $user->email ?? $request->order->email ?? null;
            if (!$email) {
                return;
            }
            $name = $user
                ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'ลูกค้า'
                : 'ลูกค้า';

            $this->mail->refundRejected($email, $name, [
                'request_number'   => $request->request_number,
                'order_number'     => $request->order->order_number ?? $request->order_id,
                'requested_amount' => (float) $request->requested_amount,
                'rejected_at'      => $request->reviewed_at?->format('d/m/Y H:i'),
            ], $reason);
        } catch (\Throwable $e) {
            Log::warning('RefundService send rejected email failed: ' . $e->getMessage());
        }
    }
}
