<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PhotographerDisbursement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PaymentWebhookController extends Controller
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Write a row to payment_audit_log.
     * Schema: id, transaction_id, order_id, action, actor_type, actor_id,
     *         ip_address, old_values, new_values, signature, created_at
     */
    private function auditLog(Request $request, string $action, ?string $transactionId = null, ?int $orderId = null, array $extra = []): void
    {
        try {
            DB::table('payment_audit_log')->insert([
                'transaction_id' => $transactionId,
                'order_id'       => $orderId,
                'action'         => $action,
                'actor_type'     => 'webhook',
                'actor_id'       => null,
                'ip_address'     => $request->ip(),
                'old_values'     => null,
                'new_values'     => json_encode(array_merge(['payload' => $request->all()], $extra), JSON_UNESCAPED_UNICODE),
                'signature'      => $request->header('Stripe-Signature')
                                    ?? $request->header('X-Omise-Signature')
                                    ?? $request->header('PAYPAL-TRANSMISSION-SIG')
                                    ?? $request->header('X-LINE-Authorization')
                                    ?? null,
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('PaymentWebhook: audit log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update an order's status and log to payment_logs.
     *
     * When status flips to 'paid' we also branch on order_type:
     *  • photo_package  → leave the existing delivery path alone (handled
     *    either by the Omise-specific completeOmiseCharge flow, or by the
     *    /payment/success page triggering PhotoDeliveryService on first
     *    poll for non-push gateways).
     *  • credit_package → hand off to CreditService::issueFromPaidOrder()
     *    so the photographer's credit bundle + ledger row get written
     *    regardless of which gateway came in. Idempotent: the service
     *    early-returns if a bundle already exists for this order.
     *  • subscription  → hand off to SubscriptionService::activateFromPaidInvoice()
     *    which flips the PhotographerSubscription to active, extends the
     *    period, and refreshes the denormalised profile cache (storage
     *    quota, plan code). Also idempotent.
     */
    private function updateOrderStatus(int $orderId, string $status, string $note = ''): bool
    {
        try {
            // Route through OrderStateMachine when transitioning to 'paid' —
            // it wraps lockForUpdate + ALLOWED_TRANSITIONS + activity log
            // + idempotency. Webhook retries hitting an already-paid order
            // return false silently (no double-fulfill).
            //
            // Other statuses (failed, cancelled, refunded) keep the legacy
            // direct update — they don't need the same protection.
            if ($status === 'paid') {
                $sm = app(\App\Services\Payment\OrderStateMachine::class);
                try {
                    $changed = $sm->transitionToPaid(
                        orderId:        $orderId,
                        idempotencyKey: "webhook.order.{$orderId}.paid",
                        auditContext:   ['source' => 'webhook', 'note' => $note],
                    );
                    if ($changed) {
                        DB::table('payment_logs')->insert([
                            'order_id'   => $orderId,
                            'event_type' => "webhook_status_{$status}",
                            'note'       => $note,
                            'created_at' => now(),
                        ]);
                        $this->dispatchPaidOrderSideEffects($orderId);
                    }
                    return true;     // both first-write and idempotent retry are "success"
                } catch (\DomainException $e) {
                    // Order in unexpected state (e.g. cancelled, refunded) —
                    // refusing the transition is correct. Log + return false.
                    Log::warning('PaymentWebhook: paid-transition refused by state machine', [
                        'order_id' => $orderId,
                        'reason'   => $e->getMessage(),
                    ]);
                    return false;
                }
            }

            $updated = DB::table('orders')
                ->where('id', $orderId)
                ->update(['status' => $status, 'updated_at' => now()]);

            if ($updated) {
                DB::table('payment_logs')->insert([
                    'order_id'   => $orderId,
                    'event_type' => "webhook_status_{$status}",
                    'note'       => $note,
                    'created_at' => now(),
                ]);
            }

            return $updated > 0;
        } catch (\Throwable $e) {
            Log::error('PaymentWebhook: updateOrderStatus failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Route a just-paid order to the right post-payment handler.
     *
     * Kept as its own method so the Omise-specific completion path can reuse
     * it without duplicating the order_type check. Swallows all exceptions
     * and logs — webhook retries should not compound on application errors
     * in side effects (the order is already flagged paid, a retry would
     * just re-issue credits and the idempotency guard would kick in anyway).
     */
    private function dispatchPaidOrderSideEffects(int $orderId): void
    {
        try {
            $order = \App\Models\Order::find($orderId);
            if (!$order) return;

            // Delegate to OrderFulfillmentService which knows how to route
            // photo_package / credit_package / subscription orders to their
            // respective post-payment handlers. Keeping this dispatch logic
            // in one place prevents drift between gateway webhooks and slip
            // approval paths (all of which need the same branching).
            //
            // NOTE: for photo_package orders the Omise webhook still runs
            // PhotoDeliveryService directly from completeOmiseCharge() so
            // that the status-page polling path (which bypasses this hook)
            // still works on non-push gateways. Subscription + credit_package
            // orders only need this hook.
            if ($order->isCreditPackageOrder() || $order->isSubscriptionOrder()) {
                app(\App\Services\OrderFulfillmentService::class)->fulfill($order);
            }
        } catch (\Throwable $e) {
            Log::error('PaymentWebhook: paid-order side effects failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Stripe
    // -------------------------------------------------------------------------

    public function stripe(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = \App\Services\Payment\StripeGateway::webhookSecret();

        // Verify signature if secret is configured
        if ($secret && $sigHeader) {
            try {
                // Manual HMAC-SHA256 verification matching Stripe's format:
                // t=<timestamp>,v1=<signature>
                $parts    = [];
                foreach (explode(',', $sigHeader) as $part) {
                    [$k, $v]   = explode('=', $part, 2);
                    $parts[$k] = $v;
                }
                $timestamp        = $parts['t'] ?? 0;
                $expectedSig      = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
                $receivedSig      = $parts['v1'] ?? '';

                if (!hash_equals($expectedSig, $receivedSig)) {
                    Log::warning('Stripe webhook: invalid signature');
                    $this->auditLog($request, 'stripe_signature_failure');
                    // Notify admin so a forged or misconfigured webhook
                    // doesn't go unnoticed. The helper throttles per
                    // (provider, ip) so retries don't spam.
                    try {
                        \App\Models\AdminNotification::webhookFailure(
                            'stripe',
                            'Invalid signature from ' . $request->ip(),
                            $request->ip()
                        );
                    } catch (\Throwable $e) {}
                    return response()->json(['error' => 'Invalid signature'], 400);
                }
            } catch (\Throwable $e) {
                Log::error('Stripe webhook: signature check error', ['error' => $e->getMessage()]);
            }
        }

        $event     = $request->all();
        $eventType = $event['type'] ?? 'unknown';

        $this->auditLog($request, "stripe_{$eventType}");

        // Handle common Stripe event types
        switch ($eventType) {
            case 'payment_intent.succeeded':
                $pi          = $event['data']['object'] ?? [];
                $txnId       = $pi['id'] ?? null;
                $metadata    = $pi['metadata'] ?? [];
                $orderId     = isset($metadata['order_id']) ? (int) $metadata['order_id'] : null;

                if ($orderId) {
                    $this->updateOrderStatus($orderId, 'paid', "Stripe PaymentIntent succeeded: {$txnId}");
                }
                break;

            case 'payment_intent.payment_failed':
                $pi       = $event['data']['object'] ?? [];
                $txnId    = $pi['id'] ?? null;
                $metadata = $pi['metadata'] ?? [];
                $orderId  = isset($metadata['order_id']) ? (int) $metadata['order_id'] : null;

                if ($orderId) {
                    $this->updateOrderStatus($orderId, 'failed', "Stripe PaymentIntent failed: {$txnId}");
                }
                break;

            case 'charge.refunded':
                $charge   = $event['data']['object'] ?? [];
                $metadata = $charge['metadata'] ?? [];
                $orderId  = isset($metadata['order_id']) ? (int) $metadata['order_id'] : null;

                if ($orderId) {
                    $this->updateOrderStatus($orderId, 'refunded', 'Stripe charge refunded');
                }
                break;

            default:
                Log::info("Stripe webhook: unhandled event type [{$eventType}]");
        }

        return response()->json(['received' => true]);
    }

    // -------------------------------------------------------------------------
    // Omise — charge webhook (credit card / internet banking)
    // -------------------------------------------------------------------------
    //
    // End-to-end flow for Omise-paid orders:
    //   1. Customer picks Omise at /payment/checkout/{order}
    //   2. PaymentService::processPayment creates a PaymentTransaction (status
    //      = pending) and OmiseGateway::initiate POSTs to /charges, attaching
    //      our internal transaction_id + order_id to metadata so we can match
    //      this charge back later
    //   3. Customer is redirected to Omise's authorize_uri, pays, comes back
    //      to /payment/success
    //   4. Omise fires `charge.complete` to THIS endpoint (usually within a
    //      second or two, but can lag — status page polling covers the gap)
    //   5. We verify signature (opt-in), flip the transaction to completed
    //      + order to paid, and hand off to PhotoDeliveryService to create
    //      DownloadToken rows + push LINE/email link if configured
    //   6. Customer's status page sees status=paid on the next poll tick and
    //      renders the "ดาวน์โหลดรูปทั้งหมด" CTA — no manual admin step
    //
    // Idempotency: Omise retries on non-2xx for 3 days. completeTransaction
    // short-circuits on already-completed txns, and PhotoDeliveryService's
    // ensureDownloadTokens() checks for existing tokens before inserting, so
    // repeat deliveries are safe.

    public function omise(Request $request)
    {
        // ── Signature verification (opt-in) ──
        //
        // Omise signs webhooks with HMAC-SHA256 of the raw body when an
        // endpoint secret is configured in their dashboard; header name is
        // `X-Omise-Key-Hash`. We only verify when `omise_webhook_secret` is
        // set so local dev can still receive test events, but production
        // admins should always fill in the secret — otherwise any attacker
        // who can reach this URL can mark orders paid.
        $secret = AppSetting::get('omise_webhook_secret', '');

        // SECURITY: signature verification is REQUIRED in production.
        // Previously this was opt-in — if the admin forgot to set
        // omise_webhook_secret, anyone who could reach this URL could
        // forge `charge.complete` payloads and mark orders paid.
        // Now: missing secret in production → return 503 Service
        // Unavailable + admin alert. Local/staging still permit
        // unsigned webhooks for development convenience.
        if (!$secret && app()->environment('production')) {
            Log::error('Omise webhook: signature secret not configured in production');
            try {
                \App\Models\AdminNotification::webhookFailure(
                    'omise',
                    'omise_webhook_secret is not set — webhook rejected. Configure it under /admin/settings/webhooks.',
                    'config-missing'
                );
            } catch (\Throwable $e) {}
            return response()->json([
                'error' => 'Webhook secret not configured',
            ], 503);
        }

        if ($secret) {
            $received = $request->header('X-Omise-Key-Hash', '');
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            if (!$received || !hash_equals($expected, $received)) {
                Log::warning('Omise webhook: invalid signature', ['ip' => $request->ip()]);
                $this->auditLog($request, 'omise_signature_failure');
                try {
                    \App\Models\AdminNotification::webhookFailure(
                        'omise',
                        'Invalid signature from ' . $request->ip(),
                        $request->ip()
                    );
                } catch (\Throwable $e) {}
                return response()->json(['error' => 'Invalid signature'], 400);
            }
        }

        $event     = $request->all();
        $eventKey  = $event['key'] ?? 'unknown';
        $object    = $event['data'] ?? [];
        $chargeId  = $object['id'] ?? null;
        $metadata  = $object['metadata'] ?? [];
        $txnId     = $metadata['transaction_id'] ?? null;        // our TXN-XXX id
        $orderId   = isset($metadata['order_id']) ? (int) $metadata['order_id'] : null;

        $this->auditLog($request, "omise_{$eventKey}", $txnId, $orderId);

        // Defensive: Omise transfer events must hit /webhooks/omise-transfer,
        // not here. If someone pasted the wrong URL into the dashboard, log
        // loudly and 200 so Omise doesn't retry storm — but don't run charge
        // handling on a transfer payload.
        if (str_starts_with($eventKey, 'transfer.')) {
            Log::warning("Omise charge webhook: received transfer event [{$eventKey}]. Admin should move this URL to /api/webhooks/omise-transfer");
            return response()->json(['received' => true]);
        }

        switch ($eventKey) {
            case 'charge.complete':
                $status = $object['status'] ?? null;
                if ($status === 'successful') {
                    $this->completeOmiseCharge($txnId, $orderId, $chargeId);
                } elseif (in_array($status, ['failed', 'expired'], true)) {
                    if ($orderId) {
                        $this->updateOrderStatus($orderId, 'failed', "Omise charge {$status}: {$chargeId}");
                    }
                    $this->failOmiseTransaction($txnId, "omise_charge_{$status}");
                }
                break;

            case 'refund.create':
                if ($orderId) {
                    $this->updateOrderStatus($orderId, 'refunded', "Omise refund created for charge: {$chargeId}");
                }
                break;

            default:
                Log::info("Omise webhook: unhandled event [{$eventKey}]");
        }

        return response()->json(['received' => true]);
    }

    /**
     * Mark the matching PaymentTransaction + Order as paid and kick off
     * photo delivery (DownloadToken creation + LINE/email push).
     *
     * Match priority:
     *   1. metadata.transaction_id  → our internal TXN-XXX (set by OmiseGateway)
     *   2. fallback: latest pending Omise PaymentTransaction on the order
     *      (covers Omise dashboard "send test event" where metadata is absent)
     */
    private function completeOmiseCharge(?string $txnId, ?int $orderId, ?string $chargeId): void
    {
        try {
            $transaction = null;
            if ($txnId) {
                $transaction = \App\Models\PaymentTransaction::where('transaction_id', $txnId)->first();
            }
            if (!$transaction && $orderId) {
                $transaction = \App\Models\PaymentTransaction::where('order_id', $orderId)
                    ->where('payment_gateway', 'omise')
                    ->orderByDesc('id')
                    ->first();
            }

            if (!$transaction) {
                Log::warning('Omise webhook: charge.complete but no PaymentTransaction matched', [
                    'txn_id' => $txnId, 'order_id' => $orderId, 'charge_id' => $chargeId,
                ]);
                return;
            }

            // Idempotent: if already completed, skip the update but still
            // re-run delivery (tokens/email dispatch are themselves idempotent).
            if ($transaction->status !== 'completed') {
                \App\Services\Payment\PaymentService::completeTransaction($transaction, $chargeId);
            }

            $order = $transaction->order()->with(['user', 'items', 'event'])->first();
            if (!$order) {
                Log::warning('Omise webhook: transaction has no order', ['txn_id' => $transaction->transaction_id]);
                return;
            }

            // completeTransaction() above already moves the order to 'paid',
            // so here we just kick off the right post-pay handler.
            // OrderFulfillmentService internally branches on order_type:
            //   photo_package  → PhotoDeliveryService::deliver
            //   credit_package → CreditService::issueFromPaidOrder
            //   subscription   → SubscriptionService::activateFromPaidInvoice
            // All three are idempotent, so webhook retries land safely.
            app(\App\Services\OrderFulfillmentService::class)->fulfill($order);

            // Audit trail for admin visibility (payment_logs is the
            // human-readable timeline on the admin order page).
            try {
                DB::table('payment_logs')->insert([
                    'order_id'   => $order->id,
                    'event_type' => 'webhook_omise_charge_complete',
                    'note'       => "charge_id={$chargeId} txn={$transaction->transaction_id}",
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Omise webhook: payment_logs insert failed: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error('Omise webhook: completeOmiseCharge failed: ' . $e->getMessage());
        }
    }

    /**
     * Flip a PaymentTransaction to failed. Used when Omise reports a
     * charge.complete with status=failed/expired.
     */
    private function failOmiseTransaction(?string $txnId, string $reason): void
    {
        if (!$txnId) return;
        try {
            $transaction = \App\Models\PaymentTransaction::where('transaction_id', $txnId)->first();
            if ($transaction && $transaction->status !== 'completed') {
                \App\Services\Payment\PaymentService::failTransaction($transaction, $reason);
            }
        } catch (\Throwable $e) {
            Log::error('Omise webhook: failOmiseTransaction failed: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Omise — Transfer settlement webhook (payouts, NOT charges)
    // -------------------------------------------------------------------------
    //
    // Omise confirms PromptPay transfers out-of-band: the initial POST to
    // /transfers returns 200 with `sent=true` but the money only actually
    // lands minutes later, and the provider fires a webhook event of type
    // `transfer.*` when it settles (or flips to failed). This handler is
    // what turns the disbursement row from 'processing' → 'succeeded' or
    // 'failed' when that happens.
    //
    // Matching strategy: `data.id` from the event payload must equal
    // `photographer_disbursements.provider_txn_id` (stamped by the initial
    // transfer() call in OmisePayoutProvider). If no disbursement matches
    // we 200 back — Omise retries for 3 days on non-2xx, and we'd rather
    // swallow an unknown txn than build a retry storm.

    public function omiseTransfer(Request $request)
    {
        // ── Signature verification (opt-in) ──
        //
        // Omise doesn't sign transfer webhooks by default, but lets you set
        // a shared secret per webhook endpoint in their dashboard; it arrives
        // as `X-Omise-Key-Hash` header (HMAC-SHA256 of the raw body). We
        // check it only if `omise_webhook_secret` is configured — makes the
        // endpoint usable out-of-the-box for dev while still lockable in prod.
        $secret = AppSetting::get('omise_webhook_secret', '');

        // SECURITY: signature is REQUIRED in production for transfer
        // webhooks too — they fire on disbursement state changes which
        // can affect photographer payouts. Without it a forged event
        // could mark a payout 'failed' or 'succeeded' incorrectly.
        if (!$secret && app()->environment('production')) {
            Log::error('Omise transfer webhook: signature secret not configured in production');
            try {
                \App\Models\AdminNotification::webhookFailure(
                    'omise.transfer',
                    'omise_webhook_secret is not set — transfer webhook rejected.',
                    'config-missing'
                );
            } catch (\Throwable $e) {}
            return response()->json(['error' => 'Webhook secret not configured'], 503);
        }

        if ($secret) {
            $received = $request->header('X-Omise-Key-Hash', '');
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            if (!$received || !hash_equals($expected, $received)) {
                Log::warning('Omise transfer webhook: invalid signature');
                $this->auditLog($request, 'omise_transfer_signature_failure');
                try {
                    \App\Models\AdminNotification::webhookFailure(
                        'omise.transfer',
                        'Invalid signature from ' . $request->ip(),
                        $request->ip()
                    );
                } catch (\Throwable $e) {}
                return response()->json(['error' => 'Invalid signature'], 400);
            }
        }

        $event       = $request->all();
        $eventKey    = $event['key'] ?? 'unknown';    // e.g. "transfer.complete"
        $object      = $event['data'] ?? [];
        $transferId  = $object['id'] ?? null;         // "trsf_test_5abc…"
        $status      = $object['status'] ?? null;     // pending/sent/paid/failed
        $sent        = $object['sent']        ?? false;
        $paid        = $object['paid']        ?? false;
        $failureCode = $object['failure_code'] ?? null;
        $failureMsg  = $object['failure_message'] ?? null;

        $this->auditLog($request, "omise_transfer_{$eventKey}", $transferId);

        // Only handle transfer.* events; ignore anything else that hits this
        // endpoint by mistake (e.g. admin copy-pasted the URL into the charge
        // webhook config).
        if (!str_starts_with($eventKey, 'transfer.')) {
            Log::info("Omise transfer webhook: non-transfer event ignored [{$eventKey}]");
            return response()->json(['received' => true]);
        }

        if (!$transferId) {
            Log::warning('Omise transfer webhook: missing transfer id', ['event' => $eventKey]);
            return response()->json(['received' => true, 'warning' => 'missing transfer id']);
        }

        // Match the disbursement. Exact match on provider_txn_id — we stamped
        // this at the initial transfer() call in OmisePayoutProvider.
        $disbursement = PhotographerDisbursement::where('provider_txn_id', $transferId)
            ->where('provider', 'omise')
            ->first();

        if (!$disbursement) {
            Log::warning('Omise transfer webhook: no disbursement matched', [
                'transfer_id' => $transferId,
                'event'       => $eventKey,
            ]);
            // 200 so Omise stops retrying — this transfer is not ours.
            return response()->json(['received' => true, 'warning' => 'unknown transfer']);
        }

        // Idempotency — Omise retries on 5xx and may fire duplicate events
        // on transient network issues. A settled disbursement is immutable.
        if ($disbursement->isTerminal()) {
            Log::debug('Omise transfer webhook: disbursement already terminal', [
                'disbursement_id' => $disbursement->id,
                'status'          => $disbursement->status,
                'event'           => $eventKey,
            ]);
            return response()->json(['received' => true, 'note' => 'already terminal']);
        }

        // Decide what state change the event represents.
        //   transfer.complete  → look at paid/failed flags to decide
        //   transfer.fail      → definitely failed
        //   transfer.send      → accepted-not-yet-paid (stay processing)
        //   transfer.pending   → still enroute (stay processing)
        $isFailure = ($eventKey === 'transfer.fail')
                     || ($eventKey === 'transfer.complete' && !$paid && ($status === 'failed' || $failureCode));
        $isSuccess = ($eventKey === 'transfer.complete' && $paid)
                     || ($status === 'paid');

        if ($isSuccess) {
            $disbursement->markSucceeded($transferId, $object);
            Log::info('Omise transfer webhook: disbursement settled succeeded', [
                'disbursement_id' => $disbursement->id,
                'transfer_id'     => $transferId,
            ]);
        } elseif ($isFailure) {
            $disbursement->markFailed(
                $failureCode ?: 'omise_transfer_failed',
                $failureMsg  ?: 'Omise reported transfer failure',
                $object,
            );
            Log::warning('Omise transfer webhook: disbursement settled failed', [
                'disbursement_id' => $disbursement->id,
                'transfer_id'     => $transferId,
                'failure_code'    => $failureCode,
            ]);
        } else {
            // In-flight status update (e.g. transfer.send). Record the raw
            // payload for ops visibility but don't change terminal state.
            $disbursement->update(['raw_response' => $object]);
            Log::info('Omise transfer webhook: in-flight update', [
                'disbursement_id' => $disbursement->id,
                'event'           => $eventKey,
                'status'          => $status,
            ]);
        }

        return response()->json(['received' => true]);
    }

    // -------------------------------------------------------------------------
    // SlipOK (Thai bank slip verification)
    // -------------------------------------------------------------------------

    /**
     * SlipOK webhook callback.
     *
     * Hardening notes (vs the original trust-anyone implementation):
     *
     *   1. HMAC signature check (required when `slipok_webhook_secret` is set
     *      in app_settings). The HMAC is computed over the raw body. We always
     *      log the attempt — a missing/invalid signature is a SECURITY EVENT.
     *
     *   2. Match by `slipok_trans_ref` first (the bank-transaction-unique id),
     *      fall back to `reference_code`. The original matcher used only
     *      `reference_code` which can be reused across orders.
     *
     *   3. Idempotency / state-guard: an already-approved or already-rejected
     *      slip is NEVER flipped. The webhook can only act on `pending` rows.
     *      This prevents an attacker (or a buggy retry) from un-doing a manual
     *      admin decision, and it prevents downgrading a verified payment.
     *
     *   4. Cross-checks the amount: if SlipOK says success but the amount is
     *      below the order total (with the configured tolerance), we reject —
     *      we never approve based on the webhook alone for under-paid slips.
     *
     *   5. Old → new values captured in the audit log so disputes are
     *      reconstructable.
     */
    public function slipok(Request $request)
    {
        $rawBody = $request->getContent();
        $data    = $request->all();

        // ── 1. Signature validation (skip-with-warning if not configured) ────
        $secret = (string) AppSetting::get('slipok_webhook_secret', '');
        $sigOk  = true;
        $sigReason = 'no-secret-configured';

        if ($secret !== '') {
            $sigOk = false;
            $sigReason = 'missing-header';

            $providedSig = $request->header('X-Slipok-Signature')
                ?? $request->header('X-SignatureV2')
                ?? $request->header('X-Slipok-Hmac')
                ?? '';

            if ($providedSig !== '') {
                $expected = hash_hmac('sha256', $rawBody, $secret);
                // Some integrations prefix with `sha256=`; strip if present.
                $providedSig = preg_replace('/^sha256=/i', '', trim($providedSig));
                $sigOk = hash_equals($expected, $providedSig);
                $sigReason = $sigOk ? 'valid' : 'mismatch';
            }
        }

        // Still record the attempt — security incidents need an audit trail.
        $refCode = $data['data']['transRef'] ?? ($data['transRef'] ?? null);
        $this->auditLog($request, 'slipok_callback', $refCode, null, [
            'signature_status' => $sigReason,
        ]);

        if (!$sigOk) {
            Log::warning('SlipOK webhook: signature invalid — refusing to process', [
                'reason' => $sigReason,
                'ip'     => $request->ip(),
            ]);
            return response()->json(['received' => false, 'error' => 'invalid_signature'], 401);
        }

        // ── 2. Resolve matching slip (transRef preferred, refCode fallback) ──
        $status    = $data['status'] ?? ($data['data']['status'] ?? null);
        $transRef  = $data['data']['transRef']
                  ?? $data['transRef']
                  ?? null;
        $amount    = (float) ($data['data']['amount'] ?? $data['amount'] ?? 0);

        if (!$transRef && !$refCode) {
            Log::warning('SlipOK webhook: missing both transRef and refCode');
            return response()->json(['received' => true, 'warning' => 'missing_identifier']);
        }

        $slip = null;
        if ($transRef) {
            $slip = \App\Models\PaymentSlip::where('slipok_trans_ref', (string) $transRef)->first();
        }
        if (!$slip && $refCode) {
            $slip = \App\Models\PaymentSlip::where('reference_code', (string) $refCode)->first();
        }

        if (!$slip) {
            Log::warning('SlipOK webhook: no matching slip', [
                'transRef' => $transRef,
                'refCode'  => $refCode,
            ]);
            // Return 200 so SlipOK does not infinitely retry — the slip may
            // simply have been deleted in admin UI. We've logged it.
            return response()->json(['received' => true, 'note' => 'slip_not_found']);
        }

        // ── 3. State-guard: only act on pending slips ────────────────────────
        if ($slip->verify_status !== 'pending') {
            Log::info("SlipOK webhook: slip #{$slip->id} already in terminal state, ignoring", [
                'current_status' => $slip->verify_status,
                'transRef'       => $transRef,
            ]);
            return response()->json([
                'received'       => true,
                'note'           => 'slip_already_terminal',
                'current_status' => $slip->verify_status,
            ]);
        }

        // ── 4. Compute new status with amount cross-check ────────────────────
        $isSuccess = in_array($status, [1, '1', 'success', true, 'approved'], true);
        $newStatus = $isSuccess ? 'approved' : 'rejected';

        // Even if SlipOK says success, refuse approval when the reported
        // amount is materially below the order total. SlipOK can be tricked
        // into success on a real-but-different transfer.
        if ($newStatus === 'approved' && $slip->order_id) {
            $order = \App\Models\Order::find($slip->order_id);
            if ($order && $order->total > 0 && $amount > 0) {
                $tolerancePct = max(0.1, min(5.0, (float) AppSetting::get('slip_amount_tolerance_percent', '1')));
                $minAcceptable = (float) $order->total * (1 - ($tolerancePct / 100));
                if ($amount < $minAcceptable) {
                    Log::warning("SlipOK webhook: amount {$amount} below order total {$order->total} (tolerance {$tolerancePct}%), rejecting", [
                        'slip_id' => $slip->id,
                    ]);
                    $newStatus = 'rejected';
                }
            }
        }

        // ── 5. Persist via Eloquent (fires model events for downstream hooks) ──
        $oldValues = [
            'verify_status' => $slip->verify_status,
            'verified_by'   => $slip->verified_by,
        ];

        $slip->update([
            'verify_status' => $newStatus,
            'verified_at'   => now(),
            'verified_by'   => 'slipok_webhook',
            'note'          => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);

        // Capture before/after on audit log for reconstructability.
        try {
            DB::table('payment_audit_log')->insert([
                'transaction_id' => null,
                'order_id'       => $slip->order_id,
                'action'         => 'slipok_decision_applied',
                'actor_type'     => 'webhook',
                'actor_id'       => null,
                'ip_address'     => $request->ip(),
                'old_values'     => json_encode($oldValues, JSON_UNESCAPED_UNICODE),
                'new_values'     => json_encode([
                    'verify_status' => $newStatus,
                    'amount'        => $amount,
                    'transRef'      => $transRef,
                ], JSON_UNESCAPED_UNICODE),
                'signature'      => null,
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SlipOK webhook: audit insert failed', ['error' => $e->getMessage()]);
        }

        if ($newStatus === 'approved' && $slip->order_id) {
            $this->updateOrderStatus((int) $slip->order_id, 'paid', "SlipOK verified: ref {$transRef}");
        }

        Log::info("SlipOK webhook: slip #{$slip->id} → {$newStatus}", [
            'transRef' => $transRef,
            'amount'   => $amount,
        ]);

        return response()->json(['received' => true, 'status' => $newStatus]);
    }

    // -------------------------------------------------------------------------
    // PayPal
    // -------------------------------------------------------------------------

    public function paypal(Request $request)
    {
        $payload   = $request->getContent();
        $eventType = $request->input('event_type', 'unknown');

        $this->auditLog($request, "paypal_{$eventType}");

        try {
            // ------------------------------------------------------------------
            // Signature verification via PayPal's verify-webhook-signature API
            // ------------------------------------------------------------------
            $webhookId = AppSetting::get('paypal_webhook_id', '');

            if ($webhookId) {
                $transmissionId   = $request->header('PAYPAL-TRANSMISSION-ID', '');
                $transmissionTime = $request->header('PAYPAL-TRANSMISSION-TIME', '');
                $certUrl          = $request->header('PAYPAL-CERT-URL', '');
                $transmissionSig  = $request->header('PAYPAL-TRANSMISSION-SIG', '');
                $authAlgo         = $request->header('PAYPAL-AUTH-ALGO', 'SHA256withRSA');

                if ($transmissionId && $transmissionSig) {
                    $mode      = AppSetting::get('paypal_mode', 'sandbox');
                    $apiBase   = $mode === 'live'
                        ? 'https://api-m.paypal.com'
                        : 'https://api-m.sandbox.paypal.com';

                    // Obtain access token using client credentials
                    $clientId = AppSetting::get('paypal_client_id', '');
                    $secret   = AppSetting::get('paypal_secret', '');

                    $tokenResp = Http::withBasicAuth($clientId, $secret)
                        ->asForm()
                        ->post("{$apiBase}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

                    $accessToken = $tokenResp->json('access_token');

                    if ($accessToken) {
                        $verifyResp = Http::withToken($accessToken)
                            ->post("{$apiBase}/v1/notifications/verify-webhook-signature", [
                                'auth_algo'         => $authAlgo,
                                'cert_url'          => $certUrl,
                                'transmission_id'   => $transmissionId,
                                'transmission_sig'  => $transmissionSig,
                                'transmission_time' => $transmissionTime,
                                'webhook_id'        => $webhookId,
                                'webhook_event'     => $request->all(),
                            ]);

                        $verificationStatus = $verifyResp->json('verification_status', '');

                        if ($verificationStatus !== 'SUCCESS') {
                            Log::warning('PayPal webhook: signature verification failed', [
                                'verification_status' => $verificationStatus,
                                'event_type'          => $eventType,
                            ]);
                            $this->auditLog($request, 'paypal_signature_failure');
                            return response()->json(['error' => 'Invalid signature'], 400);
                        }
                    }
                }
            }

            // ------------------------------------------------------------------
            // Event handling
            // ------------------------------------------------------------------
            $resource   = $request->input('resource', []);
            $orderId    = null;
            $gatewayTxn = null;

            switch ($eventType) {

                case 'PAYMENT.CAPTURE.COMPLETED':
                    // resource is a capture object; custom_id holds our order identifier
                    $gatewayTxn    = $resource['id'] ?? null;                   // capture ID
                    $customId      = $resource['custom_id'] ?? null;            // set at order creation
                    $invoiceId     = $resource['invoice_id'] ?? null;
                    $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id']
                                     ?? $resource['links'][0]['href'] ?? null;  // fallback

                    // Resolve our internal order: prefer custom_id (our order_number),
                    // then try gateway_transaction_id = paypal order ID, then capture ID
                    $txnRecord = null;
                    foreach (array_filter([$customId, $invoiceId]) as $candidate) {
                        $txnRecord = DB::table('payment_transactions')
                            ->join('orders', 'orders.id', '=', 'payment_transactions.order_id')
                            ->where('orders.order_number', $candidate)
                            ->where('payment_transactions.payment_gateway', 'paypal')
                            ->select('payment_transactions.*')
                            ->orderByDesc('payment_transactions.id')
                            ->first();
                        if ($txnRecord) break;
                    }

                    if (!$txnRecord && $gatewayTxn) {
                        $txnRecord = DB::table('payment_transactions')
                            ->where('gateway_transaction_id', $gatewayTxn)
                            ->orderByDesc('id')
                            ->first();
                    }

                    if ($txnRecord) {
                        $orderId = (int) $txnRecord->order_id;

                        DB::table('payment_transactions')
                            ->where('id', $txnRecord->id)
                            ->update([
                                'status'                 => 'completed',
                                'gateway_transaction_id' => $gatewayTxn ?? $txnRecord->gateway_transaction_id,
                                'paid_at'                => now(),
                                'updated_at'             => now(),
                            ]);

                        $this->updateOrderStatus($orderId, 'paid', "PayPal capture completed: {$gatewayTxn}");

                        Log::info('PayPal webhook: capture completed', [
                            'capture_id' => $gatewayTxn,
                            'order_id'   => $orderId,
                        ]);
                    } else {
                        Log::warning('PayPal webhook: no matching transaction for CAPTURE.COMPLETED', [
                            'capture_id' => $gatewayTxn,
                            'custom_id'  => $customId,
                        ]);
                    }
                    break;

                case 'PAYMENT.CAPTURE.DENIED':
                    $gatewayTxn = $resource['id'] ?? null;
                    $customId   = $resource['custom_id'] ?? null;
                    $invoiceId  = $resource['invoice_id'] ?? null;

                    $txnRecord = null;
                    foreach (array_filter([$customId, $invoiceId]) as $candidate) {
                        $txnRecord = DB::table('payment_transactions')
                            ->join('orders', 'orders.id', '=', 'payment_transactions.order_id')
                            ->where('orders.order_number', $candidate)
                            ->where('payment_transactions.payment_gateway', 'paypal')
                            ->select('payment_transactions.*')
                            ->orderByDesc('payment_transactions.id')
                            ->first();
                        if ($txnRecord) break;
                    }

                    if (!$txnRecord && $gatewayTxn) {
                        $txnRecord = DB::table('payment_transactions')
                            ->where('gateway_transaction_id', $gatewayTxn)
                            ->orderByDesc('id')
                            ->first();
                    }

                    if ($txnRecord) {
                        $orderId = (int) $txnRecord->order_id;

                        DB::table('payment_transactions')
                            ->where('id', $txnRecord->id)
                            ->update(['status' => 'failed', 'updated_at' => now()]);

                        $this->updateOrderStatus($orderId, 'cancelled', "PayPal capture denied: {$gatewayTxn}");

                        Log::info('PayPal webhook: capture denied', [
                            'capture_id' => $gatewayTxn,
                            'order_id'   => $orderId,
                        ]);
                    }
                    break;

                case 'PAYMENT.CAPTURE.REFUNDED':
                    $gatewayTxn = $resource['id'] ?? null;   // refund ID
                    // The related capture is in links or supplementary_data
                    $captureId  = $resource['supplementary_data']['related_ids']['capture_id'] ?? null;

                    $txnRecord = null;
                    if ($captureId) {
                        $txnRecord = DB::table('payment_transactions')
                            ->where('gateway_transaction_id', $captureId)
                            ->orderByDesc('id')
                            ->first();
                    }
                    if (!$txnRecord && $gatewayTxn) {
                        $txnRecord = DB::table('payment_transactions')
                            ->where('gateway_transaction_id', $gatewayTxn)
                            ->orderByDesc('id')
                            ->first();
                    }

                    if ($txnRecord) {
                        $orderId = (int) $txnRecord->order_id;

                        DB::table('payment_transactions')
                            ->where('id', $txnRecord->id)
                            ->update(['status' => 'refunded', 'updated_at' => now()]);

                        $this->updateOrderStatus($orderId, 'refunded', "PayPal refunded: {$gatewayTxn}");

                        Log::info('PayPal webhook: capture refunded', [
                            'refund_id'  => $gatewayTxn,
                            'capture_id' => $captureId,
                            'order_id'   => $orderId,
                        ]);
                    }
                    break;

                default:
                    Log::info("PayPal webhook: unhandled event type [{$eventType}]");
            }

        } catch (\Throwable $e) {
            Log::error('PayPal webhook: exception', [
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            // Return 200 so PayPal does not keep retrying on application errors
        }

        return response()->json(['received' => true]);
    }

    // -------------------------------------------------------------------------
    // LINE Pay
    // -------------------------------------------------------------------------

    public function linepay(Request $request)
    {
        // LINE Pay uses a redirect callback (GET with query params), not a push webhook.
        // The flow: LINE Pay redirects the user to our confirmUrl with transactionId in
        // the query string. We then POST to LINE Pay's Confirm API to capture the payment.

        $this->auditLog($request, 'linepay_callback');

        try {
            // Handle cancellation redirect
            if ($request->query('cancel') || $request->input('cancel')) {
                Log::info('LINE Pay: user cancelled payment');

                // Attempt to locate transaction to mark as failed
                $transactionId = $request->query('transactionId') ?? $request->input('transactionId');
                if ($transactionId) {
                    $txnRecord = DB::table('payment_transactions')
                        ->where('gateway_transaction_id', $transactionId)
                        ->where('payment_gateway', 'line_pay')
                        ->orderByDesc('id')
                        ->first();

                    if ($txnRecord) {
                        DB::table('payment_transactions')
                            ->where('id', $txnRecord->id)
                            ->update(['status' => 'failed', 'updated_at' => now()]);

                        $this->updateOrderStatus((int) $txnRecord->order_id, 'cancelled', 'LINE Pay: user cancelled');
                    }
                }

                return response()->json(['received' => true, 'status' => 'cancelled']);
            }

            // LINE Pay sends transactionId as a query parameter on the confirmUrl
            $transactionId = $request->query('transactionId') ?? $request->input('transactionId', '');
            $orderId       = $request->query('orderId')       ?? $request->input('orderId', '');

            if (empty($transactionId)) {
                Log::warning('LINE Pay callback: missing transactionId');
                return response()->json(['received' => true, 'warning' => 'missing transactionId']);
            }

            // Find the matching payment transaction by gateway_transaction_id
            $txnRecord = DB::table('payment_transactions')
                ->where('gateway_transaction_id', $transactionId)
                ->where('payment_gateway', 'line_pay')
                ->orderByDesc('id')
                ->first();

            // If not found by transactionId, try by order_number via orderId param
            if (!$txnRecord && $orderId) {
                $txnRecord = DB::table('payment_transactions')
                    ->join('orders', 'orders.id', '=', 'payment_transactions.order_id')
                    ->where('orders.order_number', $orderId)
                    ->where('payment_transactions.payment_gateway', 'line_pay')
                    ->select('payment_transactions.*')
                    ->orderByDesc('payment_transactions.id')
                    ->first();
            }

            if (!$txnRecord) {
                Log::warning('LINE Pay callback: no transaction found', [
                    'transactionId' => $transactionId,
                    'orderId'       => $orderId,
                ]);
                return response()->json(['received' => true, 'warning' => 'transaction not found']);
            }

            $amount = (float) $txnRecord->amount;

            // ------------------------------------------------------------------
            // Call LINE Pay Confirm API
            // ------------------------------------------------------------------
            $channelId = AppSetting::get('linepay_channel_id', '');
            $secret    = AppSetting::get('linepay_channel_secret', '');
            $isSandbox = AppSetting::get('linepay_sandbox', '1');
            $apiBase   = ($isSandbox === '0' || $isSandbox === 'false')
                ? 'https://api-pay.line.me'
                : 'https://sandbox-api-pay.line.me';

            $endpoint  = "/v3/payments/{$transactionId}/confirm";
            $body      = json_encode(['amount' => $amount, 'currency' => 'THB']);
            $nonce     = bin2hex(random_bytes(16));
            $signature = base64_encode(hash_hmac('sha256', $secret . $endpoint . $body . $nonce, $secret, true));

            $confirmResp = Http::withHeaders([
                'Content-Type'                  => 'application/json',
                'X-LINE-ChannelId'              => $channelId,
                'X-LINE-Authorization-Nonce'    => $nonce,
                'X-LINE-Authorization'          => $signature,
            ])->post($apiBase . $endpoint, ['amount' => $amount, 'currency' => 'THB']);

            $result     = $confirmResp->json();
            $returnCode = $result['returnCode'] ?? 'ERROR';
            $success    = $returnCode === '0000';

            Log::info('LINE Pay: confirm API response', [
                'transactionId' => $transactionId,
                'returnCode'    => $returnCode,
                'returnMessage' => $result['returnMessage'] ?? '',
            ]);

            // ------------------------------------------------------------------
            // Update transaction and order
            // ------------------------------------------------------------------
            $newStatus = $success ? 'completed' : 'failed';

            DB::table('payment_transactions')
                ->where('id', $txnRecord->id)
                ->update(array_merge(
                    ['status' => $newStatus, 'updated_at' => now()],
                    $success ? ['paid_at' => now()] : []
                ));

            if ($success) {
                $this->updateOrderStatus(
                    (int) $txnRecord->order_id,
                    'paid',
                    "LINE Pay confirmed: transactionId {$transactionId}"
                );
                Log::info('LINE Pay: payment confirmed', [
                    'transactionId' => $transactionId,
                    'order_id'      => $txnRecord->order_id,
                ]);
            } else {
                $this->updateOrderStatus(
                    (int) $txnRecord->order_id,
                    'cancelled',
                    "LINE Pay confirm failed [{$returnCode}]: " . ($result['returnMessage'] ?? '')
                );
                Log::warning('LINE Pay: confirm failed', [
                    'transactionId' => $transactionId,
                    'returnCode'    => $returnCode,
                    'returnMessage' => $result['returnMessage'] ?? '',
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('LINE Pay callback: exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['received' => true]);
    }

    // -------------------------------------------------------------------------
    // TrueMoney
    // -------------------------------------------------------------------------

    public function truemoney(Request $request)
    {
        // TrueMoney sends a POST (JSON or form) notification to the callback URL.
        // The payload may come as raw JSON body or as POST form fields.
        $rawBody = $request->getContent();
        $data    = $request->all();

        // Prefer JSON body if the request body is JSON
        if (empty($data) && $rawBody) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $transactionId = $data['transaction_id'] ?? $data['txn_ref'] ?? $data['ref_no'] ?? null;
        $this->auditLog($request, 'truemoney_webhook', $transactionId);

        try {
            // ------------------------------------------------------------------
            // HMAC-SHA256 signature verification
            // ------------------------------------------------------------------
            $secretKey = AppSetting::get('truemoney_secret_key', '');

            if ($secretKey) {
                // TrueMoney includes the signature in the payload under "signature" key.
                // The signed string is the raw body (without the signature field) or a
                // canonical concatenation of fields. We verify using the raw body approach:
                // signature = HMAC-SHA256(secret, rawBody)
                $receivedSig = $data['signature'] ?? $request->header('X-TrueMoney-Signature', '');

                if ($receivedSig) {
                    // Compute expected signature over the raw body (excluding the signature field itself)
                    // Build a body string without the signature field for canonical verification
                    $verifyData = $data;
                    unset($verifyData['signature']);
                    ksort($verifyData);
                    $signableString  = http_build_query($verifyData);
                    $expectedSig     = hash_hmac('sha256', $signableString, $secretKey);

                    if (!hash_equals($expectedSig, strtolower($receivedSig))) {
                        Log::warning('TrueMoney webhook: invalid signature', [
                            'transaction_id' => $transactionId,
                        ]);
                        $this->auditLog($request, 'truemoney_signature_failure');
                        return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
                    }
                }
            }

            // ------------------------------------------------------------------
            // Extract and validate fields
            // ------------------------------------------------------------------
            if (empty($transactionId)) {
                Log::warning('TrueMoney webhook: missing transaction_id', ['data' => $data]);
                return response()->json(['status' => 'ok']); // Acknowledge to avoid retries
            }

            // TrueMoney status: SUCCESS / FAILED / PENDING
            $tmStatus  = strtoupper($data['status'] ?? '');
            $newStatus = $tmStatus === 'SUCCESS' ? 'completed' : ($tmStatus === 'PENDING' ? 'processing' : 'failed');

            // ------------------------------------------------------------------
            // Find the matching transaction record
            // ------------------------------------------------------------------
            $txnRecord = DB::table('payment_transactions')
                ->where('gateway_transaction_id', $transactionId)
                ->where('payment_gateway', 'truemoney')
                ->orderByDesc('id')
                ->first();

            // Fallback: match by order_number if the gateway stores our order ref
            if (!$txnRecord) {
                $orderRef = $data['order_id'] ?? $data['merchant_order_id'] ?? null;
                if ($orderRef) {
                    $txnRecord = DB::table('payment_transactions')
                        ->join('orders', 'orders.id', '=', 'payment_transactions.order_id')
                        ->where('orders.order_number', $orderRef)
                        ->where('payment_transactions.payment_gateway', 'truemoney')
                        ->select('payment_transactions.*')
                        ->orderByDesc('payment_transactions.id')
                        ->first();
                }
            }

            if (!$txnRecord) {
                Log::warning('TrueMoney webhook: no matching transaction', [
                    'transaction_id' => $transactionId,
                    'status'         => $tmStatus,
                ]);
                return response()->json(['status' => 'ok']);
            }

            // ------------------------------------------------------------------
            // Update transaction and order
            // ------------------------------------------------------------------
            DB::table('payment_transactions')
                ->where('id', $txnRecord->id)
                ->update(array_merge(
                    ['status' => $newStatus, 'updated_at' => now()],
                    $newStatus === 'completed' ? ['paid_at' => now()] : []
                ));

            if ($newStatus === 'completed') {
                $this->updateOrderStatus(
                    (int) $txnRecord->order_id,
                    'paid',
                    "TrueMoney payment completed: {$transactionId}"
                );
                Log::info('TrueMoney webhook: payment completed', [
                    'transaction_id' => $transactionId,
                    'order_id'       => $txnRecord->order_id,
                ]);
            } elseif ($newStatus === 'failed') {
                $this->updateOrderStatus(
                    (int) $txnRecord->order_id,
                    'cancelled',
                    "TrueMoney payment failed: {$transactionId}"
                );
                Log::info('TrueMoney webhook: payment failed', [
                    'transaction_id' => $transactionId,
                    'order_id'       => $txnRecord->order_id,
                ]);
            } else {
                Log::info('TrueMoney webhook: payment pending', [
                    'transaction_id' => $transactionId,
                    'order_id'       => $txnRecord->order_id,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('TrueMoney webhook: exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    // -------------------------------------------------------------------------
    // 2C2P
    // -------------------------------------------------------------------------

    public function twoCTwoP(Request $request)
    {
        // 2C2P sends a JWT-encoded payload in the "payload" POST field (both
        // backend server-to-server notification and frontend redirect).
        $jwtPayload = $request->input('payload', '');
        $this->auditLog($request, '2c2p_webhook');

        try {
            if (empty($jwtPayload)) {
                Log::warning('2C2P webhook: missing payload field');
                return response()->json(['status' => 'error', 'message' => 'missing payload'], 400);
            }

            // ------------------------------------------------------------------
            // Decode and verify JWT (HS256 signed with secret key)
            // ------------------------------------------------------------------
            $secretKey = AppSetting::get('2c2p_secret_key', '');

            $decoded = $this->decodeAndVerify2C2PJwt($jwtPayload, $secretKey);

            if ($decoded === null) {
                Log::warning('2C2P webhook: invalid JWT signature or malformed token');
                $this->auditLog($request, '2c2p_signature_failure');
                return response()->json(['status' => 'error', 'message' => 'Invalid JWT'], 400);
            }

            // ------------------------------------------------------------------
            // Extract fields from decoded payload
            // ------------------------------------------------------------------
            $merchantId = $decoded['merchantID']   ?? '';
            $tranRef    = $decoded['tranRef']       ?? ($decoded['invoiceNo'] ?? '');
            $respCode   = $decoded['respCode']      ?? '';
            $respDesc   = $decoded['respDesc']      ?? '';
            $amount     = $decoded['amount']        ?? 0;
            $invoiceNo  = $decoded['invoiceNo']     ?? '';

            // Validate merchant ID matches our config
            $expectedMerchantId = AppSetting::get('2c2p_merchant_id', '');
            if ($expectedMerchantId && $merchantId && $merchantId !== $expectedMerchantId) {
                Log::warning('2C2P webhook: merchant ID mismatch', [
                    'received' => $merchantId,
                    'expected' => $expectedMerchantId,
                ]);
                return response()->json(['status' => 'error', 'message' => 'Merchant ID mismatch'], 400);
            }

            // ------------------------------------------------------------------
            // Map 2C2P response codes to internal status
            // 0000 = success; 0001 = pending; 0002 = rejected/failed;
            // 9999 = system error; any other code = failed
            // ------------------------------------------------------------------
            $newStatus = match ($respCode) {
                '0000'  => 'completed',
                '0001'  => 'processing',
                default => 'failed',
            };

            $orderStatus = match ($newStatus) {
                'completed'  => 'paid',
                'processing' => 'pending_review',
                default      => 'cancelled',
            };

            Log::info('2C2P webhook: decoded payload', [
                'tranRef'    => $tranRef,
                'invoiceNo'  => $invoiceNo,
                'respCode'   => $respCode,
                'respDesc'   => $respDesc,
                'newStatus'  => $newStatus,
            ]);

            // ------------------------------------------------------------------
            // Locate matching transaction
            // ------------------------------------------------------------------
            $txnRecord = null;

            // Primary: match by gateway_transaction_id = tranRef (payment token or transaction ref)
            if ($tranRef) {
                $txnRecord = DB::table('payment_transactions')
                    ->where('gateway_transaction_id', $tranRef)
                    ->where('payment_gateway', '2c2p')
                    ->orderByDesc('id')
                    ->first();
            }

            // Fallback: match by order_number = invoiceNo
            if (!$txnRecord && $invoiceNo) {
                $txnRecord = DB::table('payment_transactions')
                    ->join('orders', 'orders.id', '=', 'payment_transactions.order_id')
                    ->where('orders.order_number', $invoiceNo)
                    ->where('payment_transactions.payment_gateway', '2c2p')
                    ->select('payment_transactions.*')
                    ->orderByDesc('payment_transactions.id')
                    ->first();
            }

            if (!$txnRecord) {
                Log::warning('2C2P webhook: no matching transaction', [
                    'tranRef'   => $tranRef,
                    'invoiceNo' => $invoiceNo,
                ]);
                // Return 200 so 2C2P does not keep retrying
                return response()->json(['status' => 'ok']);
            }

            // ------------------------------------------------------------------
            // Update transaction and order
            // ------------------------------------------------------------------
            DB::table('payment_transactions')
                ->where('id', $txnRecord->id)
                ->update(array_merge(
                    [
                        'status'                 => $newStatus,
                        'gateway_transaction_id' => $tranRef ?: $txnRecord->gateway_transaction_id,
                        'updated_at'             => now(),
                    ],
                    $newStatus === 'completed' ? ['paid_at' => now()] : []
                ));

            $this->updateOrderStatus(
                (int) $txnRecord->order_id,
                $orderStatus,
                "2C2P [{$respCode}] {$respDesc}: tranRef {$tranRef}"
            );

            Log::info('2C2P webhook: order updated', [
                'tranRef'     => $tranRef,
                'order_id'    => $txnRecord->order_id,
                'respCode'    => $respCode,
                'new_status'  => $newStatus,
                'order_status'=> $orderStatus,
            ]);

        } catch (\Throwable $e) {
            Log::error('2C2P webhook: exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    // -------------------------------------------------------------------------
    // 2C2P JWT helper
    // -------------------------------------------------------------------------

    /**
     * Decode and verify a 2C2P HS256 JWT.
     * Returns the decoded payload array on success, or null on failure.
     */
    private function decodeAndVerify2C2PJwt(string $jwt, string $secret): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $bodyB64, $sigB64] = $parts;

        // Recompute signature (Base64Url-encoded HMAC-SHA256)
        $expectedSig = rtrim(strtr(base64_encode(
            hash_hmac('sha256', "{$headerB64}.{$bodyB64}", $secret, true)
        ), '+/', '-_'), '=');

        // Normalize received signature to Base64Url
        $receivedSig = rtrim(strtr($sigB64, '+/', '-_'), '=');

        if (!hash_equals($expectedSig, $receivedSig)) {
            return null;
        }

        // Decode body
        $bodyJson = base64_decode(strtr($bodyB64, '-_', '+/'));
        $payload  = json_decode($bodyJson, true);

        return is_array($payload) ? $payload : null;
    }

    // -------------------------------------------------------------------------
    // LINE Messaging webhook
    //
    // Parses incoming text messages from LINE OA users and creates a
    // ContactMessage row of category='support' so admins see the
    // message in the support inbox + bell. LINE requires a quick 200
    // OK so all heavy lifting is wrapped in try/catch.
    //
    // LINE event payload (per Messaging API):
    //   { events: [{ type: 'message', message: { type: 'text', text: '...' },
    //                source: { userId: 'U...' }, replyToken: '...' }] }
    // -------------------------------------------------------------------------

    public function lineWebhook(Request $request)
    {
        // ── 1. Signature validation ─────────────────────────────────────
        // LINE signs every webhook with HMAC-SHA256(channel_secret, raw_body).
        // Without verifying, any third party can POST forged events to
        // /api/webhooks/line and create fake support tickets, fake
        // images, fake postbacks — denial-of-service via support inbox.
        // The verifier reads channel_secret from app_settings (same place
        // the rest of the LINE config lives).
        $verifier = app(\App\Services\Line\LineSignatureVerifier::class);
        $rawBody  = $request->getContent();
        $sig      = $request->header('X-Line-Signature', $request->header('x-line-signature'));

        // Allow disabling for local dev / smoke tests via app setting.
        // Default IS strict (the audit setting must explicitly opt-out).
        $enforce = (string) \App\Models\AppSetting::get('line_webhook_signature_required', '1') === '1';
        if ($enforce && !$verifier->verify($rawBody, $sig)) {
            Log::warning('LINE webhook: signature rejected', [
                'has_sig' => $sig !== null && $sig !== '',
                'ip'      => $request->ip(),
            ]);
            // Audit the rejection for later forensic review.
            $this->auditLog($request, 'line_signature_failure');
            return response()->json(['error' => 'invalid signature'], 401);
        }

        // ── 2. Audit ────────────────────────────────────────────────────
        $this->auditLog($request, 'line_webhook');

        // ── 3. Per-event idempotent processing ──────────────────────────
        $events = $request->input('events', []);
        $stats  = app(\App\Services\Line\LineWebhookProcessor::class)->processBatch($events);

        Log::info('LINE messaging webhook processed', [
            'events_count' => count($events),
            'stats'        => $stats,
        ]);

        // Always 200 to avoid LINE retrying a delivery we've already
        // claimed via line_inbound_events (would just be marked
        // 'duplicate' on the second pass, but unnecessary work).
        return response()->json(['received' => true] + $stats);
    }

    // -------------------------------------------------------------------------
    // Facebook Messenger webhook
    //
    // GET = handshake verification.
    // POST = inbound message events. We treat each text message as a
    // ContactMessage row, just like LINE above.
    //
    // Payload (per Facebook Graph API):
    //   { object: 'page', entry: [{ messaging: [{ sender: { id: '...' },
    //     message: { text: '...' } }] }] }
    // -------------------------------------------------------------------------

    public function facebookWebhook(Request $request)
    {
        // Facebook sends a GET for hub verification
        if ($request->isMethod('GET')) {
            $challenge = $request->query('hub_challenge');
            $mode      = $request->query('hub_mode');
            $token     = $request->query('hub_verify_token');
            $appToken  = env('FACEBOOK_VERIFY_TOKEN', '');

            if ($mode === 'subscribe' && ($appToken === '' || $token === $appToken)) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }
            return response('Verification failed', 403);
        }

        Log::info('Facebook webhook received', ['object' => $request->input('object')]);

        $entries = $request->input('entry', []);
        foreach ($entries as $entry) {
            foreach (($entry['messaging'] ?? []) as $msg) {
                try {
                    $text = trim((string) ($msg['message']['text'] ?? ''));
                    if ($text === '') continue;
                    $senderId = (string) ($msg['sender']['id'] ?? 'unknown');

                    $this->createSupportInbound(
                        channel: 'facebook',
                        senderId: $senderId,
                        senderName: 'Facebook User ' . substr($senderId, 0, 8),
                        body: $text
                    );
                } catch (\Throwable $e) {
                    Log::warning('Facebook webhook event failed: ' . $e->getMessage());
                }
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * Create a ContactMessage row from an inbound chat-channel message
     * (LINE / Facebook Messenger). Idempotency: each unique
     * (channel, sender_id, message_hash, day) combination only
     * creates one ticket — defends against webhook retries.
     *
     * Admin sees these in /admin/messages and gets a bell notification
     * (the AdminNotificationObserver fires on ContactMessage::created).
     */
    private function createSupportInbound(string $channel, string $senderId, string $senderName, string $body): void
    {
        $hash = sha1($body);
        $today = now()->format('Y-m-d');

        // Idempotency check — same body from same sender same day = no duplicate.
        $existing = \App\Models\ContactMessage::where('email', "{$channel}+{$senderId}@webhook.local")
            ->where('subject', "ข้อความจาก {$channel}")
            ->whereDate('created_at', $today)
            ->where('message', 'ilike', '%' . substr($body, 0, 50) . '%')
            ->first();
        if ($existing) return;

        \App\Models\ContactMessage::create([
            'ticket_number'    => 'CHAT-' . strtoupper(substr($channel, 0, 2))
                                 . '-' . now()->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))),
            'name'             => $senderName,
            // Synthetic email so we have a valid contact identifier;
            // admins can still use the channel+sender_id to reply via
            // each platform's official messaging API later.
            'email'            => "{$channel}+{$senderId}@webhook.local",
            'subject'          => "ข้อความจาก {$channel}",
            'category'         => 'support',
            'priority'         => 'normal',
            'message'          => $body,
            'status'           => 'open',
            'last_activity_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Google Calendar push notifications (events.watch)
    //
    // Payload contract: empty body, all info comes from headers.
    //   X-Goog-Channel-Id     — our channel UUID (we issued at subscribe)
    //   X-Goog-Channel-Token  — must match the token we stored
    //   X-Goog-Resource-Id    — Google's resource id (calendar version)
    //   X-Goog-Resource-State — 'sync' (initial ping) | 'exists' (change)
    //                           | 'not_exists' (resource gone)
    //
    // We always 200 the request fast — the heavy "list events and
    // reconcile bookings" work happens in ReverseSyncCalendarFromGoogleJob.
    // -------------------------------------------------------------------------
    public function googleCalendarWebhook(Request $request)
    {
        $channelId   = (string) $request->header('X-Goog-Channel-Id', '');
        $channelTok  = (string) $request->header('X-Goog-Channel-Token', '');
        $resourceState = (string) $request->header('X-Goog-Resource-State', '');

        if ($channelId === '') {
            return response()->json(['error' => 'missing channel id'], 400);
        }

        $row = DB::table('gcal_watch_channels')
            ->where('channel_id', $channelId)
            ->first();
        if (!$row || $row->status !== 'active') {
            // Unknown / stopped channel — silently ack so Google stops
            // retrying and doesn't fill its retry queue.
            Log::info('gcal.webhook.unknown_channel', ['channel_id' => $channelId]);
            return response()->json(['received' => true]);
        }

        // Token-equality is constant-time via hash_equals to avoid a
        // timing oracle on the watch token.
        if (!hash_equals((string) $row->token, $channelTok)) {
            Log::warning('gcal.webhook.token_mismatch', ['channel_id' => $channelId]);
            return response()->json(['error' => 'token mismatch'], 401);
        }

        // The first push after a subscribe is a 'sync' ping — Google
        // confirms the channel works. We don't need to fetch events on
        // that one.
        if ($resourceState === 'sync') {
            return response()->json(['received' => true, 'sync_ack' => true]);
        }

        // Dispatch the heavy work to the queue so this webhook ACKs in
        // <100ms (Google retries on slow responses).
        \App\Jobs\Booking\ReverseSyncCalendarFromGoogleJob::dispatch(
            (int) $row->photographer_id,
        );

        return response()->json(['received' => true]);
    }
}
