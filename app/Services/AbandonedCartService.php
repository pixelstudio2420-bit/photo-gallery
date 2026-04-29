<?php

namespace App\Services;

use App\Models\AbandonedCart;
use Illuminate\Support\Facades\Log;

/**
 * AbandonedCartService — tracks cart activity and runs recovery campaigns.
 *
 * Called from:
 *  - CartService / checkout middleware when cart state changes (trackActivity)
 *  - OrderService after successful payment (markRecovered)
 *  - ProcessAbandonedCarts artisan command (processReminders)
 */
class AbandonedCartService
{
    /**
     * Record cart activity. Creates a new abandoned_cart or updates the
     * existing active one. Only persists when there's at least 1 item.
     */
    public function trackActivity(?int $userId, ?string $email, ?string $sessionId, array $items): void
    {
        if (count($items) === 0) {
            return;
        }

        // Need at least one identifier
        if (empty($userId) && empty($email) && empty($sessionId)) {
            return;
        }

        $itemCount      = 0;
        $estimatedTotal = 0.0;
        foreach ($items as $item) {
            $qty   = (int) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            $itemCount      += $qty;
            $estimatedTotal += $price * $qty;
        }

        try {
            AbandonedCart::createOrUpdate([
                'user_id'          => $userId,
                'email'            => $email,
                'session_id'       => $sessionId,
                'items'            => $items,
                'item_count'       => $itemCount,
                'estimated_total'  => $estimatedTotal,
                'recovery_status'  => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::warning('AbandonedCartService::trackActivity failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark the matching abandoned cart as recovered when the customer completes checkout.
     */
    public function markRecovered(?int $userId, ?string $sessionId, int $orderId): void
    {
        try {
            $query = AbandonedCart::whereIn('recovery_status', ['pending', 'reminded_1', 'reminded_2']);

            if ($userId) {
                $query->where('user_id', $userId);
            } elseif ($sessionId) {
                $query->where('session_id', $sessionId);
            } else {
                return;
            }

            $cart = $query->latest('last_activity_at')->first();
            if ($cart) {
                $cart->markRecovered($orderId);
            }
        } catch (\Throwable $e) {
            Log::warning('AbandonedCartService::markRecovered failed: ' . $e->getMessage());
        }
    }

    /**
     * Find a cart by its recovery token (used by the /cart/recover/{token} route).
     */
    public function findByToken(string $token): ?AbandonedCart
    {
        if ($token === '' || strlen($token) < 32) {
            return null;
        }

        return AbandonedCart::where('recovery_token', $token)
            ->whereIn('recovery_status', ['pending', 'reminded_1', 'reminded_2'])
            ->first();
    }

    /**
     * Process all reminder & expiry work. Called by the scheduler.
     *
     * @return array{reminded_1:int, reminded_2:int, expired:int}
     */
    public function processReminders(MailService $mail): array
    {
        $remindedOne = 0;
        $remindedTwo = 0;
        $expired     = 0;

        // 1st reminder (1h after abandonment)
        AbandonedCart::eligibleForReminder1()
            ->orderBy('last_activity_at')
            ->limit(200)
            ->get()
            ->each(function (AbandonedCart $cart) use ($mail, &$remindedOne) {
                if ($this->sendFirstReminder($cart, $mail)) {
                    $remindedOne++;
                }
            });

        // 2nd reminder (24h after the first reminder)
        AbandonedCart::eligibleForReminder2()
            ->orderBy('first_reminder_at')
            ->limit(200)
            ->get()
            ->each(function (AbandonedCart $cart) use ($mail, &$remindedTwo) {
                if ($this->sendSecondReminder($cart, $mail)) {
                    $remindedTwo++;
                }
            });

        // Expire old reminded_2 carts (> 7 days)
        AbandonedCart::expired()
            ->limit(500)
            ->get()
            ->each(function (AbandonedCart $cart) use (&$expired) {
                try {
                    $cart->markExpired();
                    $expired++;
                } catch (\Throwable $e) {
                    Log::warning('AbandonedCart expire failed [' . $cart->id . ']: ' . $e->getMessage());
                }
            });

        return [
            'reminded_1' => $remindedOne,
            'reminded_2' => $remindedTwo,
            'expired'    => $expired,
        ];
    }

    /**
     * Send the 1st reminder email and flag the cart as reminded_1.
     */
    protected function sendFirstReminder(AbandonedCart $cart, MailService $mail): bool
    {
        $email = $this->resolveEmail($cart);
        if (!$email) {
            return false;
        }

        $name = $this->resolveName($cart);

        try {
            $sent = $mail->abandonedCartReminder1($email, $name, [
                'itemCount'   => (int) $cart->item_count,
                'total'       => (float) $cart->estimated_total,
                'items'       => $cart->items ?? [],
                'recoveryUrl' => $cart->getRecoveryUrl(),
            ]);

            $cart->update([
                'recovery_status'     => 'reminded_1',
                'first_reminder_at'   => now(),
            ]);

            return (bool) $sent;
        } catch (\Throwable $e) {
            Log::warning('AbandonedCart reminder1 failed [' . $cart->id . ']: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send the 2nd reminder email (with incentive) and flag as reminded_2.
     */
    protected function sendSecondReminder(AbandonedCart $cart, MailService $mail): bool
    {
        $email = $this->resolveEmail($cart);
        if (!$email) {
            return false;
        }

        $name = $this->resolveName($cart);

        try {
            $sent = $mail->abandonedCartReminder2($email, $name, [
                'itemCount'    => (int) $cart->item_count,
                'total'        => (float) $cart->estimated_total,
                'items'        => $cart->items ?? [],
                'recoveryUrl'  => $cart->getRecoveryUrl(),
                'discountCode' => 'COMEBACK10',
                'discountPct'  => 10,
            ]);

            $cart->update([
                'recovery_status'     => 'reminded_2',
                'second_reminder_at'  => now(),
            ]);

            return (bool) $sent;
        } catch (\Throwable $e) {
            Log::warning('AbandonedCart reminder2 failed [' . $cart->id . ']: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Prefer the related user's email, fall back to stored guest email.
     */
    private function resolveEmail(AbandonedCart $cart): ?string
    {
        if ($cart->user && !empty($cart->user->email)) {
            return $cart->user->email;
        }
        return $cart->email ?: null;
    }

    private function resolveName(AbandonedCart $cart): string
    {
        if ($cart->user) {
            $name = trim(($cart->user->first_name ?? '') . ' ' . ($cart->user->last_name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }
        return 'ลูกค้า';
    }
}
