<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Email photographers whose events are about to be auto-deleted.
 *
 * Scheduled to run daily BEFORE events:purge-expired so photographers get
 * a heads-up chance to extend retention, pin as portfolio, or upgrade.
 *
 * Safety rails
 * ------------
 *   • Honours retention_warning_enabled master switch.
 *   • Uses Event.auto_delete_warned_at to avoid double-sending — once the
 *     column is set, the same event is skipped on subsequent runs.
 *   • Skips events flagged auto_delete_exempt (obviously).
 *   • Skips events already in portfolio mode (originals_purged_at set).
 *   • Groups warnings by photographer — one email per photographer per run,
 *     not one email per event (avoids inbox spam).
 *   • No-ops if the photographer has no email on file.
 */
class WarnUpcomingCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;

    public function __construct(public bool $dryRun = false)
    {
        $this->onQueue('mail');
    }

    public function handle(MailService $mail): void
    {
        if ((string) AppSetting::get('retention_warning_enabled', '1') !== '1') {
            Log::info('WarnUpcomingCleanupJob: retention warnings disabled');
            return;
        }

        $warnDaysAhead = (int) AppSetting::get('retention_warning_days_ahead', '1');
        if ($warnDaysAhead <= 0) return;

        $now = now();
        $cutoffFrom = $now->copy();
        $cutoffTo   = $now->copy()->addDays($warnDaysAhead + 1);  // buffer: catch events deleting in the next (N+1) days

        // Walk candidate events in small batches so we don't page a giant
        // SELECT into memory. We post-filter by effectiveDeleteAt() because
        // that function is too expensive to translate into SQL (it reads
        // tier from AppSetting + photographer_profiles).
        $byPhotog = [];  // [user_id => ['email' => str, 'name' => str, 'events' => []]]

        Event::query()
            ->where('auto_delete_exempt', false)
            ->whereNull('auto_delete_warned_at')
            ->whereNull('originals_purged_at')
            ->with(['photographer:id,email,first_name,last_name', 'photographer.photographerProfile:id,user_id,display_name'])
            ->chunkById(200, function ($events) use (&$byPhotog, $cutoffFrom, $cutoffTo) {
                foreach ($events as $ev) {
                    $eta = $ev->effectiveDeleteAt();
                    if (!$eta) continue;
                    if ($eta->lt($cutoffFrom) || $eta->gt($cutoffTo)) continue;

                    $photog = $ev->photographer;
                    if (!$photog || empty($photog->email)) continue;

                    $uid = (int) $photog->id;
                    if (!isset($byPhotog[$uid])) {
                        $byPhotog[$uid] = [
                            'email'  => $photog->email,
                            'name'   => $this->photographerDisplayName($photog),
                            'events' => [],
                        ];
                    }

                    $byPhotog[$uid]['events'][] = [
                        'id'          => $ev->id,
                        'name'        => $ev->name,
                        'delete_at'   => $eta->format('Y-m-d H:i'),
                        'photo_count' => (int) $ev->photos()->count(),
                    ];

                    // Book-keep so the next run doesn't re-warn.
                    if (!$this->dryRun) {
                        $ev->forceFill(['auto_delete_warned_at' => now()])->saveQuietly();
                    }
                }
            });

        if (empty($byPhotog)) {
            Log::info('WarnUpcomingCleanupJob: no photographers need warning');
            return;
        }

        foreach ($byPhotog as $uid => $data) {
            $eventCount = count($data['events']);
            if ($this->dryRun) {
                Log::info('WarnUpcomingCleanupJob (dry-run): would warn', [
                    'user_id'     => $uid,
                    'email'       => $data['email'],
                    'event_count' => $eventCount,
                ]);
                continue;
            }

            try {
                $mail->photographerCleanupWarning($data['email'], $data['name'], $data['events'], 24);
            } catch (\Throwable $e) {
                Log::warning('WarnUpcomingCleanupJob mail failed', [
                    'user_id' => $uid,
                    'error'   => $e->getMessage(),
                ]);
                // Don't rethrow — keep processing remaining photographers.
            }
        }

        Log::info('WarnUpcomingCleanupJob: sent warnings', [
            'photographers' => count($byPhotog),
        ]);
    }

    private function photographerDisplayName(User $user): string
    {
        $profileName = optional($user->photographerProfile ?? null)->display_name;
        if ($profileName) return $profileName;

        $full = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        return $full !== '' ? $full : 'ช่างภาพ';
    }
}
