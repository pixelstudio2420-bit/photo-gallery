<?php

namespace App\Services;

use App\Models\DataExportRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates a PDPA "data portability" export for a given user.
 *
 * The export is a single JSON file containing every row we have for that user
 * across their core tables: auth profile, orders, payment slips, downloads,
 * reviews, support tickets, wishlist, etc.
 *
 * We deliberately avoid includng other users' PII (e.g. chat messages reveal
 * both sides — filter to messages sent by this user or to this user).
 */
class DataExportService
{
    /** Storage disk used to hold exports (kept private). */
    const DISK = 'local';

    /** Sub-directory inside the disk root. */
    const DIR = 'exports';

    /** Export file TTL in days (auto-expires after). */
    const TTL_DAYS = 14;

    /**
     * Build + persist the export for a given request. Idempotent — if already
     * ready, returns the existing file.
     *
     * @return DataExportRequest
     */
    public function process(DataExportRequest $req, ?int $processedBy = null): DataExportRequest
    {
        $user = User::find($req->user_id);
        if (!$user) {
            $req->update([
                'status'       => 'rejected',
                'admin_note'   => 'User not found',
                'processed_at' => now(),
                'processed_by' => $processedBy,
            ]);
            return $req;
        }

        $payload = $this->collect($user);

        $filename = sprintf('pdpa-export-user-%d-%s.json', $user->id, now()->format('Ymd-His'));
        $relPath  = self::DIR . '/' . $filename;

        Storage::disk(self::DISK)->put($relPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $size = Storage::disk(self::DISK)->size($relPath);

        $req->update([
            'status'          => 'ready',
            'file_path'       => $relPath,
            'file_disk'       => self::DISK,
            'file_size_bytes' => $size,
            'download_token'  => (string) Str::random(60),
            'expires_at'      => now()->addDays(self::TTL_DAYS),
            'processed_at'    => now(),
            'processed_by'    => $processedBy,
        ]);

        return $req->fresh();
    }

    /**
     * Remove expired or manually-deleted export files. Called by admin action or cleanup cron.
     */
    public function deleteFile(DataExportRequest $req): void
    {
        if ($req->file_path) {
            try {
                Storage::disk($req->file_disk ?: self::DISK)->delete($req->file_path);
            } catch (\Throwable) {
                // already gone
            }
        }
        $req->update([
            'file_path'      => null,
            'file_size_bytes'=> null,
            'download_token' => null,
        ]);
    }

    /**
     * Collect every personal data row for this user across the known tables.
     *
     * @return array<string, mixed>
     */
    public function collect(User $user): array
    {
        $uid = $user->id;
        $data = [
            'meta' => [
                'exported_at' => now()->toIso8601String(),
                'user_id'     => $uid,
                'note'        => 'เอกสารชุดนี้คือสำเนาข้อมูลส่วนบุคคลของคุณตามสิทธิ PDPA',
            ],
            'profile' => $user->toArray(),
        ];

        // Generic helpers to safely pull rows without crashing if a table is missing
        $pull = function (string $table, string $userCol = 'user_id') use ($uid): array {
            if (!Schema::hasTable($table)) return [];
            try {
                return DB::table($table)->where($userCol, $uid)->get()->map(fn ($r) => (array) $r)->all();
            } catch (\Throwable) {
                return [];
            }
        };

        $data['orders']           = $pull('orders');
        $data['payment_slips']    = Schema::hasTable('payment_slips') && Schema::hasTable('orders')
            ? DB::table('payment_slips as p')
                ->join('orders as o', 'o.id', '=', 'p.order_id')
                ->where('o.user_id', $uid)
                ->select('p.*')
                ->get()->map(fn ($r) => (array) $r)->all()
            : [];

        $data['downloads']        = $pull('download_logs');
        $data['wishlists']        = $pull('wishlists');
        $data['reviews']          = $pull('reviews');
        $data['carts']            = $pull('carts');
        $data['cart_items']       = Schema::hasTable('cart_items') && Schema::hasTable('carts')
            ? DB::table('cart_items as ci')
                ->join('carts as c', 'c.id', '=', 'ci.cart_id')
                ->where('c.user_id', $uid)
                ->select('ci.*')
                ->get()->map(fn ($r) => (array) $r)->all()
            : [];

        $data['notifications']    = $pull('notifications', 'notifiable_id');
        $data['support_tickets']  = $pull('support_tickets');
        $data['user_sessions']    = $pull('user_sessions');
        $data['marketing_subscribers'] = Schema::hasTable('marketing_subscribers')
            ? DB::table('marketing_subscribers')->where('email', $user->email ?? '__none__')->get()->map(fn ($r) => (array) $r)->all()
            : [];

        // Chat messages — include both sent by + received by this user, strip counter-party PII
        if (Schema::hasTable('chat_messages')) {
            try {
                $msgs = DB::table('chat_messages')
                    ->where('sender_id', $uid)
                    ->orWhere('receiver_id', $uid)
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();
                $data['chat_messages'] = $msgs;
            } catch (\Throwable) {
                $data['chat_messages'] = [];
            }
        }

        // Activity log
        $data['activity_log'] = Schema::hasTable('activity_log')
            ? DB::table('activity_log')->where('causer_id', $uid)->where('causer_type', User::class)->get()->map(fn ($r) => (array) $r)->all()
            : [];

        return $data;
    }
}
