<?php

namespace App\Console\Commands;

use App\Models\BlogNewsItem;
use App\Models\BlogNewsSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ดึงข่าวสารจากแหล่งข่าว (RSS/Atom feeds) ที่ถึงเวลาอัพเดท
 *
 * Usage:
 *   php artisan blog:fetch-news                   # ดึงจากทุกแหล่งที่ถึงเวลา
 *   php artisan blog:fetch-news --source=3        # ดึงจากแหล่งที่ระบุ ID
 */
class BlogFetchNews extends Command
{
    protected $signature = 'blog:fetch-news {--source= : Specific source ID}';

    protected $description = 'ดึงข่าวสารจากแหล่งข่าวทั้งหมดที่ถึงเวลาอัพเดท';

    public function handle(): int
    {
        $sourceId = $this->option('source');

        if ($sourceId) {
            return $this->fetchFromSource((int) $sourceId);
        }

        return $this->fetchAllDueSources();
    }

    /* ────────────────────────────────────────────────────────────────
     *  Fetch all sources that are due for an update
     * ──────────────────────────────────────────────────────────────── */
    protected function fetchAllDueSources(): int
    {
        $sources = BlogNewsSource::due()->get();

        if ($sources->isEmpty()) {
            $this->info('ไม่มีแหล่งข่าวที่ถึงเวลาอัพเดท');
            return self::SUCCESS;
        }

        $this->info("พบ {$sources->count()} แหล่งข่าวที่ต้องอัพเดท");

        $totalNew = 0;

        foreach ($sources as $source) {
            $this->line('');
            $this->info("กำลังดึงจาก: {$source->name} ({$source->feed_url})");

            $newItems = $this->fetchFeed($source);
            $totalNew += $newItems;

            $this->info("  ► เพิ่มข่าวใหม่ {$newItems} รายการ");
        }

        $this->line('');
        $this->info("เสร็จสิ้น — เพิ่มข่าวใหม่ทั้งหมด {$totalNew} รายการ");

        return self::SUCCESS;
    }

    /* ────────────────────────────────────────────────────────────────
     *  Fetch from a specific source
     * ──────────────────────────────────────────────────────────────── */
    protected function fetchFromSource(int $sourceId): int
    {
        $source = BlogNewsSource::find($sourceId);

        if (!$source) {
            $this->error("ไม่พบแหล่งข่าว ID: {$sourceId}");
            return self::FAILURE;
        }

        $this->info("กำลังดึงจาก: {$source->name} ({$source->feed_url})");

        $newItems = $this->fetchFeed($source);

        $this->info("เสร็จสิ้น — เพิ่มข่าวใหม่ {$newItems} รายการ");

        return self::SUCCESS;
    }

