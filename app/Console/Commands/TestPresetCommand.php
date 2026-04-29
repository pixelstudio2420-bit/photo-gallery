<?php

namespace App\Console\Commands;

use App\Models\EventPhoto;
use App\Models\PhotographerProfile;
use App\Services\PresetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Quick CLI tester for Lightroom .xmp preset files.
 *
 * Usage:
 *   php artisan presets:test                              # Test all .xmp in storage/app/sample-xmp/
 *   php artisan presets:test path/to/folder               # Test all .xmp in custom folder
 *   php artisan presets:test --photo-id=42                # Test against a specific photo
 *   php artisan presets:test path/to/preset.xmp           # Test a single .xmp file
 *
 * Workflow for users:
 *   1) Export preset from Lightroom Classic / Lightroom CC as .xmp
 *   2) Drop file(s) into storage/app/sample-xmp/
 *   3) Run: php artisan presets:test
 *   4) Open the URL it prints — see render comparison
 *
 * Outputs land in storage/app/public/test-xmp-import/ — viewable via:
 *   http://127.0.0.1:8001/storage/test-xmp-import/index.html
 */
class TestPresetCommand extends Command
{
    protected $signature = 'presets:test
                            {path? : Path to .xmp file or folder (default: storage/app/sample-xmp)}
                            {--photo-id= : EventPhoto id to render against (default: latest active photo)}';

    protected $description = 'Parse and render Lightroom .xmp preset files against a real photo';

    public function handle(PresetService $ps): int
    {
        // ─── Step 1: Resolve input path(s) ────────────────────────────
        $inputArg = $this->argument('path');
        $base = $inputArg
            ? (str_starts_with($inputArg, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $inputArg)
                ? $inputArg
                : storage_path('app/'.ltrim($inputArg, '/\\')))
            : storage_path('app/sample-xmp');

        if (is_file($base) && str_ends_with(strtolower($base), '.xmp')) {
            $files = [$base];
        } elseif (is_dir($base)) {
            $files = glob($base.'/*.xmp');
        } else {
            $this->error("ไม่พบไฟล์/โฟลเดอร์: {$base}");
            $this->line('');
            $this->line('💡 ลองวางไฟล์ .xmp ลงใน: '.storage_path('app/sample-xmp'));
            return Command::FAILURE;
        }

        if (empty($files)) {
            $this->warn("ไม่พบไฟล์ .xmp ใน: {$base}");
            $this->line('');
            $this->line('วิธีใช้:');
            $this->line('  1) Export preset .xmp จาก Lightroom (ดูคู่มือด้านล่าง)');
            $this->line('  2) วางไฟล์ลงใน: '.storage_path('app/sample-xmp'));
            $this->line('  3) รัน: php artisan presets:test');
            $this->line('');
            $this->line('📚 วิธี Export จาก Lightroom Classic:');
            $this->line('   Develop module → คลิกขวา preset ใน Presets panel → Show in Explorer/Finder');
            $this->line('   หรือ: เปิด folder แบบตรงๆ');
            $this->line('   Windows: %APPDATA%\\Adobe\\CameraRaw\\Settings\\');
            $this->line('   Mac:     ~/Library/Application Support/Adobe/CameraRaw/Settings/');
            $this->line('');
            $this->line('📚 วิธี Export จาก Lightroom CC (cloud version):');
            $this->line('   Right-click preset → Manage Presets → Export → ได้ .xmp file');
            return Command::SUCCESS;
        }

        $this->info('พบ '.count($files).' ไฟล์ .xmp');
        $this->newLine();

        // ─── Step 2: Resolve target photo ─────────────────────────────
        $photoId = $this->option('photo-id');
        $photo = $photoId
            ? EventPhoto::find($photoId)
            : EventPhoto::where('status', 'active')
                ->whereNotNull('original_path')
                ->orderByDesc('id')
                ->first();

        if (!$photo) {
            $this->error('ไม่พบรูปสำหรับใช้ทดสอบ — ต้องมีอย่างน้อย 1 รูปใน event_photos ที่ status=active');
            return Command::FAILURE;
        }

        $disk = $photo->storage_disk ?? 'public';
        if (!Storage::disk($disk)->exists($photo->original_path)) {
            $this->error("รูป id={$photo->id} หาไฟล์ใน storage ไม่เจอ ({$photo->original_path})");
            return Command::FAILURE;
        }

        $photoBytes = Storage::disk($disk)->get($photo->original_path);
        $this->info("✓ Photo id={$photo->id}: {$photo->width}x{$photo->height}, ".strlen($photoBytes).' bytes');
        $this->newLine();

        // ─── Step 3: Prep output dir ──────────────────────────────────
        $outDir = 'test-xmp-import';
        Storage::disk('public')->makeDirectory($outDir);
        foreach (Storage::disk('public')->files($outDir) as $f) {
            Storage::disk('public')->delete($f);
        }
        Storage::disk('public')->put($outDir.'/00-original.jpg', $photoBytes);

        // ─── Step 4: Process each file ────────────────────────────────
        $results = [];
        $headers = ['File', 'crs:*', 'Got', 'Cov %', 'Time(ms)', 'Output(KB)'];
        $rows = [];

        foreach ($files as $i => $xmpPath) {
            $filename = basename($xmpPath);
            $content = file_get_contents($xmpPath);

            preg_match_all('/crs:[A-Za-z0-9_]+\s*=\s*[\'"]/', $content, $m);
            $totalCrs = count($m[0]);

            $parsed = $ps->parseXmp($content);
            $captured = count($parsed);
            $coverage = $totalCrs > 0 ? round(($captured / $totalCrs) * 100, 1) : 0;

            $start = microtime(true);
            $rendered = $ps->previewBytes($parsed, $photoBytes, 800);
            $elapsedMs = (microtime(true) - $start) * 1000;

            if (!$rendered) {
                $this->error("✗ Render failed: {$filename}");
                continue;
            }

            $outBasename = sprintf('%02d-%s.jpg', $i + 1, preg_replace('/\.[^.]+$/', '', $filename));
            $outBasename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $outBasename);
            Storage::disk('public')->put($outDir.'/'.$outBasename, $rendered);

            $results[] = compact('filename', 'totalCrs', 'captured', 'coverage', 'parsed', 'elapsedMs')
                + ['outBasename' => $outBasename, 'bytes' => strlen($rendered)];

            $rows[] = [
                substr($filename, 0, 35),
                $totalCrs,
                $captured,
                "{$coverage}%",
                number_format($elapsedMs, 0),
                number_format(strlen($rendered) / 1024, 1),
            ];
        }

