<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OrderTimelineService — write/read the append-only order status history
 * and coordinate status transitions on the order.
 */
class OrderTimelineService
{
    /**
     * Append a status event to the history.
     *
     * @param string $source  One of: user, admin, system, gateway
     * @param array  $meta    Arbitrary context (gateway response, IP, notes, ...)
     */
    public function log(
        Order $order,
        string $status,
        ?string $description = null,
        string $source = 'system',
        ?int $adminId = null,
        ?int $userId = null,
        array $meta = []
    ): OrderStatusHistory {
        $actorName = $this->resolveActorName($source, $adminId, $userId);

        return OrderStatusHistory::create([
            'order_id'            => $order->id,
            'status'              => $status,
            'description'         => $description,
            'changed_by_admin_id' => $adminId,
            'changed_by_user_id'  => $userId,
            'actor_name'          => $actorName,
            'source'              => $source,
            'meta'                => $meta,
            'created_at'          => now(),
        ]);
    }

    /**
     * Get a rich, UI-ready timeline for a given order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTimeline(Order $order): array
    {
        $events = OrderStatusHistory::with(['admin', 'user'])
            ->where('order_id', $order->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $events->map(function (OrderStatusHistory $event) {
            return [
                'id'          => $event->id,
                'status'      => $event->status,
                'description' => $event->description,
                'icon'        => $event->icon,
                'color'       => $event->color,
                'source'      => $event->source,
                'actor_name'  => $event->actor_name,
                'admin_id'    => $event->changed_by_admin_id,
                'user_id'     => $event->changed_by_user_id,
                'meta'        => $event->meta ?? [],
                'created_at'  => $event->created_at,
                'human_time'  => $event->created_at ? $event->created_at->diffForHumans() : null,
            ];
        })->all();
    }

    /**
     * Change the order's status and write a matching timeline entry atomically.
     */
    public function changeStatus(
        Order $order,
        string $newStatus,
        string $description,
        string $source = 'admin',
        ?int $adminId = null
    ): void {
        DB::transaction(function () use ($order, $newStatus, $description, $source, $adminId) {
            $previousStatus = $order->status;

            if ($previousStatus !== $newStatus) {
                $order->status = $newStatus;
                $order->save();
            }

            $this->log(
                $order,
                $newStatus,
                $description,
                $source,
                $adminId,
                null,
                ['previous_status' => $previousStatus]
            );
        });
    }

    /**
     * Resolve a best-effort actor name from the supplied ids/source.
     */
    private function resolveActorName(string $source, ?int $adminId, ?int $userId): ?string
    {
        try {
            if ($adminId) {
                $admin = \App\Models\Admin::find($adminId);
                if ($admin) {
                    $name = trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? ''));
                    return $name !== '' ? $name : ($admin->email ?? 'Admin');
                }
            }

            if ($userId) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                    return $name !== '' ? $name : ($user->email ?? 'Customer');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OrderTimelineService resolveActorName failed: ' . $e->getMessage());
        }

        return match ($source) {
            'system'  => 'ระบบ',
            'gateway' => 'Payment Gateway',
            'admin'   => 'Admin',
            'user'    => 'Customer',
            default   => null,
        };
    }
}
