<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogCtaButton;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\Blog\AiContentService;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogPostController extends Controller
{
    public function __construct(private AiContentService $aiService) {}

    /* ================================================================
     *  INDEX -- list posts with filters & stats
     * ================================================================ */
    public function index(Request $request)
    {
        $posts = BlogPost::with(['category', 'tags'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->search, fn ($q, $v) => $q->search($v))
            ->when($request->boolean('is_featured'), fn ($q) => $q->where('is_featured', true))
            ->when($request->boolean('is_affiliate'), fn ($q) => $q->where('is_affiliate_post', true))
            ->when($request->sort, function ($q, $sort) {
                return match ($sort) {
                    'oldest'     => $q->orderBy('created_at', 'asc'),
                    'views'      => $q->orderBy('view_count', 'desc'),
                    'title'      => $q->orderBy('title', 'asc'),
                    'updated'    => $q->orderBy('updated_at', 'desc'),
                    default      => $q->orderBy('created_at', 'desc'),
                };
            }, fn ($q) => $q->orderBy('created_at', 'desc'))
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total'      => BlogPost::count(),
            'published'  => BlogPost::where('status', 'published')->count(),
            'draft'      => BlogPost::where('status', 'draft')->count(),
            'scheduled'  => BlogPost::where('status', 'scheduled')->count(),
            'total_views' => (int) BlogPost::sum('view_count'),
        ];

        $categories = BlogCategory::active()->orderBy('name')->get();

        return view('admin.blog.posts.index', compact('posts', 'stats', 'categories'));
    }

    /* ================================================================
     *  CREATE / STORE
     * ================================================================ */
    public function create()
    {
        $categories = BlogCategory::active()->orderBy('sort_order')->orderBy('name')->get();
        $tags       = BlogTag::orderBy('name')->get();
        $ctaButtons = BlogCtaButton::active()->orderBy('name')->get();

        return view('admin.blog.posts.create', compact('categories', 'tags', 'ctaButtons'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:500',
            'content'          => 'required|string',
            'excerpt'          => 'nullable|string|max:1000',
            'category_id'      => 'required|exists:blog_categories,id',
            'status'           => 'required|in:draft,scheduled,published',
            'visibility'       => 'nullable|in:public,private,password',
            'post_password'    => 'nullable|string|max:100',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image'         => 'nullable|image|max:2048',
            'focus_keyword'    => 'nullable|string|max:255',
            'secondary_keywords' => 'nullable|string|max:500',
            'canonical_url'    => 'nullable|url|max:500',
            'schema_type'      => 'nullable|string|max:50',
            'featured_image'   => 'nullable|image|max:5120',
            'is_featured'      => 'nullable|boolean',
            'is_affiliate_post' => 'nullable|boolean',
            'allow_comments'   => 'nullable|boolean',
            'tags'             => 'nullable|array',
            'tags.*'           => 'string|max:100',
            'scheduled_at'     => 'nullable|date|after:now',
        ]);

        $post = new BlogPost();
        $post->fill($validated);
        $post->author_id = Auth::guard('admin')->id();

        // Slug
        $post->title = $validated['title'];
        $post->generateSlug();

        // Reading time & word count
        $post->calculateWordCount();
        $post->calculateReadingTime();

        // Table of contents
        $post->generateTableOfContents();

        // Secondary keywords as array
        if (!empty($validated['secondary_keywords'])) {
            $post->secondary_keywords = array_map('trim', explode(',', $validated['secondary_keywords']));
        }

        // Defer image uploads until after the row exists so paths can be
        // scoped under blog/posts/{id}/…
        $featuredFile = $request->hasFile('featured_image') ? $request->file('featured_image') : null;
        $ogFile       = $request->hasFile('og_image') ? $request->file('og_image') : null;

        // Publication timestamps
        if ($validated['status'] === 'published') {
            $post->published_at = now();
        }
        if ($validated['status'] === 'scheduled' && !empty($validated['scheduled_at'])) {
            $post->scheduled_at = $validated['scheduled_at'];
        }

        $post->is_featured      = $request->boolean('is_featured');
        $post->is_affiliate_post = $request->boolean('is_affiliate_post');
        $post->allow_comments   = $request->boolean('allow_comments', true);

        $post->save();

        if ($featuredFile || $ogFile) {
            $media = app(R2MediaService::class);
            $authorId = (int) ($post->author_id ?? Auth::id());
            try {
                if ($featuredFile) {
                    $upload = $media->uploadBlogImage($authorId, (int) $post->id, $featuredFile);
                    $post->featured_image = $upload->key;
                }
                if ($ogFile) {
                    $upload = $media->uploadBlogImage($authorId, (int) $post->id, $ogFile);
                    $post->og_image = $upload->key;
                }
                $post->save();
            } catch (InvalidMediaFileException $e) {
                // Post row was already saved without the image — admin can
                // re-upload from the edit screen. Surface the rejection to
                // the form errors so the admin sees why.
                return back()
                    ->withInput()
                    ->withErrors(['featured_image' => $e->getMessage()]);
            }
        }

        // Sync tags
        $this->syncTags($post, $request->input('tags', []));

        // Update category post count
        if ($post->category) {
            $post->category->incrementPostCount();
        }

        return redirect()
            ->route('admin.blog.posts.index')
            ->with('success', 'สร้างบทความเรียบร้อยแล้ว');
    }

    /* ================================================================
     *  EDIT / UPDATE
     * ================================================================ */
    public function edit($id)
    {
        $post = BlogPost::with('tags')->findOrFail($id);
        $categories = BlogCategory::active()->orderBy('sort_order')->orderBy('name')->get();
        $tags       = BlogTag::orderBy('name')->get();
        $ctaButtons = BlogCtaButton::active()->orderBy('name')->get();

        return view('admin.blog.posts.edit', compact('post', 'categories', 'tags', 'ctaButtons'));
    }

    public function update(Request $request, $id)
    {
        $post = BlogPost::findOrFail($id);

        $validated = $request->validate([
            'title'            => 'required|string|max:500',
            'content'          => 'required|string',
            'excerpt'          => 'nullable|string|max:1000',
            'category_id'      => 'required|exists:blog_categories,id',
            'status'           => 'required|in:draft,scheduled,published',
            'visibility'       => 'nullable|in:public,private,password',
            'post_password'    => 'nullable|string|max:100',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image'         => 'nullable|image|max:2048',
            'focus_keyword'    => 'nullable|string|max:255',
            'secondary_keywords' => 'nullable|string|max:500',
            'canonical_url'    => 'nullable|url|max:500',
            'schema_type'      => 'nullable|string|max:50',
            'featured_image'   => 'nullable|image|max:5120',
            'is_featured'      => 'nullable|boolean',
            'is_affiliate_post' => 'nullable|boolean',
            'allow_comments'   => 'nullable|boolean',
            'tags'             => 'nullable|array',
            'tags.*'           => 'string|max:100',
            'scheduled_at'     => 'nullable|date',
        ]);

        $oldCategoryId = $post->category_id;

        $post->fill($validated);

        // Re-generate slug only if title changed
        if ($post->isDirty('title')) {
            $post->generateSlug();
        }

        // Recalculate reading metrics
        $post->calculateWordCount();
        $post->calculateReadingTime();
        $post->generateTableOfContents();

        // Secondary keywords
        if (!empty($validated['secondary_keywords'])) {
            $post->secondary_keywords = array_map('trim', explode(',', $validated['secondary_keywords']));
        } else {
            $post->secondary_keywords = null;
        }

        $media = app(R2MediaService::class);
        $authorId = (int) ($post->author_id ?? Auth::id());

        // Featured image — delete old object on R2 before replacing so the
        // bucket doesn't accumulate orphans when an admin swaps the hero
        // image of a long-lived post. CDN cache is purged async by the
        // R2MediaService delete pipeline.
        if ($request->hasFile('featured_image')) {
            if ($post->featured_image) {
                try { $media->delete($post->featured_image); } catch (\Throwable) {}
            }
            try {
                $upload = $media->uploadBlogImage($authorId, (int) $post->id, $request->file('featured_image'));
                $post->featured_image = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withInput()->withErrors(['featured_image' => $e->getMessage()]);
            }
        }

        // OG image — same treatment.
        if ($request->hasFile('og_image')) {
            if ($post->og_image) {
                try { $media->delete($post->og_image); } catch (\Throwable) {}
            }
            try {
                $upload = $media->uploadBlogImage($authorId, (int) $post->id, $request->file('og_image'));
                $post->og_image = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withInput()->withErrors(['og_image' => $e->getMessage()]);
            }
        }

        // Publication timestamps
        if ($validated['status'] === 'published' && !$post->published_at) {
            $post->published_at = now();
        }
        if ($validated['status'] === 'scheduled' && !empty($validated['scheduled_at'])) {
            $post->scheduled_at = $validated['scheduled_at'];
        }

        $post->is_featured       = $request->boolean('is_featured');
        $post->is_affiliate_post = $request->boolean('is_affiliate_post');
        $post->allow_comments    = $request->boolean('allow_comments', true);
        $post->last_modified_at  = now();

        $post->save();

        // Sync tags
        $this->syncTags($post, $request->input('tags', []));

        // Update category post counts if category changed
        if ($oldCategoryId !== $post->category_id) {
            BlogCategory::find($oldCategoryId)?->decrementPostCount();
            $post->category?->incrementPostCount();
        }

        return redirect()
            ->route('admin.blog.posts.index')
            ->with('success', 'อัพเดทบทความเรียบร้อยแล้ว');
    }

    /* ================================================================
     *  DESTROY -- soft delete
     * ================================================================ */
    public function destroy($id)
    {
        $post = BlogPost::findOrFail($id);

        $post->category?->decrementPostCount();
        $post->delete();

        return redirect()
            ->route('admin.blog.posts.index')
            ->with('success', 'ลบบทความเรียบร้อยแล้ว');
    }

    /* ================================================================
     *  TOGGLE FEATURED
     * ================================================================ */
    public function toggleFeatured($id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);
        $post->is_featured = !$post->is_featured;
        $post->save();

        return response()->json([
            'success'     => true,
            'is_featured' => $post->is_featured,
            'message'     => $post->is_featured ? 'ตั้งเป็นบทความแนะนำแล้ว' : 'ยกเลิกบทความแนะนำแล้ว',
        ]);
    }

    /* ================================================================
     *  TOGGLE STATUS (published <-> draft)
     * ================================================================ */
    public function toggleStatus($id): JsonResponse
    {
        $post = BlogPost::findOrFail($id);

        if ($post->status === 'published') {
            $post->status = 'draft';
        } else {
            $post->status       = 'published';
            $post->published_at = $post->published_at ?? now();
        }

        $post->save();

        return response()->json([
            'success' => true,
            'status'  => $post->status,
            'message' => $post->status === 'published' ? 'เผยแพร่บทความแล้ว' : 'เปลี่ยนเป็นฉบับร่างแล้ว',
        ]);
    }

    /* ================================================================
     *  BULK ACTION
     * ================================================================ */
    public function bulkAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,publish,draft',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:blog_posts,id',
        ]);

        $ids    = $validated['ids'];
        $action = $validated['action'];
        $count  = count($ids);

        switch ($action) {
            case 'delete':
                BlogPost::whereIn('id', $ids)->each(function (BlogPost $post) {
                    $post->category?->decrementPostCount();
                    $post->delete();
                });
                $message = "ลบ {$count} บทความเรียบร้อยแล้ว";
                break;

            case 'publish':
                BlogPost::whereIn('id', $ids)->update([
                    'status'       => 'published',
                    'published_at' => DB::raw('COALESCE(published_at, NOW())'),
                ]);
                $message = "เผยแพร่ {$count} บทความเรียบร้อยแล้ว";
                break;

            case 'draft':
                BlogPost::whereIn('id', $ids)->update(['status' => 'draft']);
                $message = "เปลี่ยน {$count} บทความเป็นฉบับร่างแล้ว";
                break;

            default:
                return response()->json(['success' => false, 'message' => 'การกระทำไม่ถูกต้อง'], 422);
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    /* ================================================================
     *  DUPLICATE
     * ================================================================ */
    public function duplicate($id)
    {
        $original = BlogPost::with('tags')->findOrFail($id);

        $clone = $original->replicate([
            'slug', 'view_count', 'share_count', 'published_at', 'scheduled_at',
        ]);

        $clone->title       = $original->title . ' (สำเนา)';
        $clone->status      = 'draft';
        $clone->view_count  = 0;
        $clone->share_count = 0;
        $clone->author_id   = Auth::guard('admin')->id();
        $clone->generateSlug();
        $clone->save();

        // Clone tag associations
        $clone->tags()->sync($original->tags->pluck('id'));

        return redirect()
            ->route('admin.blog.posts.edit', $clone->id)
            ->with('success', 'สำเนาบทความเรียบร้อยแล้ว');
    }

    /* ================================================================
     *  AI METHODS (return JSON for AJAX)
     * ================================================================ */

    public function aiGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword'     => 'required|string|max:255',
            'word_count'  => 'nullable|integer|min:300|max:10000',
            'tone'        => 'nullable|string|in:formal,casual,professional,friendly',
            'language'    => 'nullable|string|in:th,en',
            'include_faq' => 'nullable|boolean',
            'include_toc' => 'nullable|boolean',
        ]);

        try {
            $result = $this->aiService->generateArticle(
                keyword: $validated['keyword'],
                options: [
                    'word_count'  => $validated['word_count'] ?? 1500,
                    'tone'        => $validated['tone'] ?? 'professional',
                    'language'    => $validated['language'] ?? 'th',
                    'include_faq' => $request->boolean('include_faq', true),
                    'include_toc' => $request->boolean('include_toc', true),
                ],
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'สร้างบทความด้วย AI เรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการสร้างบทความ: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function aiRewrite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|min:50',
            'style'   => 'nullable|string|in:formal,casual,simplified,expanded',
        ]);

        try {
            $result = $this->aiService->rewriteContent(
                content: $validated['content'],
                style: $validated['style'] ?? 'formal',
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'เขียนเนื้อหาใหม่เรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function aiSummarize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|min:100',
        ]);

        try {
            $result = $this->aiService->summarizeContent(
                content: $validated['content'],
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'สรุปเนื้อหาเรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function aiSeoAnalyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_id' => 'required|exists:blog_posts,id',
        ]);

        try {
            $post   = BlogPost::findOrFail($validated['post_id']);
            $result = $this->aiService->analyzeSeo(
                content: $post->content,
                keyword: $post->focus_keyword ?? $post->title,
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'วิเคราะห์ SEO เรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function aiSuggestKeywords(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic' => 'required|string|max:255',
        ]);

        try {
            $result = $this->aiService->suggestKeywords(
                topic: $validated['topic'],
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'แนะนำคีย์เวิร์ดเรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function aiGenerateMeta(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'   => 'required|string|max:500',
            'content' => 'required|string|min:100',
        ]);

        try {
            $result = $this->aiService->generateMetaTags(
                content: $validated['content'],
                keyword: $validated['title'],
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'สร้าง Meta เรียบร้อยแล้ว',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ================================================================
     *  PRIVATE HELPERS
     * ================================================================ */

    /**
     * Sync tags -- create new tags on-the-fly if they don't exist.
     */
    private function syncTags(BlogPost $post, array $tagInputs): void
    {
        if (empty($tagInputs)) {
            $post->tags()->detach();
            return;
        }

        $tagIds = [];

        foreach ($tagInputs as $tagInput) {
            // If it's numeric, treat as existing tag ID
            if (is_numeric($tagInput)) {
                $tagIds[] = (int) $tagInput;
                continue;
            }

            // Otherwise create or find by name
            $tag = BlogTag::firstOrCreate(
                ['slug' => Str::slug($tagInput)],
                ['name' => $tagInput, 'slug' => Str::slug($tagInput)]
            );
            $tagIds[] = $tag->id;
        }

        $post->tags()->sync($tagIds);

        // Recount tags
        BlogTag::whereIn('id', $tagIds)->each(function (BlogTag $tag) {
            $tag->update(['post_count' => $tag->posts()->count()]);
        });
    }
}
