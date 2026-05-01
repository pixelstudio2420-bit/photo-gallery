<?php

namespace App\Console\Commands;

use App\Services\Seo\PSeoService;
use Illuminate\Console\Command;

/**
 * Bulk-regenerate every active pSEO template's pages.
 *
 * Usage:
 *   php artisan pseo:generate
 *
 * Suggested schedule: weekly. The per-event observer keeps the most
 * impactful pages fresh in real-time; this batch sweep catches the
 * long tail (provinces with stale event counts, etc.).
 */
class GeneratePSeoPages extends Command
{
    protected $signature = 'pseo:generate';
    protected $description = 'Regenerate pSEO landing pages from active templates';

    public function handle(PSeoService $svc): int
    {
        $this->info('Generating pSEO landing pages...');

        $results = $svc->generateAll();

        $rows = [];
        foreach ($results as $type => $r) {
            $rows[] = [$type, $r['created'] ?? 0, $r['updated'] ?? 0, $r['skipped'] ?? 0];
        }
        $this->table(['Template Type', 'Created', 'Updated', 'Skipped'], $rows);

        $total = collect($results)->sum(fn ($r) => ($r['created'] ?? 0) + ($r['updated'] ?? 0));
        $this->info("Done — {$total} pages generated/updated");

        return self::SUCCESS;
    }
}