        $this->table($headers, $rows);

        // ─── Step 5: Summary stats ────────────────────────────────────
        if (count($results) > 0) {
            $totalCrs = array_sum(array_column($results, 'totalCrs'));
            $totalCap = array_sum(array_column($results, 'captured'));
            $avgCov = round(array_sum(array_column($results, 'coverage')) / count($results), 1);
            $avgMs  = round(array_sum(array_column($results, 'elapsedMs')) / count($results), 0);

            $this->newLine();
            $this->info('─── สรุป ───');
            $this->line("  Field coverage รวม: <fg=cyan>{$totalCap}/{$totalCrs}</> (".round($totalCap/$totalCrs*100, 1).'%)');
            $this->line("  Coverage เฉลี่ย:    <fg=cyan>{$avgCov}%</>");
            $this->line("  Render time เฉลี่ย: <fg=cyan>{$avgMs}ms</> (preview 800px)");
        }

        // ─── Step 6: Build HTML ───────────────────────────────────────
        $html = $this->buildHtml($photo, $results);
        Storage::disk('public')->put($outDir.'/index.html', $html);

        $this->newLine();
        $this->info('✅ Test complete!');
        $this->newLine();
        $this->line('🌐 ดูผลลัพธ์ที่:');
        $this->line('   <fg=cyan;options=underscore>http://127.0.0.1:8001/storage/test-xmp-import/index.html</>');
        $this->newLine();

