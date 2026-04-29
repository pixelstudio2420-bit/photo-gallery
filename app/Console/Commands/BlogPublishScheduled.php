<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * เผยแพร่บทความที่ตั้งเวลาไว้ (scheduled) เมื่อถึงกำหนด
 *
 * Usage:
 *   php artisan blog:publish-scheduled
 *
 * Recommended: run every minute via scheduler or cron.
 */
class BlogPublishScheduled extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'เผยแพร่บทความที่ตั้งเวลาไว้';

    public function handle(): int
    {
        $posts = BlogPost::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($posts->isEmpty()) {
            $this->info('ไม่มีบทความที่ต้องเผยแพร่ในขณะนี้');
            return self::SUCCESS;
        }

        $this->info("พบ {$posts->count()} บทความที่ต้องเผยแพร่");

        $published = 0;
        $failed    = 0;

        foreach ($posts as $post) {
            try {
                $post->update([
                    'status'       => 'published',
                    'published_at' => now(),
                ]);

                // Re-calculate reading time & word count if not set
                if (!$post->reading_time || !$post->word_count) {
                    $post->calculateReadingTime();
                    $post->calculateWordCount();
                    $post->save();
                }

                // Regenerate table of contents if empty
                if (empty($post->table_of_contents)) {
                    $post->generateTableOfContents();
                    $post->save();
                }

                $published++;
                $this->line("  ✓ เผยแพร่: {$post->title}");

                Log::info('BlogPublishScheduled: published post', [
                    'post_id' => $post->id,
                    'title'   => $post->title,
                    'slug'    => $post->slug,
                ]);

            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ✗ ล้มเหลว: {$post->title} — {$e->getMessage()}");

                Log::error('BlogPublishScheduled: failed to publish', [
                    'post_id' => $post->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $this->line('');
        $this->info("เสร็จสิ้น — เผยแพร่สำเร็จ {$published} รายการ, ล้มเหลว {$failed} รายการ");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
