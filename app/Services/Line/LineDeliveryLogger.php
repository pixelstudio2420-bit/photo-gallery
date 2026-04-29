<?php

namespace App\Services\Line;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Writes a row to line_deliveries for every push/multicast attempt.
 *
 * Why this exists
 * ---------------
 * Without this, the only record of "we tried to LINE-notify user X" is
 * a Laravel log line — which is impossible to query, ages out, and
 * doesn't survive a log rotation. The audit table:
 *
 *   • Lets support answer "did user X get the order notification?" in
 *     one SQL query instead of 20 minutes of log-grep.
 *
 *   • Makes failure rate measurable: SELECT status, COUNT(*) FROM
 *     line_deliveries WHERE created_at > now() - interval '1 hour'.
 *
 *   • Provides the dedup key for idempotent sends — caller passes
 *     idempotency_key='order.42.delivery' and a second dispatch is a
 *     no-op (catches the unique-violation, returns the existing row's id).
 *
 * Idempotency contract
 * --------------------
 * begin() inserts a 'pending' row. If a row already exists with the
 * same (line_user_id, idempotency_key), the existing row's id is
 * returned and the caller knows to skip the actual API call.
 *
 * markSent() / markFailed() / markSkipped() flip the status when the
 * caller knows the outcome. They're idempotent — calling twice with
 * the same row id just updates fields.
 */
class LineDeliveryLogger
{
    /**
     * Begin a delivery. Returns:
     *   ['id' => int, 'duplicate' => bool]
     *
     * If `duplicate` is true, the caller should NOT make the LINE API
     * call — a previous attempt already produced (or is producing)
     * this delivery. Caller can still poll the row's status if they
     * want to know how the prior attempt ended.
     */
    public function begin(
        ?int $userId,
        string $lineUserId,
        string $deliveryType,        // push | multicast | broadcast | reply
        string $messageType,         // text | image | flex | sticker | template
        ?string $payloadSummary = null,
        ?array $payloadJson = null,
        ?string $idempotencyKey = null,
    ): array {
        $row = [
            'user_id'         => $userId,
            'line_user_id'    => $lineUserId,
            'delivery_type'   => $deliveryType,
            'message_type'    => $messageType,
            'payload_summary' => $payloadSummary !== null
                ? mb_substr($payloadSummary, 0, 500)
                : null,
            'payload_json'    => $payloadJson !== null
                ? json_encode($payloadJson, JSON_UNESCAPED_UNICODE)
                : null,
            'status'          => 'pending',
            'attempts'        => 0,
            'idempotency_key' => $idempotencyKey,
            'created_at'      => now(),
        ];

        try {
            $id = DB::table('line_deliveries')->insertGetId($row);
            return ['id' => $id, 'duplicate' => false];
        } catch (QueryException $e) {
            if ($idempotencyKey !== null && $this->isUniqueViolation($e)) {
                $existing = DB::table('line_deliveries')
                    ->where('line_user_id', $lineUserId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->orderByDesc('id')
                    ->first();
                if ($existing) {
                    return ['id' => (int) $existing->id, 'duplicate' => true];
                }
            }
            throw $e;
        }
    }

    public function markSent(int $deliveryId, int $httpStatus = 200): void
    {
        DB::table('line_deliveries')->where('id', $deliveryId)->update([
            'status'      => 'sent',
            'http_status' => $httpStatus,
            'attempts'    => DB::raw('attempts + 1'),
            'sent_at'     => now(),
        ]);
    }

    public function markFailed(int $deliveryId, ?int $httpStatus, string $error): void
    {
        DB::table('line_deliveries')->where('id', $deliveryId)->update([
            'status'      => 'failed',
            'http_status' => $httpStatus,
            'error'       => mb_substr($error, 0, 500),
            'attempts'    => DB::raw('attempts + 1'),
        ]);
    }

    public function markSkipped(int $deliveryId, string $reason): void
    {
        DB::table('line_deliveries')->where('id', $deliveryId)->update([
            'status' => 'skipped',
            'error'  => mb_substr($reason, 0, 500),
        ]);
    }

    /**
     * Bump the attempt counter without flipping status — used when a
     * retry is about to fire. Lets us see how many times we've tried
     * before settling the status.
     */
    public function incrementAttempt(int $deliveryId): void
    {
        DB::table('line_deliveries')->where('id', $deliveryId)->update([
            'attempts' => DB::raw('attempts + 1'),
        ]);
    }

    private function isUniqueViolation(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return $e->getCode() === '23505'
            || $e->getCode() === '23000'
            || str_contains($msg, 'duplicate')
            || str_contains($msg, 'unique constraint');
    }
}
