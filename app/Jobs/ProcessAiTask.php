<?php

namespace App\Jobs;

use App\Models\BlogAiTask;
use App\Services\Blog\AiContentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessAiTask -- Queue job สำหรับประมวลผล AI tasks แบบ async
 *
 * รองรับ task types: generate_article, summarize, rewrite, research,
 * seo_analyze, keyword_suggest, translate
 *
 * retry สูงสุด 3 ครั้ง, backoff 60 วินาที
 */
class ProcessAiTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * จำนวนครั้งที่ retry
     */
    public int $tries = 3;

    /**
     * ระยะเวลารอก่อน retry (วินาที)
     */
    public int $backoff = 60;

    /**
     * timeout สำหรับ job (วินาที)
     */
    public int $timeout = 300;

    public function __construct(public BlogAiTask $task) {}

    /**
     * ประมวลผล AI task
     */
    public function handle(AiContentService $ai): void
    {
        $startTime = microtime(true);

        // อัปเดตสถานะเป็น processing
        $this->task->update(['status' => 'processing']);

        Log::info('Processing AI task', [
            'task_id' => $this->task->id,
            'type'    => $this->task->type,
            'title'   => $this->task->title,
        ]);

        try {
            // ตั้ง provider ถ้ามีระบุ
            if (!empty($this->task->provider)) {
                $ai->setProvider($this->task->provider);
            }

            $inputData = $this->task->input_data;
            if (is_string($inputData)) {
                $inputData = json_decode($inputData, true) ?? [];
            }
            $inputData = $inputData ?? [];

            // ประมวลผลตามประเภท
            $result = match ($this->task->type) {
                'generate_article' => $this->handleGenerateArticle($ai, $inputData),
                'summarize'        => $this->handleSummarize($ai, $inputData),
                'rewrite'          => $this->handleRewrite($ai, $inputData),
                'research'         => $this->handleResearch($ai, $inputData),
                'seo_analyze'      => $this->handleSeoAnalyze($ai, $inputData),
                'keyword_suggest'  => $this->handleKeywordSuggest($ai, $inputData),
                'translate'        => $this->handleTranslate($ai, $inputData),
                'news_fetch'       => $this->handleNewsFetch($inputData),
                default            => throw new \RuntimeException("ไม่รู้จักประเภท task: {$this->task->type}"),
            };

            $elapsed = (int) round((microtime(true) - $startTime) * 1000);

            // บันทึกผลลัพธ์
            $this->task->update([
                'status'             => 'completed',
                'output_data'        => json_encode($result, JSON_UNESCAPED_UNICODE),
                'tokens_input'       => $result['tokens_input'] ?? $result['tokens_used']['input'] ?? 0,
                'tokens_output'      => $result['tokens_output'] ?? $result['tokens_used']['output'] ?? 0,
                'cost_usd'           => $result['cost'] ?? 0,
                'processing_time_ms' => $elapsed,
                'model'              => $result['model'] ?? $ai->getProvider()->getModelName(),
                'provider'           => $ai->getProvider()->getProviderName(),
            ]);

            Log::info('AI task completed', [
                'task_id'    => $this->task->id,
                'type'       => $this->task->type,
                'elapsed_ms' => $elapsed,
                'cost'       => $result['cost'] ?? 0,
            ]);

        } catch (\Exception $e) {
            $elapsed = (int) round((microtime(true) - $startTime) * 1000);

            $this->task->update([
                'status'             => 'failed',
                'error_message'      => mb_substr($e->getMessage(), 0, 65535),
                'processing_time_ms' => $elapsed,
            ]);

            Log::error('AI task failed', [
                'task_id'    => $this->task->id,
                'type'       => $this->task->type,
                'error'      => $e->getMessage(),
                'elapsed_ms' => $elapsed,
            ]);

            throw $e; // re-throw เพื่อให้ queue retry
        }
    }

    /**
     * จัดการเมื่อ job ล้มเหลวทุก attempt
     */
    public function failed(\Throwable $exception): void
    {
        $this->task->update([
            'status'        => 'failed',
            'error_message' => 'ล้มเหลวหลังจาก retry ครบ ' . $this->tries . ' ครั้ง: ' . $exception->getMessage(),
        ]);

        Log::error('AI task permanently failed', [
            'task_id' => $this->task->id,
            'type'    => $this->task->type,
            'error'   => $exception->getMessage(),
        ]);
    }

    /* ====================================================================
     *  Task Handlers
     * ==================================================================== */

    private function handleGenerateArticle(AiContentService $ai, array $input): array
    {
        $keyword = $input['keyword'] ?? $this->task->title ?? '';

        return $ai->generateArticle($keyword, [
            'language'    => $input['language'] ?? 'th',
            'word_count'  => $input['word_count'] ?? 1500,
            'tone'        => $input['tone'] ?? 'informative',
            'include_faq' => $input['include_faq'] ?? true,
            'include_toc' => $input['include_toc'] ?? true,
        ]);
    }

    private function handleSummarize(AiContentService $ai, array $input): array
    {
        $content = $input['content'] ?? $this->task->prompt ?? '';
        $format  = $input['format'] ?? 'paragraph';

        return $ai->summarizeContent($content, $format);
    }

    private function handleRewrite(AiContentService $ai, array $input): array
    {
        $content = $input['content'] ?? $this->task->prompt ?? '';
        $style   = $input['style'] ?? 'improve';

        return $ai->rewriteContent($content, $style);
    }

    private function handleResearch(AiContentService $ai, array $input): array
    {
        $query = $input['query'] ?? $this->task->title ?? '';

        return $ai->searchWeb($query);
    }

    private function handleSeoAnalyze(AiContentService $ai, array $input): array
    {
        $content = $input['content'] ?? $this->task->prompt ?? '';
        $keyword = $input['keyword'] ?? '';

        return $ai->analyzeSeo($content, $keyword);
    }

    private function handleKeywordSuggest(AiContentService $ai, array $input): array
    {
        $topic = $input['topic'] ?? $this->task->title ?? '';

        return $ai->suggestKeywords($topic);
    }

    private function handleTranslate(AiContentService $ai, array $input): array
    {
        $content    = $input['content'] ?? $this->task->prompt ?? '';
        $targetLang = $input['target_language'] ?? 'en';

        $prompt = "แปลเนื้อหาต่อไปนี้เป็น{$targetLang}:\n\n{$content}\n\nส่งกลับเฉพาะเนื้อหาที่แปลแล้ว";

        $result = $ai->getProvider()->generateContent($prompt, [
            'system_prompt' => 'คุณเป็นนักแปลมืออาชีพ แปลให้เป็นธรรมชาติ คงความหมายเดิม',
        ]);

        return [
            'translated_content' => $result['content'],
            'target_language'    => $targetLang,
            'tokens_input'       => $result['tokens_input'],
            'tokens_output'      => $result['tokens_output'],
            'cost'               => $result['cost'],
        ];
    }

    private function handleNewsFetch(array $input): array
    {
        $sourceId = $input['source_id'] ?? null;

        if ($sourceId === null) {
            throw new \RuntimeException('ไม่ได้ระบุ source_id สำหรับ news_fetch');
        }

        $source = \App\Models\BlogNewsSource::findOrFail($sourceId);
        $news   = app(\App\Services\Blog\NewsAggregatorService::class);

        $items = $news->fetchFromSource($source);

        return [
            'source_id'    => $sourceId,
            'source_name'  => $source->name,
            'items_fetched'=> count($items),
            'tokens_input' => 0,
            'tokens_output'=> 0,
            'cost'         => 0,
        ];
    }
}
