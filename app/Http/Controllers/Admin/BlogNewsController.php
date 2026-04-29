<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogNewsItem;
use App\Models\BlogNewsSource;
use App\Models\BlogPost;
use App\Services\Blog\AiContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlogNewsController extends Controller
{
    public function __construct(private AiContentService $aiService) {}

    /* ================================================================
     *  INDEX -- list news sources
     * ================================================================ */
    public function index(Request $request)
    {
        $sources = BlogNewsSource::with('category')
            ->withCount('items')
            ->when($request->search, fn ($q, $v) => $q->where('name', 'ilike', "%{$v}%"))
            ->when($request->has('is_active') && $request->is_active !== '', fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $categories = BlogCategory::active()->orderBy('name')->get();

        return view('admin.blog.news.index', compact('sources', 'categories'));
    }

    /* ================================================================
     *  STORE SOURCE
     * ================================================================ */
    public function storeSource(Request $request)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'url'                  => 'required|url|max:2000',
            'feed_url'             => 'required|url|max:2000',
            'feed_type'            => 'nullable|string|in:rss,atom,json',
            'category_id'          => 'nullable|exists:blog_categories,id',
            'language'             => 'nullable|string|max:10',
            'is_active'            => 'nullable|boolean',
            'auto_publish'         => 'nullable|boolean',
            'fetch_interval_hours' => 'nullable|integer|min:1|max:168',
        ]);

        $validated['is_active']            = $request->boolean('is_active', true);
        $validated['auto_publish']         = $request->boolean('auto_publish', false);
        $validated['fetch_interval_hours'] = $validated['fetch_interval_hours'] ?? 6;
        $validated['language']             = $validated['language'] ?? 'th';

        BlogNewsSource::create($validated);

        return redirect()
            ->route('admin.blog.news.index')
            ->with('success', 'เพิ่มแหล่งข่าวเรียบร้อยแล้ว');
    }

    /* ================================================================
     *  UPDATE SOURCE
     * ================================================================ */
    public function updateSource(Request $request, $id)
    {
        $source = BlogNewsSource::findOrFail($id);

        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'url'                  => 'required|url|max:2000',
            'feed_url'             => 'required|url|max:2000',
            'feed_type'            => 'nullable|string|in:rss,atom,json',
            'category_id'          => 'nullable|exists:blog_categories,id',
            'language'             => 'nullable|string|max:10',
            'is_active'            => 'nullable|boolean',
            'auto_publish'         => 'nullable|boolean',
            'fetch_interval_hours' => 'nullable|integer|min:1|max:168',
        ]);

        $validated['is_active']    = $request->boolean('is_active', true);
        $validated['auto_publish'] = $request->boolean('auto_publish', false);

        $source->update($validated);

        return redirect()
            ->route('admin.blog.news.index')
            ->with('success', 'อัพเดทแหล่งข่าวเรียบร้อยแล้ว');
    }

    /* ================================================================
     *  DELETE SOURCE
     * ================================================================ */
    public function deleteSource($id)
    {
        $source = BlogNewsSource::findOrFail($id);

        // Delete all items from this source
        $source->items()->delete();
        $source->delete();

        return redirect()
            ->route('admin.blog.news.index')
            ->with('success', 'ลบแหล่งข่าวเรียบร้อยแล้ว');
    }

    /* ================================================================
     *  FETCH NOW -- manually trigger fetch for one source
     * ================================================================ */
    public function fetchNow($id): JsonResponse
    {
        $source = BlogNewsSource::findOrFail($id);

        try {
            $count = $this->fetchFeedItems($source);

            return response()->json([
                'success' => true,
                'message' => "ดึงข่าวจาก {$source->name} ได้ {$count} รายการ",
                'count'   => $count,
            ]);
        } catch (\Exception $e) {
            Log::error("News fetch failed for source {$source->id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการดึงข่าว: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ================================================================
     *  FETCH ALL -- trigger fetch for all due sources
     * ================================================================ */
    public function fetchAll(): JsonResponse
    {
        $sources    = BlogNewsSource::due()->get();
        $totalItems = 0;
        $errors     = [];

        foreach ($sources as $source) {
            try {
                $totalItems += $this->fetchFeedItems($source);
            } catch (\Exception $e) {
                Log::error("News fetch failed for source {$source->id}: " . $e->getMessage());
                $errors[] = "{$source->name}: {$e->getMessage()}";
            }
        }

        $message = "ดึงข่าวจาก {$sources->count()} แหล่ง ได้ทั้งหมด {$totalItems} รายการ";
        if (!empty($errors)) {
            $message .= ' (มี ' . count($errors) . ' แหล่งที่เกิดข้อผิดพลาด)';
        }

        return response()->json([
            'success' => empty($errors),
            'message' => $message,
            'total'   => $totalItems,
            'errors'  => $errors,
        ]);
    }

    /* ================================================================
     *  ITEMS -- list news items
     * ================================================================ */
    public function items(Request $request)
    {
        $items = BlogNewsItem::with(['source', 'category'])
            ->when($request->source_id, fn ($q, $v) => $q->where('source_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('published_at', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->whereDate('published_at', '<=', $v))
            ->when($request->search, fn ($q, $v) => $q->where('title', 'ilike', "%{$v}%"))
            ->orderBy('published_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $sources    = BlogNewsSource::orderBy('name')->get();
        $categories = BlogCategory::active()->orderBy('name')->get();

        return view('admin.blog.news.items', compact('items', 'sources', 'categories'));
    }

    /* ================================================================
     *  ITEM SHOW
     * ================================================================ */
    public function itemShow($id)
    {
        $item = BlogNewsItem::with(['source', 'category', 'post'])->findOrFail($id);

        return view('admin.blog.news.item-show', compact('item'));
    }

    /* ================================================================
     *  SUMMARIZE ITEM -- AI summarization
     * ================================================================ */
    public function summarizeItem($id): JsonResponse
    {
        $item = BlogNewsItem::findOrFail($id);

        if (empty($item->original_content)) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบเนื้อหาต้นฉบับสำหรับสรุป',
            ], 422);
        }

        try {
            $result = $this->aiService->summarizeContent(
                content: $item->original_content,
            );

            $item->update([
                'ai_summary' => $result['content'] ?? $result['summary'] ?? '',
                'status'     => $item->status === 'fetched' ? 'summarized' : $item->status,
            ]);

            return response()->json([
                'success'    => true,
                'ai_summary' => $item->ai_summary,
                'message'    => 'สรุปข่าวด้วย AI เรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการสรุปข่าว: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ================================================================
     *  PUBLISH ITEM -- convert to blog post
     * ================================================================ */
    public function publishItem($id)
    {
        $item = BlogNewsItem::with('source')->findOrFail($id);

        $post = new BlogPost();
        $post->title       = $item->title;
        $post->content     = $item->ai_summary ?: $item->original_content;
        $post->excerpt     = Str::limit(strip_tags($item->ai_summary ?: $item->original_content), 300);
        $post->category_id = $item->category_id ?: $item->source?->category_id;
        $post->author_id   = Auth::guard('admin')->id();
        $post->status      = 'draft';
        $post->generateSlug();
        $post->calculateWordCount();
        $post->calculateReadingTime();
        $post->generateTableOfContents();

        if ($item->image_url) {
            $post->featured_image = $item->image_url;
        }

        $post->save();

        // Link back
        $item->update([
            'status'  => 'published',
            'post_id' => $post->id,
        ]);

        return redirect()
            ->route('admin.blog.posts.edit', $post->id)
            ->with('success', 'สร้างบทความจากข่าวเรียบร้อยแล้ว -- กรุณาตรวจทานก่อนเผยแพร่');
    }

    /* ================================================================
     *  DISMISS ITEM
     * ================================================================ */
    public function dismissItem($id): JsonResponse
    {
        $item = BlogNewsItem::findOrFail($id);
        $item->update(['status' => 'dismissed']);

        return response()->json([
            'success' => true,
            'message' => 'ซ่อนรายการข่าวแล้ว',
        ]);
    }

    /* ================================================================
     *  BULK ACTION
     * ================================================================ */
    public function bulkAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:summarize,publish,dismiss,delete',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:blog_news_items,id',
        ]);

        $ids    = $validated['ids'];
        $action = $validated['action'];
        $count  = count($ids);

        switch ($action) {
            case 'summarize':
                $adminId   = Auth::guard('admin')->id();
                $processed = 0;
                $errors    = 0;

                foreach (BlogNewsItem::whereIn('id', $ids)->get() as $item) {
                    if (empty($item->original_content)) {
                        $errors++;
                        continue;
                    }

                    try {
                        $result = $this->aiService->summarizeContent(
                            content: $item->original_content,
                        );

                        $item->update([
                            'ai_summary' => $result['content'] ?? $result['summary'] ?? '',
                            'status'     => $item->status === 'fetched' ? 'summarized' : $item->status,
                        ]);
                        $processed++;
                    } catch (\Exception $e) {
                        Log::error("Bulk summarize failed for item {$item->id}: " . $e->getMessage());
                        $errors++;
                    }
                }

                $message = "สรุป {$processed} ข่าวเรียบร้อยแล้ว";
                if ($errors > 0) {
                    $message .= " (ล้มเหลว {$errors} รายการ)";
                }
                break;

            case 'publish':
                $adminId = Auth::guard('admin')->id();
                $processed = 0;

                foreach (BlogNewsItem::whereIn('id', $ids)->get() as $item) {
                    $post = new BlogPost();
                    $post->title       = $item->title;
                    $post->content     = $item->ai_summary ?: $item->original_content;
                    $post->excerpt     = Str::limit(strip_tags($item->ai_summary ?: $item->original_content), 300);
                    $post->category_id = $item->category_id ?: $item->source?->category_id;
                    $post->author_id   = $adminId;
                    $post->status      = 'draft';
                    $post->generateSlug();
                    $post->calculateWordCount();
                    $post->calculateReadingTime();
                    $post->save();

                    $item->update([
                        'status'  => 'published',
                        'post_id' => $post->id,
                    ]);
                    $processed++;
                }

                $message = "สร้าง {$processed} บทความจากข่าวเรียบร้อยแล้ว (เป็นฉบับร่าง)";
                break;

            case 'dismiss':
                BlogNewsItem::whereIn('id', $ids)->update(['status' => 'dismissed']);
                $message = "ซ่อน {$count} รายการข่าวแล้ว";
                break;

            case 'delete':
                BlogNewsItem::whereIn('id', $ids)->delete();
                $message = "ลบ {$count} รายการข่าวแล้ว";
                break;

            default:
                return response()->json(['success' => false, 'message' => 'การกระทำไม่ถูกต้อง'], 422);
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    /* ================================================================
     *  PRIVATE HELPERS
     * ================================================================ */

    /**
     * Fetch RSS/Atom feed items for a given source.
     */
    private function fetchFeedItems(BlogNewsSource $source): int
    {
        $response = Http::timeout(30)->get($source->feed_url);

        if (!$response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} fetching {$source->feed_url}");
        }

        $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            throw new \RuntimeException('ไม่สามารถแปลง XML ของฟีดได้');
        }

        $items = $this->parseRssFeed($xml);
        $count = 0;

        foreach ($items as $feedItem) {
            // Skip duplicates by URL
            $exists = BlogNewsItem::where('source_id', $source->id)
                ->where('url', $feedItem['url'])
                ->exists();

            if ($exists) {
                continue;
            }

            BlogNewsItem::create([
                'source_id'        => $source->id,
                'title'            => Str::limit($feedItem['title'], 500),
                'url'              => $feedItem['url'],
                'original_content' => $feedItem['content'] ?? null,
                'image_url'        => $feedItem['image'] ?? null,
                'category_id'      => $source->category_id,
                'status'           => 'fetched',
                'published_at'     => $feedItem['published_at'] ?? now(),
                'fetched_at'       => now(),
            ]);

            $count++;
        }

        // Update source metadata
        $source->update([
            'last_fetched_at'     => now(),
            'total_items_fetched' => $source->total_items_fetched + $count,
        ]);

        return $count;
    }

    /**
     * Parse RSS or Atom feed XML into a normalised array.
     */
    private function parseRssFeed(\SimpleXMLElement $xml): array
    {
        $items = [];

        // RSS 2.0 format
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $entry) {
                $items[] = [
                    'title'        => (string) $entry->title,
                    'url'          => (string) $entry->link,
                    'content'      => (string) ($entry->children('content', true)->encoded ?? $entry->description ?? ''),
                    'image'        => $this->extractImageFromContent((string) ($entry->description ?? '')),
                    'published_at' => !empty((string) $entry->pubDate) ? date('Y-m-d H:i:s', strtotime((string) $entry->pubDate)) : null,
                ];
            }
        }

        // Atom format
        if (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $link = '';
                foreach ($entry->link as $l) {
                    if ((string) $l['rel'] === 'alternate' || empty((string) $l['rel'])) {
                        $link = (string) $l['href'];
                        break;
                    }
                }

                $items[] = [
                    'title'        => (string) $entry->title,
                    'url'          => $link,
                    'content'      => (string) ($entry->content ?? $entry->summary ?? ''),
                    'image'        => $this->extractImageFromContent((string) ($entry->content ?? $entry->summary ?? '')),
                    'published_at' => !empty((string) $entry->published) ? date('Y-m-d H:i:s', strtotime((string) $entry->published)) : null,
                ];
            }
        }

        return $items;
    }

    /**
     * Extract the first image URL from HTML content.
     */
    private function extractImageFromContent(string $html): ?string
    {
        if (empty($html)) {
            return null;
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
