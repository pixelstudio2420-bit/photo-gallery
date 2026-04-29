<?php

namespace App\Console\Commands;

use App\Models\EventPhoto;
use App\Models\PaymentSlip;
use App\Models\User;
use App\Services\Media\MediaContext;
use App\Services\Media\MediaPathBuilder;
use App\Services\Media\R2MediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * `php artisan media:migrate-to-r2 [--dry-run] [--system=events.photos] [--limit=1000]`
 *
 * Walks the database tables that reference uploaded files, copies any object
 * NOT already on R2 over to the canonical schema, and updates the row to
 * point at the new R2 key. Idempotent — re-running on already-migrated rows
 * is a no-op.
 *
 * Driven exclusively by R2MediaService so the same path schema gets enforced
 * here as on every fresh upload.
 *
 * What it covers (in priority order):
 *   1. event_photos.original_path / thumbnail_path / watermarked_path
 *   2. payment_slips.slip_path
 *   3. auth_users.avatar
 *
 * What it does NOT cover (yet) — flagged at the end:
 *   - blog post images (images embedded in body HTML — needs HTML rewrite)
 *   - user_files (complex permissions, separate tooling)
 *   - chat attachments
 *   - photographer portfolio / branding
 *
 *  These are listed in the audit summary so operators know the migration
 *  isn't claiming to be complete.
 */
class MigrateMediaToR2Command extends Command
{
    protected $signature = 'media:migrate-to-r2
        {--dry-run            : Print actions without copying or mutating the DB}
        {--system=            : Restrict to one system (e.g. events.photos, payments.slips, auth.avatar)}
        {--limit=             : Stop after migrating N rows (per system)}
        {--source-disk=public : The current local disk that holds legacy files}';

    protected $description = 'Migrate legacy local/S3 media to Cloudflare R2 in the canonical {system}/{entity}/user_{id}/{resource}/{file} schema.';

    private bool $dryRun = false;
    private int  $limit  = 0;

    public function __construct(
        private readonly R2MediaService $media,
        private readonly MediaPathBuilder $pathBuilder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->limit  = (int)  ($this->option('limit') ?: 0);

        $sourceDiskName = (string) $this->option('source-disk');
        if (!array_key_exists($sourceDiskName, config('filesystems.disks', []))) {
            $this->error("Source disk '{$sourceDiskName}' not configured");
            return self::FAILURE;
        }

        $only = (string) ($this->option('system') ?: '');

        if ($this->dryRun) {
            $this->components->warn('DRY RUN — no files will be copied, no rows mutated.');
        }

        $totals = [];

        if ($only === '' || $only === 'events.photos') {
            $totals['events.photos'] = $this->migrateEventPhotos($sourceDiskName);
        }
        if ($only === '' || $only === 'payments.slips') {
            $totals['payments.slips'] = $this->migratePaymentSlips($sourceDiskName);
        }
        if ($only === '' || $only === 'auth.avatar') {
            $totals['auth.avatar'] = $this->migrateAvatars($sourceDiskName);
        }

        $this->newLine();
        $this->components->info('Migration summary');
        foreach ($totals as $sys => $stats) {
            $this->line(sprintf(
                '  %-22s migrated=%d  skipped=%d  failed=%d',
                $sys,
                $stats['migrated'],
                $stats['skipped'],
                $stats['failed'],
            ));
        }

        $this->newLine();
        $this->components->warn('Not yet covered by this command (see source for backlog):');
        $this->line('  - blog.posts (HTML body image rewriter still needed)');
        $this->line('  - photographer.portfolio / branding');
        $this->line('  - storage.files (UserFile cloud storage — separate tooling)');
        $this->line('  - chat.attachments');

        return self::SUCCESS;
    }

    /* ─────────────────── per-system migrators ─────────────────── */

    /** @return array{migrated:int,skipped:int,failed:int} */
    private function migrateEventPhotos(string $sourceDisk): array
    {
        $stats = ['migrated' => 0, 'skipped' => 0, 'failed' => 0];

        $query = EventPhoto::query()
            ->whereNotNull('original_path')
            ->where('storage_disk', '!=', 'r2')
            ->orderBy('id');

        if ($this->limit > 0) {
            $query->limit($this->limit);
        }

        $this->components->info('Migrating event photos…');
        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $query->chunkById(100, function ($photos) use (&$stats, $sourceDisk, $bar) {
            foreach ($photos as $photo) {
                $bar->advance();
                try {
                    $event = $photo->event;
                    if (!$event) {
                        $stats['skipped']++;
                        continue;
                    }
                    $ctx = MediaContext::make('events', 'photos', (int) $event->photographer_id, (int) $event->id);
                    $newKey = $this->copyToR2(
                        $sourceDisk,
                        $photo->original_path,
                        $this->pathBuilder->buildWithFilename(
                            $ctx,
                            $this->preserveOrUuidFilename($photo->original_path, $photo->original_filename ?? ''),
                        ),
                    );
                    if ($newKey === null) {
                        $stats['failed']++;
                        continue;
                    }
                    if (!$this->dryRun) {
                        $photo->update([
                            'storage_disk'  => 'r2',
                            'original_path' => $newKey,
                            // Don't migrate derivatives in this command — the
                            // ProcessUploadedPhotoJob can be re-dispatched to
                            // regenerate thumb/wm fresh on R2.
                            'thumbnail_path'   => null,
                            'watermarked_path' => null,
                            'status'           => 'processing',
                        ]);
                    }
                    $stats['migrated']++;
                } catch (Throwable $e) {
                    Log::warning('media:migrate-to-r2 photo failure', [
                        'photo_id' => $photo->id,
                        'error'    => $e->getMessage(),
                    ]);
                    $stats['failed']++;
                }
            }
        });

        $bar->finish();
        $this->newLine();
        return $stats;
    }