        return Command::SUCCESS;
    }

    private function buildHtml(EventPhoto $photo, array $results): string
    {
        $html = '<!DOCTYPE html><html lang="th"><head><meta charset="utf-8">';
        $html .= '<title>XMP Import Test</title>';
        $html .= '<style>
            body { font-family: -apple-system, BlinkMacSystemFont, sans-serif;
                   background: #0f172a; color: #e2e8f0; margin: 0; padding: 24px; }
            h1 { color: #f1f5f9; border-bottom: 2px solid #334155; padding-bottom: 8px; font-size: 22px; }
            h2 { color: #f43f5e; margin-top: 28px; font-size: 17px; }
            .summary { background: #1e293b; padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #334155; }
            .summary code { background: #0f172a; padding: 2px 6px; border-radius: 3px; color: #f43f5e; font-family: monospace; }
            .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 14px; }
            .panel { background: #1e293b; border-radius: 12px; overflow: hidden; border: 1px solid #334155; }
            .panel.original { border: 2px solid #10b981; }
            .panel img { display: block; width: 100%; height: 280px; object-fit: cover; cursor: zoom-in; transition: transform .2s; }
            .panel img:hover { transform: scale(1.02); }
            .panel .info { padding: 12px; }
            .panel .name { font-weight: 600; color: #f1f5f9; font-size: 14px; word-break: break-all; }
            .badges { margin-top: 6px; }
            .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-right: 4px; font-family: monospace; }
            .cov-high { background: #064e3b; color: #6ee7b7; }
            .cov-mid  { background: #78350f; color: #fcd34d; }
            .cov-low  { background: #7f1d1d; color: #fca5a5; }
            .time     { background: #312e81; color: #a5b4fc; }
            details { background: #0f172a; padding: 8px 10px; border-radius: 4px; margin-top: 8px; font-size: 11px; }
            details summary { cursor: pointer; color: #94a3b8; font-family: monospace; }
            details code { display: block; padding: 6px 0; color: #cbd5e1; font-family: monospace; white-space: pre; line-height: 1.5; }
            .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.92); align-items: center; justify-content: center; z-index: 99; cursor: zoom-out; }
            .modal.open { display: flex; }
            .modal img { max-width: 95vw; max-height: 95vh; }
        </style></head><body>';

        $html .= '<h1>📸 XMP Import Test Results</h1>';

        if (count($results) > 0) {
            $totalCrs = array_sum(array_column($results, 'totalCrs'));
            $totalCap = array_sum(array_column($results, 'captured'));
            $avgCov = round(array_sum(array_column($results, 'coverage')) / count($results), 1);
            $avgMs  = round(array_sum(array_column($results, 'elapsedMs')) / count($results), 0);
            $html .= '<div class="summary">';
            $html .= '<p>ทดสอบ <code>'.count($results).'</code> ไฟล์ .xmp กับรูป id=<code>'.$photo->id.'</code> ('.$photo->width.'×'.$photo->height.')</p>';
            $html .= '<p>Coverage: <code>'.$totalCap.'/'.$totalCrs.'</code> ('.round($totalCap/$totalCrs*100, 1).'%) | เฉลี่ย <code>'.$avgCov.'%</code> ต่อไฟล์ | Render: <code>'.$avgMs.'ms</code> ต่อรูป</p>';
            $html .= '</div>';
        }

        $html .= '<h2>📂 Original Photo</h2>';
        $html .= '<div class="grid"><div class="panel original">';
        $html .= '<img src="00-original.jpg" alt="original" onclick="zoom(this.src)">';
        $html .= '<div class="info"><div class="name">Original</div></div>';
        $html .= '</div></div>';

        if (count($results) > 0) {
            $html .= '<h2>🎨 After XMP Imports — '.count($results).' presets</h2>';
            $html .= '<div class="grid">';
            foreach ($results as $r) {
                $cov = $r['coverage'];
                $covClass = $cov >= 75 ? 'cov-high' : ($cov >= 50 ? 'cov-mid' : 'cov-low');
                $html .= '<div class="panel">';
                $html .= '<img src="'.htmlspecialchars($r['outBasename']).'" loading="lazy" onclick="zoom(this.src)" alt="'.htmlspecialchars($r['filename']).'">';
                $html .= '<div class="info">';
                $html .= '<div class="name">'.htmlspecialchars($r['filename']).'</div>';
                $html .= '<div class="badges">';
                $html .= '<span class="badge '.$covClass.'">'.$cov.'% cov</span>';
                $html .= '<span class="badge time">'.number_format($r['elapsedMs'], 0).'ms</span>';
                $html .= '<span class="badge time">'.$r['captured'].'/'.$r['totalCrs'].' fields</span>';
                $html .= '</div>';
                $html .= '<details><summary>parsed values ('.count($r['parsed']).')</summary><code>';
                foreach ($r['parsed'] as $k => $v) {
                    $display = is_bool($v) ? ($v ? 'true' : 'false') : ($v > 0 ? "+{$v}" : (string)$v);
                    $html .= htmlspecialchars(sprintf("%-13s %s", $k, $display))."\n";
                }
                $html .= '</code></details>';
                $html .= '</div></div>';
            }
            $html .= '</div>';
        }

        $html .= '<div class="modal" id="zoomModal" onclick="this.classList.remove(\'open\')"><img id="zoomImg"></div>';
        $html .= '<script>function zoom(src){var m=document.getElementById("zoomModal");document.getElementById("zoomImg").src=src;m.classList.add("open");}</script>';
        $html .= '</body></html>';
        return $html;
    }
}