    /* ────────────────────────────────────────────────────────────────
     *  Core: fetch and parse an RSS/Atom feed
     * ──────────────────────────────────────────────────────────────── */
    protected function fetchFeed(BlogNewsSource $source): int
    {
        $newCount = 0;

        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'BlogNewsFetcher/1.0'])
                ->get($source->feed_url);

            if (!$response->successful()) {
                $this->warn("  ✗ HTTP {$response->status()} จาก {$source->feed_url}");
                Log::warning('BlogFetchNews: HTTP error', [
                    'source_id' => $source->id,
                    'status'    => $response->status(),
                ]);
                return 0;
            }

            $xml = $response->body();
            $items = $this->parseFeedXml($xml, $source->feed_type);

            foreach ($items as $item) {
                // Skip duplicates by URL
                $exists = BlogNewsItem::where('source_id', $source->id)
                    ->where('url', $item['url'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                BlogNewsItem::create([
                    'source_id'        => $source->id,
                    'title'            => mb_substr($item['title'], 0, 500),
                    'url'              => $item['url'],
                    'original_content' => $item['content'] ?? null,
                    'image_url'        => $item['image'] ?? null,
                    'category_id'      => $source->category_id,
                    'status'           => 'fetched',
                    'published_at'     => $item['published_at'] ?? now(),
                    'fetched_at'       => now(),
                ]);

                $newCount++;
            }

            // Update source metadata
            $source->update([
                'last_fetched_at'     => now(),
                'total_items_fetched' => $source->total_items_fetched + $newCount,
            ]);

        } catch (\Throwable $e) {
            $this->error("  ✗ Error: {$e->getMessage()}");
            Log::error('BlogFetchNews: Exception', [
                'source_id' => $source->id,
                'error'     => $e->getMessage(),
            ]);
        }

        return $newCount;
    }

    /* ────────────────────────────────────────────────────────────────
     *  Parse RSS 2.0 or Atom XML into an array of items
     * ──────────────────────────────────────────────────────────────── */
    protected function parseFeedXml(string $xml, ?string $feedType): array
    {
        $items = [];

        try {
            libxml_use_internal_errors(true);
            $doc = simplexml_load_string($xml);

            if ($doc === false) {
                $this->warn('  ✗ XML parse error');
                return [];
            }

            // Detect feed type if not explicitly set
            if (!$feedType || $feedType === 'auto') {
                $feedType = isset($doc->channel) ? 'rss' : 'atom';
            }

            if ($feedType === 'rss') {
                $items = $this->parseRss($doc);
            } else {
                $items = $this->parseAtom($doc);
            }
        } catch (\Throwable $e) {
            $this->warn("  ✗ Parse error: {$e->getMessage()}");
        }

        return $items;
    }

    protected function parseRss(\SimpleXMLElement $doc): array
    {
        $items = [];

        foreach ($doc->channel->item ?? [] as $entry) {
            $ns = $entry->getNamespaces(true);

            $content = (string) $entry->description;
            if (isset($ns['content'])) {
                $encoded = $entry->children($ns['content']);
                if (isset($encoded->encoded)) {
                    $content = (string) $encoded->encoded;
                }
            }

            // Try to extract image from enclosure or media:content
            $image = null;
            if (isset($entry->enclosure) && str_contains((string) $entry->enclosure['type'], 'image')) {
                $image = (string) $entry->enclosure['url'];
            }
            if (!$image && isset($ns['media'])) {
                $media = $entry->children($ns['media']);
                if (isset($media->content) && isset($media->content['url'])) {
                    $image = (string) $media->content['url'];
                }
            }

            $items[] = [
                'title'        => (string) $entry->title,
                'url'          => (string) $entry->link,
                'content'      => $content,
                'image'        => $image,
                'published_at' => !empty((string) $entry->pubDate)
                    ? \Carbon\Carbon::parse((string) $entry->pubDate)->toDateTimeString()
                    : null,
            ];
        }

        return $items;
    }

    protected function parseAtom(\SimpleXMLElement $doc): array
    {
        $items = [];
        $ns = $doc->getNamespaces(true);

        // Register default namespace
        $doc->registerXPathNamespace('atom', $ns[''] ?? 'http://www.w3.org/2005/Atom');

        foreach ($doc->entry ?? [] as $entry) {
            $link = '';
            foreach ($entry->link as $l) {
                $rel = (string) ($l['rel'] ?? 'alternate');
                if ($rel === 'alternate' || $rel === '') {
                    $link = (string) $l['href'];
                    break;
                }
            }
            if (!$link && isset($entry->link[0])) {
                $link = (string) $entry->link[0]['href'];
            }

            $content = (string) ($entry->content ?? $entry->summary ?? '');

            $items[] = [
                'title'        => (string) $entry->title,
                'url'          => $link,
                'content'      => $content,
                'image'        => null,
                'published_at' => !empty((string) $entry->published)
                    ? \Carbon\Carbon::parse((string) $entry->published)->toDateTimeString()
                    : (!empty((string) $entry->updated)
                        ? \Carbon\Carbon::parse((string) $entry->updated)->toDateTimeString()
                        : null),
            ];
        }

        return $items;
    }
}
