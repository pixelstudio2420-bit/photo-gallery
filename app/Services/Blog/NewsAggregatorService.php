<?php

namespace App\Services\Blog;

use App\Models\AppSetting;
use App\Models\BlogCategory;
use App\Models\BlogNewsItem;
use App\Models\BlogNewsSource;
use App\Models\BlogPost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * NewsAggregatorService -- รวบรวมข่าวจากแหล่งต่างๆ แล้วสรุปด้วย AI
 *
 * รองรับ RSS/Atom feed, สรุปข่าวอัตโนมัติ, และเผยแพร่เป็น blog post
 */
class NewsAggregatorService
{
    public function __construct(private AiContentService $ai) {}

    /* ====================================================================
     *  Fetching
     * ==================================================================== */

    /**
     * ดึงข่าวจากทุก source ที่ถึงเวลาอัปเดต
     *
     * @return array{fetched: int, sources: int, errors: array}
     */
    public function fetchAllDueSources(): array
    {
        $dueSources = BlogNewsSource::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('last_fetched_at')
                    ->orWhereRaw("last_fetched_at < NOW() - (fetch_interval_hours || ' hours')::interval");
            })
            ->get();

        $totalFetched = 0;
        $sourcesCount = 0;
        $errors       = [];

        foreach ($dueSources as $source) {
            try {
                $items = $this->fetchFromSource($source);
                $totalFetched += count($items);
                $sourcesCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'source' => $source->name,
                    'error'  => $e->getMessage(),
                ];
                Log::error('Failed to fetch news source', [
                    'source_id' => $source->id,
                    'name'      => $source->name,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        Log::info('News fetch cycle completed', [
            'sources'  => $sourcesCount,
            'fetched'  => $totalFetched,
            'errors'   => count($errors),
        ]);

        return [
            'fetched' => $totalFetched,
            'sources' => $sourcesCount,
            'errors'  => $errors,
        ];
    }

    /**
     * ดึงข่าวจาก source เฉพาะ
     *
     * @return BlogNewsItem[]
     */
    public function fetchFromSource(BlogNewsSource $source): array
    {
        $feedUrl = $source->feed_url ?? $source->url;

        if (empty($feedUrl)) {
            throw new \RuntimeException("ไม่พบ Feed URL สำหรับ source: {$source->name}");
        }

        Log::info('Fetching news from source', [
            'source_id' => $source->id,
            'name'      => $source->name,
            'url'       => $feedUrl,
        ]);

        $feedItems  = $this->parseRssFeed($feedUrl);
        $maxItems   = config('blog.news.max_items_per_source', 20);
        $feedItems  = array_slice($feedItems, 0, $maxItems);
        $savedItems = [];

        foreach ($feedItems as $item) {
            // ข้าม item ที่มี URL ซ้ำ
            $exists = BlogNewsItem::where('source_id', $source->id)
                ->where('url', $item['url'])
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                $newsItem = BlogNewsItem::create([
                    'source_id'        => $source->id,
                    'title'            => mb_substr($item['title'], 0, 500),
                    'url'              => mb_substr($item['url'], 0, 500),
                    'original_content' => $item['content'] ?? null,
                    'image_url'        => $item['image_url'] ?? null,
                    'category_id'      => $source->category_id,
                    'status'           => 'fetched',
                    'published_at'     => $item['published_at'] ?? now(),
                    'fetched_at'       => now(),
                ]);

                $savedItems[] = $newsItem;

                // Auto-summarize ถ้าเปิดใช้งาน
                if (config('blog.news.auto_summarize', true) && !empty($item['content'])) {
                    try {
                        $this->summarizeNewsItem($newsItem);
                    } catch (\Exception $e) {
                        Log::warning('Auto-summarize failed', [
                            'item_id' => $newsItem->id,
                            'error'   => $e->getMessage(),
                        ]);
                    }
                }

            } catch (\Exception $e) {
                Log::warning('Failed to save news item', [
                    'title' => $item['title'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // อัปเดต source
        $source->update([
            'last_fetched_at'      => now(),
            'total_items_fetched'  => $source->total_items_fetched + count($savedItems),
        ]);

        Log::info('Source fetch completed', [
            'source_id'  => $source->id,
            'new_items'  => count($savedItems),
            'total_feed' => count($feedItems),
        ]);

        return $savedItems;
    }

    /* ====================================================================
     *  RSS/Atom Parsing
     * ==================================================================== */

    /**
     * Parse RSS/Atom feed จาก URL
     *
     * @return array<array{title: string, url: string, content: string|null,
     *                      image_url: string|null, published_at: string|null}>
     */
    private function parseRssFeed(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; BlogNewsBot/1.0)',
                'Accept'     => 'application/rss+xml, application/xml, text/xml, application/atom+xml',
            ])
                ->timeout(30)
                ->get($url);

            if ($response->failed()) {
                throw new \RuntimeException("HTTP {$response->status()} fetching feed: {$url}");
            }

            $xml = $response->body();

            // ปิด libxml errors เพื่อจัดการเอง
            $prevUseErrors = libxml_use_internal_errors(true);
            $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($feed === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                libxml_use_internal_errors($prevUseErrors);

                $errorMsg = !empty($errors) ? $errors[0]->message : 'Invalid XML';
                throw new \RuntimeException("Failed to parse RSS feed: {$errorMsg}");
            }

            libxml_use_internal_errors($prevUseErrors);

            // ตรวจสอบว่าเป็น RSS หรือ Atom
            if (isset($feed->channel)) {
                return $this->parseRssItems($feed);
            } elseif ($feed->getName() === 'feed') {
                return $this->parseAtomItems($feed);
            }

            throw new \RuntimeException('ไม่สามารถระบุรูปแบบ feed ได้ (RSS/Atom)');

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \RuntimeException("การเชื่อมต่อ feed หมดเวลา: {$url}");
        }
    }

    /**
     * Parse RSS 2.0 items
     */
    private function parseRssItems(\SimpleXMLElement $feed): array
    {
        $items = [];

        foreach ($feed->channel->item ?? [] as $item) {
            $content  = '';
            $imageUrl = null;

            // ดึง content จาก content:encoded หรือ description
            $namespaces = $item->getNameSpaces(true);
            if (isset($namespaces['content'])) {
                $contentNs = $item->children($namespaces['content']);
                $content   = (string) ($contentNs->encoded ?? '');
            }

            if (empty($content)) {
                $content = (string) ($item->description ?? '');
            }

            // ดึงรูปจาก enclosure หรือ media:content
            if (isset($item->enclosure)) {
                $enclosureType = (string) $item->enclosure['type'];
                if (str_starts_with($enclosureType, 'image/')) {
                    $imageUrl = (string) $item->enclosure['url'];
                }
            }

            if ($imageUrl === null && isset($namespaces['media'])) {
                $media = $item->children($namespaces['media']);
                if (isset($media->content)) {
                    $imageUrl = (string) $media->content['url'];
                } elseif (isset($media->thumbnail)) {
                    $imageUrl = (string) $media->thumbnail['url'];
                }
            }

            // Fallback: ดึงรูปแรกจาก content
            if ($imageUrl === null && !empty($content)) {
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $imgMatch)) {
                    $imageUrl = $imgMatch[1];
                }
            }

            // Parse published date
            $publishedAt = null;
            $pubDate     = (string) ($item->pubDate ?? '');
            if (!empty($pubDate)) {
                try {
                    $publishedAt = Carbon::parse($pubDate)->toDateTimeString();
                } catch (\Exception) {
                    $publishedAt = null;
                }
            }

            $items[] = [
                'title'        => (string) ($item->title ?? 'Untitled'),
                'url'          => (string) ($item->link ?? ''),
                'content'      => $content,
                'image_url'    => $imageUrl,
                'published_at' => $publishedAt,
            ];
        }

        return $items;
    }

    /**
     * Parse Atom feed items
     */
    private function parseAtomItems(\SimpleXMLElement $feed): array
    {
        $items = [];

        foreach ($feed->entry ?? [] as $entry) {
            // ดึง link -- Atom ใช้ rel="alternate"
            $url = '';
            foreach ($entry->link ?? [] as $link) {
                $rel = (string) ($link['rel'] ?? 'alternate');
                if ($rel === 'alternate' || $rel === '') {
                    $url = (string) $link['href'];
                    break;
                }
            }

            if (empty($url) && isset($entry->link['href'])) {
                $url = (string) $entry->link['href'];
            }

            // ดึง content
            $content = (string) ($entry->content ?? $entry->summary ?? '');

            // ดึง image จาก media namespace
            $imageUrl   = null;
            $namespaces = $entry->getNameSpaces(true);
            if (isset($namespaces['media'])) {
                $media = $entry->children($namespaces['media']);
                if (isset($media->content)) {
                    $imageUrl = (string) $media->content['url'];
                } elseif (isset($media->thumbnail)) {
                    $imageUrl = (string) $media->thumbnail['url'];
                }
            }

            // Parse published date
            $publishedAt = null;
            $dateStr     = (string) ($entry->published ?? $entry->updated ?? '');
            if (!empty($dateStr)) {
                try {
                    $publishedAt = Carbon::parse($dateStr)->toDateTimeString();
                } catch (\Exception) {
                    $publishedAt = null;
                }
            }

            $items[] = [
                'title'        => (string) ($entry->title ?? 'Untitled'),
                'url'          => $url,
                'content'      => $content,
                'image_url'    => $imageUrl,
                'published_at' => $publishedAt,
            ];
        }

        return $items;
    }

    /* ====================================================================
     *  AI Summarization
     * ==================================================================== */

    /**
     * สรุปข่าวด้วย AI
     */
    public function summarizeNewsItem(BlogNewsItem $item): BlogNewsItem
    {
        $content = $item->original_content;

        if (empty($content)) {
            // ลองดึงเนื้อหาจาก URL
            $extracted = $this->ai->extractContentFromUrl($item->url);
            if ($extracted['success'] ?? false) {
                $content = $extracted['content'];
                $item->update(['original_content' => $content]);
            }
        }

        if (empty($content)) {
            Log::warning('No content to summarize for news item', ['item_id' => $item->id]);
            return $item;
        }

        // ตัดเนื้อหาที่ยาวเกินไป
        $truncated = mb_substr(strip_tags($content), 0, 5000);

        $result = $this->ai->summarizeContent($truncated, 'paragraph');

        $item->update([
            'ai_summary' => $result['summary'] ?? '',
            'status'     => 'summarized',
        ]);

        return $item->fresh();
    }

    /* ====================================================================
     *  Publishing
     * ==================================================================== */

    /**
     * สร้าง blog post จาก news item ด้วย AI
     *
     * @param  array  $options  category_id, auto_publish, enhance_with_ai
     */
    public function publishAsPost(BlogNewsItem $item, array $options = []): BlogPost
    {
        $categoryId    = $options['category_id'] ?? $item->category_id;
        $autoPublish   = $options['auto_publish'] ?? config('blog.news.auto_publish', false);
        $enhanceWithAi = $options['enhance_with_ai'] ?? true;

        $title   = $item->title;
        $content = $item->ai_summary ?? $item->original_content ?? '';

        // ปรับปรุงเนื้อหาด้วย AI ถ้าเปิดใช้งาน
        if ($enhanceWithAi && !empty($content)) {
            try {
                $enhanced = $this->ai->rewriteContent($content, 'seo_optimize');
                $content  = $enhanced['content'] ?? $content;
            } catch (\Exception $e) {
                Log::warning('Failed to enhance news content with AI', [
                    'item_id' => $item->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // สร้าง meta tags
        $metaTags = [];
        try {
            $metaTags = $this->ai->generateMetaTags($content, $title);
        } catch (\Exception $e) {
            Log::warning('Failed to generate meta tags for news post', [
                'item_id' => $item->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // เพิ่ม attribution
        $sourceName = $item->source->name ?? 'แหล่งข่าวภายนอก';
        $sourceUrl  = $item->url;
        $attribution = "<p class=\"text-muted small mt-4\">"
            . "<em>ที่มา: <a href=\"{$sourceUrl}\" target=\"_blank\" rel=\"noopener nofollow\">{$sourceName}</a></em>"
            . "</p>";

        $fullContent = $content . "\n" . $attribution;

        // สร้าง slug
        $slug = Str::slug($title);
        if (empty($slug)) {
            $slug = 'news-' . $item->id . '-' . time();
        }

        // ป้องกัน slug ซ้ำ
        $originalSlug = $slug;
        $counter      = 1;
        while (BlogPost::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $plainText = strip_tags($fullContent);
        $wordCount = str_word_count($plainText);

        $post = BlogPost::create([
            'title'            => $title,
            'slug'             => $slug,
            'excerpt'          => mb_substr(strip_tags($content), 0, 300),
            'content'          => $fullContent,
            'featured_image'   => $item->image_url,
            'category_id'      => $categoryId,
            'author_id'        => auth()->id(),
            'status'           => $autoPublish ? 'published' : 'draft',
            'meta_title'       => $metaTags['meta_title'] ?? mb_substr($title, 0, 60),
            'meta_description' => $metaTags['meta_description'] ?? mb_substr(strip_tags($content), 0, 160),
            'focus_keyword'    => mb_substr($title, 0, 255),
            'word_count'       => $wordCount,
            'reading_time'     => max(1, (int) ceil($wordCount / 200)),
            'ai_generated'     => true,
            'ai_provider'      => $this->ai->getProvider()->getProviderName(),
            'ai_model'         => $this->ai->getProvider()->getModelName(),
            'published_at'     => $autoPublish ? now() : null,
        ]);

        // อัปเดต news item
        $item->update([
            'status'  => 'published',
            'post_id' => $post->id,
        ]);

        Log::info('News item published as blog post', [
            'item_id' => $item->id,
            'post_id' => $post->id,
            'title'   => $title,
        ]);

        return $post;
    }

    /* ====================================================================
     *  Trending / Search
     * ==================================================================== */

    /**
     * ค้นหาข่าวจากเว็บ
     */
    public function searchNews(string $topic, string $language = 'th'): array
    {
        $query = $language === 'th'
            ? "{$topic} ข่าว ล่าสุด"
            : "{$topic} news latest";

        return $this->ai->searchWeb($query);
    }

    /**
     * ดึง trending topics สำหรับหมวดหมู่
     */
    public function getTrendingTopics(BlogCategory $category): array
    {
        // ดึงข่าวล่าสุดในหมวดหมู่
        $recentItems = BlogNewsItem::where('category_id', $category->id)
            ->where('fetched_at', '>=', now()->subDays(7))
            ->orderByDesc('fetched_at')
            ->limit(20)
            ->get();

        if ($recentItems->isEmpty()) {
            return [
                'topics'    => [],
                'message'   => 'ไม่พบข่าวล่าสุดในหมวดหมู่นี้',
                'source'    => 'none',
            ];
        }

        // ส่งหัวข้อข่าวให้ AI วิเคราะห์ trend
        $titles = $recentItems->pluck('title')->implode("\n- ");

        $prompt = <<<PROMPT
วิเคราะห์หัวข้อข่าวต่อไปนี้ในหมวดหมู่ "{$category->name}" และหา trending topics:

- {$titles}

ส่งกลับเป็น JSON:
{
  "topics": [
    {
      "topic": "หัวข้อที่กำลังเป็นที่นิยม",
      "relevance_score": 85,
      "article_suggestion": "แนะนำหัวข้อบทความที่ควรเขียน",
      "keywords": ["keyword1", "keyword2"]
    }
  ],
  "overall_trend": "สรุปแนวโน้มโดยรวม"
}
PROMPT;

        try {
            $provider = $this->ai->getProvider();
            $result   = $provider->generateContent($prompt, [
                'system_prompt' => 'คุณเป็นนักวิเคราะห์แนวโน้มข่าวสาร วิเคราะห์และหา trending topics ตอบกลับเป็น JSON เท่านั้น',
                'json_mode'     => true,
            ]);

            $parsed = json_decode($result['content'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // พยายาม parse JSON จากเนื้อหา
                $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($result['content']));
                $cleaned = preg_replace('/\s*```$/i', '', $cleaned);
                $parsed  = json_decode($cleaned, true) ?? [];
            }

            return [
                'topics'        => $parsed['topics'] ?? [],
                'overall_trend' => $parsed['overall_trend'] ?? '',
                'source'        => 'ai_analysis',
                'analyzed_items'=> $recentItems->count(),
            ];

        } catch (\Exception $e) {
            Log::warning('Failed to analyze trending topics', [
                'category' => $category->name,
                'error'    => $e->getMessage(),
            ]);

            return [
                'topics'  => [],
                'message' => 'ไม่สามารถวิเคราะห์ trending topics ได้',
                'error'   => $e->getMessage(),
            ];
        }
    }
}
