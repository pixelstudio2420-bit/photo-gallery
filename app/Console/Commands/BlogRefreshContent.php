<?php

namespace App\Console\Commands;

use App\Models\BlogAiTask;
use App\Models\BlogPost;
use App\Services\Blog\AiProviderInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * อัพเดทบทความเก่าด้วย AI เพื่อรักษา SEO
 *
 * คำสั่งนี้จะ:
 * 1. หาบทความเก่าที่เผยแพร่แล้ว (ตามจำนวนวันที่กำหนด)
 * 2. วิเคราะห์แต่ละบทความด้วย AI เพื่อเสนอแนะการปรับปรุง
 * 3. บันทึกคำแนะนำเป็น AI task สำหรับ admin ตรวจสอบ
 * 4. ไม่อัพเดทอัตโนมัติ — ต้อง review ก่อนเสมอ
 *
 * Usage:
 *   php artisan blog:refresh-content                 # บทความเก่ากว่า 90 วัน
 *   php artisan blog:refresh-content --days=60       # บทความเก่ากว่า 60 วัน
 *   php artisan blog:refresh-content --limit=5       # จำกัดจำนวนบทความ
 */
class BlogRefreshContent extends Command
{
    protected $signature = 'blog:refresh-content
                            {--days=90 : Posts older than N days}
                            {--limit=10 : Maximum number of posts to analyze}';

    protected $description = 'อัพเดทบทความเก่าด้วย AI เพื่อรักษา SEO';

    public function handle(): int
    {
        $days  = (int) $this->option('days');
        $limit = (int) $this->option('limit');

        $this->info("กำลังค้นหาบทความที่เผยแพร่เก่ากว่า {$days} วัน...");

        // ── Find old published posts that haven't been refreshed recently ──
        $cutoffDate = now()->subDays($days);

        $posts = BlogPost::where('status', 'published')
            ->where('published_at', '<=', $cutoffDate)
            ->where(function ($q) use ($cutoffDate) {
                // Only posts that haven't been analyzed recently
                $q->whereNull('last_modified_at')
                  ->orWhere('last_modified_at', '<=', $cutoffDate);
            })
            ->orderBy('published_at') // oldest first
            ->limit($limit)
            ->get();

        if ($posts->isEmpty()) {
            $this->info('ไม่พบบทความเก่าที่ต้องวิเคราะห์');
            return self::SUCCESS;
        }

        $this->info("พบ {$posts->count()} บทความที่ต้องวิเคราะห์");

        // ── Check if AI provider is available ──
        $aiAvailable = $this->isAiAvailable();

        $analyzed = 0;
        $skipped  = 0;

        foreach ($posts as $post) {
            $this->line('');
            $this->info("วิเคราะห์: {$post->title}");
            $this->line("  URL: {$post->url}");
            $this->line("  เผยแพร่เมื่อ: {$post->published_at->format('d/m/Y')}");
            $this->line("  อายุ: {$post->published_at->diffInDays(now())} วัน");
            $this->line("  ยอดชม: {$post->view_count}");

            if ($aiAvailable) {
                $suggestions = $this->analyzeWithAi($post);

                if ($suggestions) {
                    $this->storeSuggestions($post, $suggestions);
                    $analyzed++;
                    $this->info('  ► บันทึกคำแนะนำเรียบร้อย');
                } else {
                    $skipped++;
                    $this->warn('  ► ไม่สามารถวิเคราะห์ได้');
                }
            } else {
                // Fallback: basic heuristic analysis without AI
                $suggestions = $this->analyzeWithHeuristics($post);
                $this->storeSuggestions($post, $suggestions);
                $analyzed++;
                $this->info('  ► บันทึกคำแนะนำ (heuristic) เรียบร้อย');
            }
        }

        $this->line('');
        $this->info("เสร็จสิ้น — วิเคราะห์แล้ว {$analyzed} รายการ, ข้าม {$skipped} รายการ");
        $this->line('กรุณาตรวจสอบคำแนะนำใน Admin > บล็อก > AI Tools > History');

        return self::SUCCESS;
    }

