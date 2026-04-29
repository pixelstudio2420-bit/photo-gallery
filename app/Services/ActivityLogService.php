<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ActivityLogService — export & cleanup utilities for the activity_logs table.
 */
class ActivityLogService
{
    /**
     * Export activity logs to CSV as a streamed response.
     *
     * Supported filters: date_from, date_to, admin_id, user_id, action
     */
    public function export(array $filters = []): StreamedResponse
    {
        $filename = 'activity-log-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Content-Disposition'       => 'attachment; filename="' . $filename . '"',
            'Cache-Control'             => 'no-store, no-cache',
            'X-Content-Type-Options'    => 'nosniff',
        ];

        return new StreamedResponse(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                'ID', 'Date', 'Actor', 'Action', 'Target', 'Description', 'IP',
            ]);

            // Preload admins & users referenced in the range for speed
            $query = DB::table('activity_logs')->orderByDesc('created_at');

            if (!empty($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }
            if (!empty($filters['admin_id'])) {
                $query->where('admin_id', $filters['admin_id']);
            }
            if (!empty($filters['user_id'])) {
                $query->where('user_id', $filters['user_id']);
            }
            if (!empty($filters['action'])) {
                $query->where('action', $filters['action']);
            }

            // Cache actors for efficiency
            $adminNames = [];
            $userNames  = [];

            try {
                $query->chunk(500, function ($rows) use ($handle, &$adminNames, &$userNames) {
                    foreach ($rows as $row) {
                        // Resolve actor name
                        $actor = '-';
                        if (!empty($row->admin_id)) {
                            if (!isset($adminNames[$row->admin_id])) {
                                $admin = Admin::find($row->admin_id);
                                $adminNames[$row->admin_id] = $admin
                                    ? trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')) ?: $admin->email
                                    : "admin#{$row->admin_id}";
                            }
                            $actor = 'Admin: ' . $adminNames[$row->admin_id];
                        } elseif (!empty($row->user_id)) {
                            if (!isset($userNames[$row->user_id])) {
                                $user = User::find($row->user_id);
                                $userNames[$row->user_id] = $user
                                    ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email
                                    : "user#{$row->user_id}";
                            }
                            $actor = 'User: ' . $userNames[$row->user_id];
                        }

                        $target = trim(($row->target_type ?? '') . ($row->target_id ? " #{$row->target_id}" : ''));

                        fputcsv($handle, [
                            $row->id,
                            $row->created_at,
                            $actor,
                            $row->action ?? '',
                            $target ?: '-',
                            $row->description ?? '',
                            $row->ip_address ?? '',
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('ActivityLogService::export chunk failed: ' . $e->getMessage());
                fputcsv($handle, ['ERROR', 'Export aborted', $e->getMessage()]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Cleanup activity logs older than $daysOld days.
     * Returns number of rows deleted.
     */
    public function cleanup(int $daysOld = 180): int
    {
        $cutoff = now()->subDays($daysOld);

        try {
            return DB::table('activity_logs')
                ->where('created_at', '<', $cutoff)
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('ActivityLogService::cleanup failed: ' . $e->getMessage());
            return 0;
        }
    }
}
