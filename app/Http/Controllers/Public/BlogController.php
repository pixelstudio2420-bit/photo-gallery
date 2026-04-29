<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogCtaButton;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    /* ================================================================
     *  index — Blog listing page
     * ================================================================ */
    public function index(Request $request)
    {
        $query = BlogPost::published()
            ->with(['category', 'tags'])
            ->orderByDesc('published_at');

        // ── Filters ──
        $currentCategory = null;
        $currentTag      = null;

        if ($request->filled('category')) {
            $currentCategory = BlogCategory::where('slug', $request->category)->first();
            if ($currentCategory) {
                $query->where('category_id', $currentCategory->id);
            }
        }

        if ($request->filled('tag')) {
            $currentTag = BlogTag::where('slug', $request->tag)->first();
            if ($currentTag) {
                $query->whereHas('tags', fn ($q) => $q->where('blog_tags.id', $currentTag->id));
            }
        }

        if ($request->filled('q')) {
            $query->search($request->q);
        }

        if ($request->boolean('featured_only')) {
            $query->featured();
        }

        $posts = $query->paginate(12)->withQueryString();

        // ── AJAX — return JSON with rendered partials ──
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'html'        => view('public.blog.partials.post-grid', compact('posts'))->render(),
                'pagination'  => $posts->links()->toHtml(),
                'total'       => $posts->total(),
                'hasMore'     => $posts->hasMorePages(),
            ]);
        }

        // ── Sidebar data ──
        $popularPosts = BlogPost::published()
            ->orderByDesc('view_count')
            ->limit(5)
            ->get();

        $categories = BlogCategory::active()
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get();

        $tags = BlogTag::orderByDesc('post_count')->limit(20)->get();

        // ── Featured hero ──
        $featuredPosts = BlogPost::published()
            ->featured()
            ->with(['category'])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        // ── SEO ──
        $seo = app(\App\Services\SeoService::class);
        $seo->title('บทความ')
            ->description('รวมบทความ เทคนิค และเคล็ดลับการถ่ายภาพ อัปเดตล่าสุด')
            ->type('website')
            ->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => url('/')],
                ['name' => 'บทความ'],
            ]);

        return view('public.blog.index', compact(
            'posts', 'categories', 'tags', 'featuredPosts',
            'popularPosts', 'currentCategory', 'currentTag'
        ));
    }

    /* ================================================================
     *  show — Single post page
     * ================================================================ */
    public function show(string $slug)
    {
        $post = BlogPost::published()
            ->with(['category', 'tags', 'author'])
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment view count
        $post->increment('view_count');

        // ── SEO ──
        $seo = app(\App\Services\SeoService::class);

        $seoTitle       = $post->meta_title ?: $post->title;
        $seoDescription = $post->meta_description ?: ($post->excerpt ?: Str::limit(strip_tags($post->content), 160));
        $seoImage       = $post->og_image ?: $post->featured_image;

        $seo->title($seoTitle)
            ->description($seoDescription)
            ->type('article');

        if ($seoImage) {
            $seo->image($seoImage);
        }

        if ($post->canonical_url) {
            $seo->canonical($post->canonical_url);
        }

        // Breadcrumbs: หน้าแรก > บทความ > Category > Post title
        $breadcrumbs = [
            ['name' => 'หน้าแรก', 'url' => url('/')],
            ['name' => 'บทความ',  'url' => route('blog.index')],
        ];
        if ($post->category) {
            $breadcrumbs[] = [
                'name' => $post->category->name,
                'url'  => route('blog.category', $post->category->slug),
            ];
        }
        $breadcrumbs[] = ['name' => $post->title];
        $seo->setBreadcrumbs($breadcrumbs);

        // Schema markup: Article / BlogPosting
        $articleSchema = [
            '@context'      => 'https://schema.org',
            '@type'         => $post->schema_type ?: 'BlogPosting',
            'headline'      => $post->title,
            'description'   => $seoDescription,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified'  => ($post->last_modified_at ?? $post->updated_at)?->toIso8601String(),
            'url'           => $post->url,
            'wordCount'     => $post->word_count,
        ];
        if ($seoImage) {
            $articleSchema['image'] = $seoImage;
        }
        if ($post->author) {
            $articleSchema['author'] = [
                '@type' => 'Person',
                'name'  => $post->author->name ?? $post->author->username ?? 'Admin',
            ];
        }
        $seo->addJsonLd($articleSchema);

        // ── Related posts (same category, exclude current) ──
        $relatedPosts = BlogPost::published()
            ->with(['category'])
            ->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        // ── Popular posts for sidebar ──
        $popularPosts = BlogPost::published()
            ->orderByDesc('view_count')
            ->limit(5)
            ->get();

        // ── Table of contents ──
        $tableOfContents = $post->table_of_contents;
        if (empty($tableOfContents)) {
            $tableOfContents = $post->generateTableOfContents();
        }

        // ── Affiliate CTA blocks ──
        $ctaButtons = BlogCtaButton::active()
            ->with('affiliateLink')
            ->whereHas('affiliateLink', fn ($q) => $q->active())
            ->orderBy('show_after_paragraph')
            ->get();

        // ── Previous / Next post links ──
        $previousPost = BlogPost::published()
            ->where('published_at', '<', $post->published_at)
            ->orderByDesc('published_at')
            ->select('id', 'title', 'slug')
            ->first();

        $nextPost = BlogPost::published()
            ->where('published_at', '>', $post->published_at)
            ->orderBy('published_at')
            ->select('id', 'title', 'slug')
            ->first();

        return view('public.blog.show', compact(
            'post', 'relatedPosts', 'popularPosts',
            'tableOfContents', 'ctaButtons',
            'previousPost', 'nextPost'
        ));
    }

    /* ================================================================
     *  category — Posts by category
     * ================================================================ */
    public function category(Request $request, string $slug)
    {
        $category = BlogCategory::active()->where('slug', $slug)->firstOrFail();

        $posts = BlogPost::published()
            ->with(['category', 'tags'])
            ->where('category_id', $category->id)
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        // AJAX
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'html'       => view('public.blog.partials.post-grid', compact('posts'))->render(),
                'pagination' => $posts->links()->toHtml(),
                'total'      => $posts->total(),
                'hasMore'    => $posts->hasMorePages(),
            ]);
        }

        // Sidebar
        $popularPosts = BlogPost::published()
            ->orderByDesc('view_count')
            ->limit(5)
            ->get();

        $categories = BlogCategory::active()
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get();

        $tags = BlogTag::orderByDesc('post_count')->limit(20)->get();

        $featuredPosts = BlogPost::published()
            ->featured()
            ->with(['category'])
            ->where('category_id', $category->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        // SEO
        $seo = app(\App\Services\SeoService::class);
        $seo->title($category->meta_title ?: $category->name)
            ->description($category->meta_description ?: "บทความในหมวดหมู่ {$category->name}")
            ->type('website')
            ->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => url('/')],
                ['name' => 'บทความ',  'url' => route('blog.index')],
                ['name' => $category->name],
            ]);

        $currentCategory = $category;
        $currentTag      = null;

        return view('public.blog.index', compact(
            'posts', 'categories', 'tags', 'featuredPosts',
            'popularPosts', 'currentCategory', 'currentTag'
        ));
    }

    /* ================================================================
     *  tag — Posts by tag
     * ================================================================ */
    public function tag(Request $request, string $slug)
    {
        $tag = BlogTag::where('slug', $slug)->firstOrFail();

        $posts = BlogPost::published()
            ->with(['category', 'tags'])
            ->whereHas('tags', fn ($q) => $q->where('blog_tags.id', $tag->id))
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        // AJAX
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'html'       => view('public.blog.partials.post-grid', compact('posts'))->render(),
                'pagination' => $posts->links()->toHtml(),
                'total'      => $posts->total(),
                'hasMore'    => $posts->hasMorePages(),
            ]);
        }

        // Sidebar
        $popularPosts = BlogPost::published()
            ->orderByDesc('view_count')
            ->limit(5)
            ->get();

        $categories = BlogCategory::active()
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get();

        $tags = BlogTag::orderByDesc('post_count')->limit(20)->get();

        $featuredPosts = BlogPost::published()
            ->featured()
            ->with(['category'])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        // SEO
        $seo = app(\App\Services\SeoService::class);
        $seo->title("แท็ก: {$tag->name}")
            ->description("บทความที่ติดแท็ก {$tag->name}")
            ->type('website')
            ->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => url('/')],
                ['name' => 'บทความ',  'url' => route('blog.index')],
                ['name' => "แท็ก: {$tag->name}"],
            ]);

        $currentCategory = null;
        $currentTag      = $tag;

        return view('public.blog.index', compact(
            'posts', 'categories', 'tags', 'featuredPosts',
            'popularPosts', 'currentCategory', 'currentTag'
        ));
    }

    /* ================================================================
     *  search — Search posts
     * ================================================================ */
    public function search(Request $request)
    {
        $q = trim($request->input('q', ''));

        $posts = BlogPost::published()
            ->with(['category', 'tags'])
            ->when($q !== '', fn ($query) => $query->search($q))
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        // AJAX
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'html'       => view('public.blog.partials.post-grid', compact('posts'))->render(),
                'pagination' => $posts->links()->toHtml(),
                'total'      => $posts->total(),
                'hasMore'    => $posts->hasMorePages(),
                'query'      => $q,
            ]);
        }

        // Sidebar
        $popularPosts = BlogPost::published()
            ->orderByDesc('view_count')
            ->limit(5)
            ->get();

        $categories = BlogCategory::active()
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get();

        $tags = BlogTag::orderByDesc('post_count')->limit(20)->get();

        $featuredPosts = collect();

        // SEO
        $seo = app(\App\Services\SeoService::class);
        $seo->title($q !== '' ? "ค้นหา: {$q}" : 'ค้นหาบทความ')
            ->description("ผลการค้นหาบทความสำหรับ \"{$q}\"")
            ->robots('noindex, follow')
            ->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => url('/')],
                ['name' => 'บทความ',  'url' => route('blog.index')],
                ['name' => 'ค้นหา'],
            ]);

        $currentCategory = null;
        $currentTag      = null;

        return view('public.blog.index', compact(
            'posts', 'categories', 'tags', 'featuredPosts',
            'popularPosts', 'currentCategory', 'currentTag', 'q'
        ));
    }

    /* ================================================================
     *  feed — RSS 2.0 feed
     * ================================================================ */
    public function feed()
    {
        $posts = BlogPost::published()
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        $appName = config('app.name', 'Blog');
        $appUrl  = rtrim(config('app.url', ''), '/');
        $now     = now()->toRfc2822String();

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "<channel>\n";
        $xml .= "  <title>" . htmlspecialchars($appName . ' — บทความ', ENT_XML1) . "</title>\n";
        $xml .= "  <link>{$appUrl}/blog</link>\n";
        $xml .= "  <description>" . htmlspecialchars('บทความและเคล็ดลับล่าสุด', ENT_XML1) . "</description>\n";
        $xml .= "  <language>th</language>\n";
        $xml .= "  <lastBuildDate>{$now}</lastBuildDate>\n";
        $xml .= '  <atom:link href="' . htmlspecialchars(route('blog.feed'), ENT_XML1) . '" rel="self" type="application/rss+xml"/>' . "\n";

        foreach ($posts as $post) {
            $pubDate     = $post->published_at->toRfc2822String();
            $title       = htmlspecialchars($post->title, ENT_XML1);
            $link        = htmlspecialchars($post->url, ENT_XML1);
            $description = htmlspecialchars($post->excerpt ?: Str::limit(strip_tags($post->content), 300), ENT_XML1);
            $category    = $post->category ? htmlspecialchars($post->category->name, ENT_XML1) : '';
            $guid        = $link;

            $xml .= "  <item>\n";
            $xml .= "    <title>{$title}</title>\n";
            $xml .= "    <link>{$link}</link>\n";
            $xml .= "    <description>{$description}</description>\n";
            $xml .= "    <pubDate>{$pubDate}</pubDate>\n";
            $xml .= "    <guid isPermaLink=\"true\">{$guid}</guid>\n";
            if ($category !== '') {
                $xml .= "    <category>{$category}</category>\n";
            }
            if ($post->featured_image) {
                $xml .= '    <enclosure url="' . htmlspecialchars($post->featured_image, ENT_XML1) . '" type="image/jpeg"/>' . "\n";
            }
            $xml .= "  </item>\n";
        }

        $xml .= "</channel>\n";
        $xml .= "</rss>\n";

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
