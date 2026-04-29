<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use App\Models\AppSetting;
use App\Services\SeoAnalyzerService;
use Illuminate\Console\Command;

class AuditSeoCriticalPages extends Command
{
    protected $signature = 'seo:audit
                            {--urls=  : Comma-separated URLs to audit (default: home + events + blog + photographers + products + contact)}
                            {--quiet-if-clean : Only output when problems are found}';

    protected $description = 'รัน SEO Analyzer กับหน้า critical (home, events, blog, …) เก็บผลลงใน app_settings และปั๊มแจ้งเตือนถ้าเจอปัญหา';

    /**
     * Default URLs to audit when --urls is not provided. Mirrors the
     * suggestion list in SeoAnalyzerController::index() so the daily
     * audit covers the same surface admins check manually.
     */
    private function defaultUrls(): array
    {
        $base = rtrim(config('app.url', url('/')), '/');
        return [
            $base . '/',
            $base . '/events',
            $base . '/blog',
            $base . '/photographers',
            $base . '/products',
            $base . '/contact',
        ];
    }

    public function handle(SeoAnalyzerService $analyzer): int
    {
        $quiet = (bool) $this->option('quiet-if-clean');

        $optUrls = (string) $this->option('urls');
        $urls    = $optUrls !== ''
            ? array_filter(array_map('trim', explode(',', $optUrls)))
            : $this->defaultUrls();

        if (empty($urls)) {
            $this->error('ไม่มี URL ให้ตรวจสอบ');
            return self::FAILURE;
        }

        $results       = [];
        $totalIssues   = 0;
        $totalWarnings = 0;
        $worstScore    = 100;
        $worstUrl      = null;

        foreach ($urls as $url) {
            $this->line("กำลังตรวจ: {$url}");

            try {
                $result = $analyzer->analyzeUrl($url);
            } catch (\Throwable $e) {
                $this->warn("  failed: {$e->getMessage()}");
                $results[] = [
                    'url'    => $url,
                    'error'  => $e->getMessage(),
                    'score'  => 0,
                ];
                continue;
            }

            $score    = (int) ($result['score'] ?? 0);
            $issues   = count($result['issues']   ?? []);
            $warnings = count($result['warnings'] ?? []);

            $totalIssues   += $issues;
            $totalWarnings += $warnings;

            if ($score < $worstScore) {
                $worstScore = $score;
                $worstUrl   = $url;
            }

            $results[] = [
                'url'      => $url,
                'score'    => $score,
                'grade'    => $result['grade'] ?? 'F',
                'issues'   => $issues,
                'warnings' => $warnings,
                'fetched_at' => now()->toIso8601String(),
            ];

            if (!$quiet) {
                $this->line(sprintf(
                    '  score=%d grade=%s issues=%d warnings=%d',
                    $score,
                    $result['grade'] ?? 'F',
                    $issues,
                    $warnings
                ));
            }
        }

        // Persist the report so the dashboard can show "last audit at …".
        AppSetting::set('seo_audit_report', json_encode([
            'results'        => $results,
            'total_issues'   => $totalIssues,
            'total_warnings' => $totalWarnings,
            'worst_score'    => $worstScore,
            'worst_url'      => $worstUrl,
            'audited_at'     => now()->toDateTimeString(),
        ]));

        // Notify admins if we found critical issues.
        if ($totalIssues > 0 || $worstScore < 60) {
            AdminNotification::systemAlert(
                'SEO audit: พบปัญหา',
                sprintf(
                    'หน้าที่คะแนนต่ำสุด: %s (%d) · พบ critical issues %d รายการ',
                    parse_url($worstUrl ?? '', PHP_URL_PATH) ?: '/',
                    $worstScore,
                    $totalIssues
                ),
                'admin/settings/seo/analyzer'
            );
        }

        if ($quiet && $totalIssues === 0 && $worstScore >= 80) {
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'SEO audit สำเร็จ — ตรวจ %d หน้า, critical issues %d รายการ, warnings %d รายการ',
            count($urls),
            $totalIssues,
            $totalWarnings
        ));

        return self::SUCCESS;
    }
}