    /* ────────────────────────────────────────────────────────────────
     *  AI-based analysis
     * ──────────────────────────────────────────────────────────────── */
    protected function analyzeWithAi(BlogPost $post): ?string
    {
        try {
            $provider = app(AiProviderInterface::class);

            $prompt = <<<PROMPT
คุณเป็นผู้เชี่ยวชาญด้าน SEO และ Content Marketing

วิเคราะห์บทความนี้และเสนอแนะการปรับปรุง:

หัวข้อ: {$post->title}
คีย์เวิร์ดหลัก: {$post->focus_keyword}
เผยแพร่เมื่อ: {$post->published_at->format('d/m/Y')}
คะแนน SEO: {$post->seo_score}/100
ยอดชม: {$post->view_count}

เนื้อหา (ย่อ):
{$this->truncateContent($post->content)}

กรุณาวิเคราะห์และเสนอแนะ:
1. ข้อมูลที่ล้าสมัยหรือไม่ถูกต้องที่ควรอัพเดท
2. คีย์เวิร์ดใหม่ที่ควรเพิ่ม
3. หัวข้อย่อยใหม่ที่ควรเพิ่มเติม
4. วิธีปรับปรุง Meta Description
5. ลิงก์ภายในที่ควรเพิ่ม
6. คะแนนความเร่งด่วนในการอัพเดท (1-10)

ตอบเป็นภาษาไทย
PROMPT;

            $result = $provider->generateContent($prompt, [
                'temperature'  => 0.7,
                'max_tokens'   => 1500,
                'system_prompt' => 'คุณเป็นที่ปรึกษาด้าน SEO ที่ให้คำแนะนำเชิงปฏิบัติ',
            ]);

            // Log the AI task
            BlogAiTask::create([
                'type'              => 'content_refresh',
                'title'             => "วิเคราะห์บทความ: {$post->title}",
                'prompt'            => $prompt,
                'input_data'        => ['post_id' => $post->id, 'post_title' => $post->title],
                'output_data'       => $result['content'],
                'provider'          => $provider->getProviderName(),
                'model'             => $provider->getModelName(),
                'status'            => 'completed',
                'post_id'           => $post->id,
                'tokens_input'      => $result['tokens_input'] ?? 0,
                'tokens_output'     => $result['tokens_output'] ?? 0,
                'cost_usd'          => $result['cost'] ?? 0,
                'processing_time_ms' => 0,
            ]);

            return $result['content'];

        } catch (\Throwable $e) {
            $this->warn("  ✗ AI error: {$e->getMessage()}");
            Log::warning('BlogRefreshContent: AI analysis failed', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    /* ────────────────────────────────────────────────────────────────
     *  Heuristic-based analysis (fallback when AI is unavailable)
     * ──────────────────────────────────────────────────────────────── */
    protected function analyzeWithHeuristics(BlogPost $post): string
    {
        $suggestions = [];
        $age = $post->published_at->diffInDays(now());

        // Check word count
        if ($post->word_count < 500) {
            $suggestions[] = "- บทความสั้นเกินไป ({$post->word_count} คำ) ควรเพิ่มเนื้อหาให้ได้อย่างน้อย 800 คำ";
        }

        // Check SEO score
        if ($post->seo_score && $post->seo_score < 50) {
            $suggestions[] = "- คะแนน SEO ต่ำ ({$post->seo_score}/100) ควรปรับปรุง meta tags และ keyword density";
        }

        // Check missing meta
        if (empty($post->meta_description)) {
            $suggestions[] = '- ไม่มี Meta Description ควรเพิ่มเพื่อ SEO';
        }

        if (empty($post->focus_keyword)) {
            $suggestions[] = '- ไม่มี Focus Keyword ควรกำหนดคีย์เวิร์ดหลัก';
        }

        // Check featured image
        if (empty($post->featured_image)) {
            $suggestions[] = '- ไม่มีรูปภาพหลัก ควรเพิ่มเพื่อ social sharing และ SEO';
        }

        // Check table of contents
        if (empty($post->table_of_contents) && $post->word_count > 800) {
            $suggestions[] = '- บทความยาวแต่ไม่มี Table of Contents ควรเพิ่มหัวข้อย่อย';
        }

        // Age-based suggestions
        if ($age > 180) {
            $suggestions[] = "- บทความเก่ามาก ({$age} วัน) ควรตรวจสอบข้อมูลให้เป็นปัจจุบัน";
        }

        // Low view count for old post
        if ($post->view_count < 50 && $age > 60) {
            $suggestions[] = "- ยอดชมต่ำ ({$post->view_count}) ควรพิจารณาปรับ title และ meta เพื่อดึงดูด traffic";
        }

        if (empty($suggestions)) {
            $suggestions[] = '- บทความอยู่ในเกณฑ์พอใช้ได้ แนะนำให้อัพเดทข้อมูลให้เป็นปัจจุบัน';
        }

        return "คำแนะนำสำหรับบทความ: {$post->title}\n"
            . "อายุ: {$age} วัน | ยอดชม: {$post->view_count}\n"
            . "─────────────────────────\n"
            . implode("\n", $suggestions);
    }

    /* ────────────────────────────────────────────────────────────────
     *  Store suggestions as an AI task for admin review
     * ──────────────────────────────────────────────────────────────── */
    protected function storeSuggestions(BlogPost $post, string $suggestions): void
    {
        // Only create a task if not created by AI analysis already
        $existingTask = BlogAiTask::where('post_id', $post->id)
            ->where('type', 'content_refresh')
            ->where('created_at', '>=', now()->subHours(1))
            ->exists();

        if ($existingTask) {
            return;
        }

        BlogAiTask::create([
            'type'        => 'content_refresh',
            'title'       => "แนะนำปรับปรุง: {$post->title}",
            'prompt'      => 'auto-generated by blog:refresh-content command',
            'input_data'  => [
                'post_id'      => $post->id,
                'post_title'   => $post->title,
                'post_age_days' => $post->published_at->diffInDays(now()),
            ],
            'output_data' => $suggestions,
            'provider'    => 'heuristic',
            'model'       => 'rule-based',
            'status'      => 'completed',
            'post_id'     => $post->id,
        ]);

        Log::info('BlogRefreshContent: suggestions saved', [
            'post_id' => $post->id,
            'title'   => $post->title,
        ]);
    }

    /* ────────────────────────────────────────────────────────────────
     *  Helpers
     * ──────────────────────────────────────────────────────────────── */
    protected function isAiAvailable(): bool
    {
        try {
            app(AiProviderInterface::class);
            return true;
        } catch (\Throwable) {
            $this->warn('AI provider ไม่พร้อมใช้งาน — จะใช้ heuristic analysis แทน');
            return false;
        }
    }

    protected function truncateContent(?string $content): string
    {
        if (empty($content)) {
            return '(ไม่มีเนื้อหา)';
        }

        $text = strip_tags($content);
        return mb_substr($text, 0, 2000) . (mb_strlen($text) > 2000 ? '...' : '');
    }
}
