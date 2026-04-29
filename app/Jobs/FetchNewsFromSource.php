<?php

namespace App\Jobs;

use App\Models\BlogNewsSource;
use App\Services\Blog\NewsAggregatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * FetchNewsFromSource -- Queue job สำหรับดึงข่าวจาก source เฉพาะ
 *
 * ใช้กับ scheduler หรือ dispatch จาก admin panel
 * retry 3 ครั้ง, backoff 120 วินาที
 */
class FetchNewsFromSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * จำนวนครั้งที่ retry
     */
    public int $tries = 3;

    /**
     * ระยะเวลารอก่อน retry (วินาที)
     */
    public int $backoff = 120;

    /**
     * timeout สำหรับ job (วินาที)
     */
    public int $timeout = 180;

    public function __construct(public BlogNewsSource $source) {}

    /**
     * ดึงข่าวจาก source
     */
    public function handle(NewsAggregatorService $news): void
    {
        Log::info('FetchNewsFromSource job started', [
            'source_id' => $this->source->id,
            'name'      => $this->source->name,
            'feed_url'  => $this->source->feed_url,
        ]);

        try {
            $items = $news->fetchFromSource($this->source);

            Log::info('FetchNewsFromSource job completed', [
                'source_id'    => $this->source->id,
                'name'         => $this->source->name,
                'items_fetched'=> count($items),
            ]);

        } catch (\Exception $e) {
            Log::error('FetchNewsFromSource job failed', [
                'source_id' => $this->source->id,
                'name'      => $this->source->name,
                'error'     => $e->getMessage(),
            ]);

            throw $e; // re-throw เพื่อให้ queue retry
        }
    }

    /**
     * จัดการเมื่อ job ล้มเหลวทุก attempt
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FetchNewsFromSource job permanently failed', [
            'source_id' => $this->source->id,
            'name'      => $this->source->name,
            'error'     => $exception->getMessage(),
            'attempts'  => $this->tries,
        ]);

        // อัปเดต source ว่าล้มเหลว
        try {
            $this->source->update([
                'last_fetched_at' => now(),
            ]);
        } catch (\Exception) {
            // ถ้าอัปเดตไม่ได้ก็ไม่เป็นไร
        }
    }
}