    /** @return array{migrated:int,skipped:int,failed:int} */
    private function migratePaymentSlips(string $sourceDisk): array
    {
        $stats = ['migrated' => 0, 'skipped' => 0, 'failed' => 0];

        $query = PaymentSlip::query()
            ->whereNotNull('slip_path')
            ->where(function ($q) {
                $q->whereNull('storage_disk')
                  ->orWhere('storage_disk', '!=', 'r2');
            })
            ->orderBy('id');

        if ($this->limit > 0) {
            $query->limit($this->limit);
        }

        $this->components->info('Migrating payment slips…');
        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $query->chunkById(100, function ($slips) use (&$stats, $sourceDisk, $bar) {
            foreach ($slips as $slip) {
                $bar->advance();
                try {
                    $order = $slip->order;
                    if (!$order) {
                        $stats['skipped']++;
                        continue;
                    }
                    $ctx = MediaContext::make('payments', 'slips', (int) $order->user_id, (int) $order->id);
                    $newKey = $this->copyToR2(
                        $sourceDisk,
                        $slip->slip_path,
                        $this->pathBuilder->buildWithFilename(
                            $ctx,
                            $this->preserveOrUuidFilename($slip->slip_path, 'slip'),
                        ),
                    );
                    if ($newKey === null) {
                        $stats['failed']++;
                        continue;
                    }
                    if (!$this->dryRun) {
                        $slip->update([
                            'storage_disk' => 'r2',
                            'slip_path'    => $newKey,
                        ]);
                    }
                    $stats['migrated']++;
                } catch (Throwable $e) {
                    Log::warning('media:migrate-to-r2 slip failure', [
                        'slip_id' => $slip->id,
                        'error'   => $e->getMessage(),
                    ]);
                    $stats['failed']++;
                }
            }
        });

        $bar->finish();
        $this->newLine();
        return $stats;
    }

    /** @return array{migrated:int,skipped:int,failed:int} */
    private function migrateAvatars(string $sourceDisk): array
    {
        $stats = ['migrated' => 0, 'skipped' => 0, 'failed' => 0];

        $query = User::query()
            ->whereNotNull('avatar')
            ->where('avatar', 'not like', 'http%')               // skip social-login URLs
            ->where('avatar', 'not like', 'auth/avatar/user_%')  // skip already-migrated rows
            ->orderBy('id');

        if ($this->limit > 0) {
            $query->limit($this->limit);
        }

        $this->components->info('Migrating avatars…');
        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $query->chunkById(100, function ($users) use (&$stats, $sourceDisk, $bar) {
            foreach ($users as $user) {
                $bar->advance();
                try {
                    $ctx = MediaContext::make('auth', 'avatar', (int) $user->id);
                    $newKey = $this->copyToR2(
                        $sourceDisk,
                        $user->getRawOriginal('avatar'),
                        $this->pathBuilder->buildWithFilename(
                            $ctx,
                            $this->preserveOrUuidFilename($user->getRawOriginal('avatar'), 'avatar'),
                        ),
                    );
                    if ($newKey === null) {
                        $stats['failed']++;
                        continue;
                    }
                    if (!$this->dryRun) {
                        $user->update(['avatar' => $newKey]);
                    }
                    $stats['migrated']++;
                } catch (Throwable $e) {
                    Log::warning('media:migrate-to-r2 avatar failure', [
                        'user_id' => $user->id,
                        'error'   => $e->getMessage(),
                    ]);
                    $stats['failed']++;
                }
            }
        });

        $bar->finish();
        $this->newLine();
        return $stats;
    }

    /* ─────────────────── helpers ─────────────────── */

    private function copyToR2(string $sourceDisk, string $sourceKey, string $r2Key): ?string
    {
        $src = Storage::disk($sourceDisk);
        if (!$src->exists($sourceKey)) {
            // Source missing — nothing we can do. Caller increments 'failed'
            // so operators see the count.
            return null;
        }

        if ($this->dryRun) {
            $this->line("  DRY: {$sourceDisk}://{$sourceKey} → r2://{$r2Key}");
            return $r2Key;
        }

        // Stream copy — never load big files entirely into memory.
        $stream = $src->readStream($sourceKey);
        if (!is_resource($stream)) {
            return null;
        }
        try {
            Storage::disk('r2')->writeStream($r2Key, $stream, [
                'visibility' => 'private', // be conservative; service layer will set per-category visibility on new uploads
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (!Storage::disk('r2')->exists($r2Key)) {
            return null;
        }
        return $r2Key;
    }

    /**
     * Take an old key like `users/45/payment-slips/abc123.jpg` and emit a
     * filename that fits the new schema:
     *   - keeps the original extension (lowercased)
     *   - prepends a UUID for collision-free identity
     *   - falls back to a synthetic name if none is recoverable
     */
    private function preserveOrUuidFilename(string $oldKey, string $hint): string
    {
        $base = basename($oldKey) ?: $hint ?: 'file';
        $ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION) ?: '');
        $stem = pathinfo($base, PATHINFO_FILENAME) ?: $hint ?: 'file';

        $uuid = (string) Str::uuid();
        return $ext !== '' ? "{$uuid}_{$stem}.{$ext}" : "{$uuid}_{$stem}";
    }
}
