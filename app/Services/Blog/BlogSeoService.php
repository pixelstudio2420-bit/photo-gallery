<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * BlogSeoService -- วิเคราะห์และปรับปรุง SEO สำหรับ blog posts
 *
 * ตรวจสอบ: title, meta description, keyword density, heading structure,
 * readability, word count, image alt text, internal links, URL slug
 * สร้าง schema markup, sitemap entries, table of contents
 */
class BlogSeoService
{
    /* ====================================================================
     *  Post Analysis
     * ==================================================================== */

    /**
     * วิเคราะห์ SEO ของ blog post แบบครบถ้วน
     *
     * @return array{overall_score: int, checks: array}
     */
    public function analyzePost(BlogPost $post): array
    {
        $content = $post->content ?? '';
        $keyword = $post->focus_keyword ?? '';
        $checks  = [];

        // ── 1. Title Length ──
        $titleLen = mb_strlen($post->title ?? '');
        $checks[] = $this->check(
            'ความยาวชื่อบทความ',
            $titleLen >= 20 && $titleLen <= 70 ? 'pass' : ($titleLen > 0 ? 'warn' : 'fail'),
            $titleLen >= 20 && $titleLen <= 70
                ? "ชื่อบทความมีความยาว {$titleLen} ตัวอักษร (เหมาะสม)"
                : "ชื่อบทความมีความยาว {$titleLen} ตัวอักษร (แนะนำ 20-70)",
            $titleLen >= 20 && $titleLen <= 70 ? 10 : ($titleLen > 0 ? 5 : 0)
        );

        // ── 2. Meta Description ──
        $metaDescLen = mb_strlen($post->meta_description ?? '');
        $maxMetaDesc = config('blog.seo.max_meta_description', 160);
        $checks[] = $this->check(
            'Meta Description',
            $metaDescLen >= 50 && $metaDescLen <= $maxMetaDesc ? 'pass' : ($metaDescLen > 0 ? 'warn' : 'fail'),
            $metaDescLen >= 50 && $metaDescLen <= $maxMetaDesc
                ? "Meta description มีความยาว {$metaDescLen} ตัวอักษร (เหมาะสม)"
                : ($metaDescLen === 0
                    ? 'ไม่มี meta description'
                    : "Meta description มีความยาว {$metaDescLen} ตัวอักษร (แนะนำ 50-{$maxMetaDesc})"),
            $metaDescLen >= 50 && $metaDescLen <= $maxMetaDesc ? 10 : ($metaDescLen > 0 ? 5 : 0)
        );

        // ── 3. Keyword in Title ──
        if (!empty($keyword)) {
            $keywordInTitle = mb_stripos($post->title ?? '', $keyword) !== false;
            $checks[] = $this->check(
                'Keyword ในชื่อบทความ',
                $keywordInTitle ? 'pass' : 'warn',
                $keywordInTitle
                    ? "พบ keyword \"{$keyword}\" ในชื่อบทความ"
                    : "ไม่พบ keyword \"{$keyword}\" ในชื่อบทความ แนะนำให้เพิ่ม",
                $keywordInTitle ? 10 : 0
            );
        }

        // ── 4. Keyword in First Paragraph ──
        if (!empty($keyword) && !empty($content)) {
            $firstParagraph = $this->extractFirstParagraph($content);
            $keywordInFirst = mb_stripos($firstParagraph, $keyword) !== false;
            $checks[] = $this->check(
                'Keyword ในย่อหน้าแรก',
                $keywordInFirst ? 'pass' : 'warn',
                $keywordInFirst
                    ? 'พบ keyword ในย่อหน้าแรกของบทความ'
                    : 'ไม่พบ keyword ในย่อหน้าแรก แนะนำให้เพิ่มเพื่อ SEO ที่ดีขึ้น',
                $keywordInFirst ? 10 : 2
            );
        }

        // ── 5. Keyword Density ──
        if (!empty($keyword) && !empty($content)) {
            $density     = $this->calculateKeywordDensity($content, $keyword);
            $idealMin    = config('blog.seo.ideal_keyword_density', 1.5) - 0.5;
            $idealMax    = config('blog.seo.max_keyword_density', 3.0);
            $densityOk   = $density >= $idealMin && $density <= $idealMax;
            $densityHigh = $density > $idealMax;

            $checks[] = $this->check(
                'Keyword Density',
                $densityOk ? 'pass' : ($densityHigh ? 'fail' : 'warn'),
                "Keyword density: {$density}% " . ($densityOk
                    ? '(เหมาะสม)'
                    : ($densityHigh ? '(สูงเกินไป อาจถูกมองว่า keyword stuffing)' : '(ต่ำเกินไป)')),
                $densityOk ? 10 : ($densityHigh ? 2 : 5)
            );
        }

        // ── 6. Heading Structure ──
        $h2Count = preg_match_all('/<h2[^>]*>/i', $content);
        $h3Count = preg_match_all('/<h3[^>]*>/i', $content);
        $hasGoodStructure = $h2Count >= 2 && $h3Count >= 1;

        $checks[] = $this->check(
            'โครงสร้าง Heading',
            $hasGoodStructure ? 'pass' : ($h2Count > 0 ? 'warn' : 'fail'),
            "พบ {$h2Count} H2 และ {$h3Count} H3 " . ($hasGoodStructure
                ? '(โครงสร้างดี)'
                : '(แนะนำ H2 อย่างน้อย 2 ตัว และ H3 อย่างน้อย 1 ตัว)'),
            $hasGoodStructure ? 10 : ($h2Count > 0 ? 5 : 0)
        );

        // ── 7. Image Alt Text ──
        $imgCount    = preg_match_all('/<img[^>]*>/i', $content);
        $imgWithAlt  = preg_match_all('/<img[^>]+alt=["\'][^"\']+["\'][^>]*>/i', $content);
        $allHaveAlt  = $imgCount === 0 || $imgCount === $imgWithAlt;

        $checks[] = $this->check(
            'Alt Text ของรูปภาพ',
            $allHaveAlt ? 'pass' : 'warn',
            $imgCount === 0
                ? 'ไม่มีรูปภาพในบทความ แนะนำให้เพิ่มรูปภาพ'
                : ($allHaveAlt
                    ? "รูปภาพ {$imgCount} รูปมี alt text ครบถ้วน"
                    : "รูปภาพ {$imgWithAlt}/{$imgCount} รูปมี alt text แนะนำให้เพิ่มให้ครบ"),
            $allHaveAlt && $imgCount > 0 ? 10 : ($imgWithAlt > 0 ? 5 : 0)
        );

        // ── 8. Internal Links ──
        $internalLinks = preg_match_all('/href=["\'](?:\/|' . preg_quote(url('/'), '/') . ')[^"\']*["\']/', $content);
        $checks[] = $this->check(
            'Internal Links',
            $internalLinks >= 2 ? 'pass' : ($internalLinks > 0 ? 'warn' : 'fail'),
            "พบ internal links {$internalLinks} ลิงก์ " . ($internalLinks >= 2
                ? '(เหมาะสม)'
                : '(แนะนำอย่างน้อย 2 ลิงก์)'),
            $internalLinks >= 2 ? 10 : ($internalLinks > 0 ? 5 : 0)
        );

        // ── 9. Word Count ──
        $wordCount = $post->word_count ?: $this->countWords($content);
        $minWords  = config('blog.seo.min_word_count', 300);
        $idealWords = config('blog.seo.ideal_word_count', 1500);

        $checks[] = $this->check(
            'จำนวนคำ',
            $wordCount >= $idealWords ? 'pass' : ($wordCount >= $minWords ? 'warn' : 'fail'),
            "จำนวนคำ: {$wordCount} " . ($wordCount >= $idealWords
                ? "(เหมาะสม เกิน {$idealWords} คำ)"
                : ($wordCount >= $minWords
                    ? "(น้อยกว่าที่แนะนำ {$idealWords} คำ)"
                    : "(น้อยเกินไป ต่ำกว่าขั้นต่ำ {$minWords} คำ)")),
            $wordCount >= $idealWords ? 10 : ($wordCount >= $minWords ? 5 : 2)
        );

        // ── 10. Readability ──
        $readability = $this->calculateReadability($content);
        $checks[] = $this->check(
            'ความอ่านง่าย',
            $readability >= 60 ? 'pass' : ($readability >= 40 ? 'warn' : 'fail'),
            "คะแนนความอ่านง่าย: {$readability}/100 " . ($readability >= 60
                ? '(อ่านง่าย)'
                : ($readability >= 40 ? '(ปานกลาง)' : '(อ่านยาก)')),
            (int) round($readability / 10)
        );

        // ── 11. URL Slug ──
        $slugLen = mb_strlen($post->slug ?? '');
        $slugOk  = $slugLen > 0 && $slugLen <= 80;
        $checks[] = $this->check(
            'URL Slug',
            $slugOk ? 'pass' : ($slugLen > 0 ? 'warn' : 'fail'),
            $slugOk
                ? "URL slug มีความยาว {$slugLen} ตัวอักษร (เหมาะสม)"
                : ($slugLen === 0 ? 'ไม่มี URL slug' : "URL slug ยาวเกินไป ({$slugLen} ตัวอักษร)"),
            $slugOk ? 10 : ($slugLen > 0 ? 5 : 0)
        );

        // ── คำนวณคะแนนรวม ──
        $totalPossible = count($checks) * 10;
        $totalScore    = array_sum(array_column($checks, 'score'));
        $overallScore  = $totalPossible > 0
            ? (int) round(($totalScore / $totalPossible) * 100)
            : 0;

        return [
            'overall_score' => $overallScore,
            'checks'        => $checks,
            'summary'       => $this->getSeoSummary($overallScore),
        ];
    }

    /* ====================================================================
     *  Readability
     * ==================================================================== */

    /**
     * คำนวณคะแนนความอ่านง่าย (ปรับจาก Flesch-Kincaid สำหรับภาษาไทย)
     *
     * @return int  คะแนน 0-100 (สูง = อ่านง่าย)
     */
    public function calculateReadability(string $content): int
    {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (empty($text)) {
            return 0;
        }

        // แยกประโยค
        $sentences = preg_split('/[.!?\x{0E2F}\x{0E30}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = max(count($sentences), 1);

        // นับคำ
        $wordCount = $this->countWords($text);

        if ($wordCount === 0) {
            return 0;
        }

        // ความยาวเฉลี่ยของประโยค (คำต่อประโยค)
        $avgSentenceLength = $wordCount / $sentenceCount;

        // นับพยางค์ (สำหรับภาษาอังกฤษ) / ความยาวคำเฉลี่ย (สำหรับภาษาไทย)
        $charCount = mb_strlen($text);
        $avgWordLength = $charCount / max($wordCount, 1);

        // สูตรปรับแต่ง: คะแนนสูง = อ่านง่าย
        // ประโยคสั้น + คำสั้น = อ่านง่ายกว่า
        $score = 100;
        $score -= ($avgSentenceLength - 15) * 2;  // ลงโทษประโยคยาว (ideal ~15 คำ)
        $score -= ($avgWordLength - 4) * 3;         // ลงโทษคำยาว (ideal ~4 ตัวอักษร)

        // เพิ่มคะแนนถ้ามี heading structure ดี
        $h2Count = preg_match_all('/<h[23][^>]*>/i', $content);
        if ($h2Count > 0 && $wordCount > 100) {
            $wordsPerHeading = $wordCount / $h2Count;
            if ($wordsPerHeading <= 300) {
                $score += 5; // โครงสร้างดี มี heading ถี่พอ
            }
        }

        // เพิ่มคะแนนถ้ามี list items
        $listItems = preg_match_all('/<li[^>]*>/i', $content);
        if ($listItems > 0) {
            $score += min(5, $listItems); // List ช่วยอ่านง่าย
        }

        return max(0, min(100, (int) round($score)));
    }

    /* ====================================================================
     *  Keyword Density
     * ==================================================================== */

    /**
     * คำนวณ keyword density เป็นเปอร์เซ็นต์
     */
    public function calculateKeywordDensity(string $content, string $keyword): float
    {
        $text      = mb_strtolower(strip_tags($content));
        $keyword   = mb_strtolower(trim($keyword));
        $wordCount = $this->countWords($text);

        if ($wordCount === 0 || empty($keyword)) {
            return 0.0;
        }

        $keywordCount = mb_substr_count($text, $keyword);
        $keywordWords = str_word_count($keyword) ?: 1;

        return round(($keywordCount * $keywordWords / $wordCount) * 100, 2);
    }

    /* ====================================================================
     *  Schema Markup
     * ==================================================================== */

    /**
     * สร้าง schema markup (JSON-LD) สำหรับ blog post
     */
    public function generateSchema(BlogPost $post): array
    {
        $appUrl    = rtrim(config('app.url', ''), '/');
        $postUrl   = $appUrl . '/blog/' . $post->slug;
        $schemaType = $post->schema_type ?? 'Article';

        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => $schemaType,
            'headline'         => $post->title,
            'description'      => $post->meta_description ?? $post->excerpt ?? '',
            'url'              => $postUrl,
            'datePublished'    => $post->published_at?->toIso8601String() ?? $post->created_at?->toIso8601String(),
            'dateModified'     => $post->last_modified_at?->toIso8601String() ?? $post->updated_at?->toIso8601String(),
            'wordCount'        => $post->word_count,
            'inLanguage'       => 'th',
        ];

        // รูปภาพ
        if (!empty($post->featured_image)) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url'   => $post->featured_image,
            ];
        }

        // ผู้เขียน
        $schema['author'] = [
            '@type' => 'Organization',
            'name'  => config('app.name', 'Blog'),
            'url'   => $appUrl,
        ];

        // Publisher
        $schema['publisher'] = [
            '@type' => 'Organization',
            'name'  => config('app.name', 'Blog'),
            'url'   => $appUrl,
        ];

        // Keywords
        if (!empty($post->focus_keyword)) {
            $schema['keywords'] = $post->focus_keyword;
        }

        // FAQ schema ถ้ามี
        $toc = $post->table_of_contents;
        if (is_string($toc)) {
            $toc = json_decode($toc, true);
        }

        return $schema;
    }

    /* ====================================================================
     *  Image Alt Text
     * ==================================================================== */

    /**
     * แนะนำ alt text สำหรับรูปภาพจาก URL และบริบท
     */
    public function suggestImageAltText(string $imageUrl, string $context): string
    {
        // สร้าง alt text จาก filename + context
        $filename = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?? '', PATHINFO_FILENAME);
        $filename = str_replace(['-', '_'], ' ', $filename);
        $filename = ucfirst(trim($filename));

        if (!empty($context)) {
            $contextWords = mb_substr(strip_tags($context), 0, 100);
            return "{$filename} - {$contextWords}";
        }

        return $filename ?: 'รูปภาพประกอบบทความ';
    }

    /* ====================================================================
     *  Sitemap
     * ==================================================================== */

    /**
     * ดึง sitemap entries สำหรับ blog posts ทั้งหมด
     */
    public function getSitemapEntries(): array
    {
        $appUrl = rtrim(config('app.url', ''), '/');
        $entries = [];

        // Blog index page
        $entries[] = [
            'url'        => $appUrl . '/blog',
            'lastmod'    => now()->toAtomString(),
            'changefreq' => 'daily',
            'priority'   => '0.8',
        ];

        // Published posts
        $posts = BlogPost::where('status', 'published')
            ->where('visibility', 'public')
            ->select('slug', 'updated_at', 'published_at', 'is_featured')
            ->orderByDesc('published_at')
            ->get();

        foreach ($posts as $post) {
            $lastmod  = ($post->updated_at ?? $post->published_at)?->toAtomString() ?? now()->toAtomString();
            $priority = $post->is_featured ? '0.9' : '0.7';

            $entries[] = [
                'url'        => $appUrl . '/blog/' . $post->slug,
                'lastmod'    => $lastmod,
                'changefreq' => 'weekly',
                'priority'   => $priority,
            ];
        }

        // Categories
        $categories = DB::table('blog_categories')
            ->where('is_active', true)
            ->select('slug', 'updated_at')
            ->get();

        foreach ($categories as $category) {
            $entries[] = [
                'url'        => $appUrl . '/blog/category/' . $category->slug,
                'lastmod'    => $category->updated_at ?? now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority'   => '0.6',
            ];
        }

        // Tags
        $tags = DB::table('blog_tags')
            ->where('post_count', '>', 0)
            ->select('slug', 'updated_at')
            ->get();

        foreach ($tags as $tag) {
            $entries[] = [
                'url'        => $appUrl . '/blog/tag/' . $tag->slug,
                'lastmod'    => $tag->updated_at ?? now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority'   => '0.5',
            ];
        }

        return $entries;
    }

    /* ====================================================================
     *  Internal Links
     * ==================================================================== */

    /**
     * แนะนำ internal links จาก post อื่นที่เกี่ยวข้อง
     */
    public function suggestInternalLinks(BlogPost $post, int $limit = 5): array
    {
        $keyword = $post->focus_keyword;
        $title   = $post->title;

        // ค้นหา post ที่เกี่ยวข้อง
        $query = BlogPost::where('id', '!=', $post->id)
            ->where('status', 'published')
            ->where('visibility', 'public');

        // ค้นหาจาก category เดียวกัน
        if ($post->category_id) {
            $sameCategoryPosts = (clone $query)
                ->where('category_id', $post->category_id)
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get();
        } else {
            $sameCategoryPosts = collect();
        }

        // ค้นหาจาก keyword ที่เกี่ยวข้อง
        $keywordPosts = collect();
        if (!empty($keyword)) {
            $keywordPosts = (clone $query)
                ->where(function ($q) use ($keyword, $title) {
                    $q->where('title', 'LIKE', "%{$keyword}%")
                      ->orWhere('focus_keyword', 'LIKE', "%{$keyword}%")
                      ->orWhere('title', 'LIKE', '%' . mb_substr($title, 0, 20) . '%');
                })
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get();
        }

        // รวมผลลัพธ์ไม่ซ้ำ
        $suggestions = $sameCategoryPosts
            ->merge($keywordPosts)
            ->unique('id')
            ->take($limit)
            ->map(function (BlogPost $relatedPost) {
                return [
                    'post_id'      => $relatedPost->id,
                    'title'        => $relatedPost->title,
                    'slug'         => $relatedPost->slug,
                    'url'          => url('/blog/' . $relatedPost->slug),
                    'keyword'      => $relatedPost->focus_keyword,
                    'anchor_text'  => $relatedPost->title,
                ];
            })
            ->values()
            ->toArray();

        return $suggestions;
    }

    /* ====================================================================
     *  Table of Contents
     * ==================================================================== */

    /**
     * สร้าง Table of Contents จาก HTML content
     *
     * @return array<array{level: int, text: string, id: string}>
     */
    public function generateTableOfContents(string $htmlContent): array
    {
        $toc = [];

        if (preg_match_all('/<(h[23])([^>]*)>(.*?)<\/\1>/si', $htmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $level = $match[1];
                $attrs = $match[2];
                $text  = strip_tags($match[3]);
                $text  = trim($text);

                if (empty($text)) {
                    continue;
                }

                // ดึง id ที่มีอยู่แล้ว หรือสร้างใหม่
                $id = '';
                if (preg_match('/id=["\']([^"\']+)["\']/', $attrs, $idMatch)) {
                    $id = $idMatch[1];
                } else {
                    $id = Str::slug($text);
                    if (empty($id)) {
                        $id = 'section-' . (count($toc) + 1);
                    }
                }

                $toc[] = [
                    'level' => $level === 'h2' ? 2 : 3,
                    'text'  => $text,
                    'id'    => $id,
                ];
            }
        }

        return $toc;
    }

    /**
     * แทรก anchor IDs ให้กับ heading tags ที่ยังไม่มี id
     */
    public function insertHeadingAnchors(string $htmlContent): string
    {
        $counter = 0;

        return preg_replace_callback(
            '/<(h[23])([^>]*)>(.*?)<\/\1>/si',
            function ($match) use (&$counter) {
                $tag   = $match[1];
                $attrs = $match[2];
                $inner = $match[3];
                $text  = strip_tags($inner);

                // ข้าม heading ที่มี id แล้ว
                if (preg_match('/id=["\']/', $attrs)) {
                    return $match[0];
                }

                $counter++;
                $id = Str::slug($text);
                if (empty($id)) {
                    $id = "section-{$counter}";
                }

                return "<{$tag}{$attrs} id=\"{$id}\">{$inner}</{$tag}>";
            },
            $htmlContent
        );
    }

    /* ====================================================================
     *  Internal helpers
     * ==================================================================== */

    /**
     * สร้าง check result
     */
    private function check(string $name, string $status, string $message, int $score): array
    {
        return [
            'name'    => $name,
            'status'  => $status, // pass, warn, fail
            'message' => $message,
            'score'   => $score,
        ];
    }

    /**
     * สรุป SEO score เป็นข้อความ
     */
    private function getSeoSummary(int $score): string
    {
        return match (true) {
            $score >= 90 => 'SEO ดีเยี่ยม! บทความพร้อมเผยแพร่',
            $score >= 70 => 'SEO ดี มีบางจุดที่ปรับปรุงได้',
            $score >= 50 => 'SEO ปานกลาง ควรปรับปรุงก่อนเผยแพร่',
            $score >= 30 => 'SEO ต้องปรับปรุง มีหลายจุดที่ต้องแก้ไข',
            default      => 'SEO ต่ำมาก ต้องปรับปรุงอย่างเร่งด่วน',
        };
    }

    /**
     * ดึงย่อหน้าแรกจาก HTML
     */
    private function extractFirstParagraph(string $html): string
    {
        if (preg_match('/<p[^>]*>(.*?)<\/p>/si', $html, $match)) {
            return strip_tags($match[1]);
        }

        // Fallback: ดึง 300 ตัวอักษรแรก
        return mb_substr(strip_tags($html), 0, 300);
    }

    /**
     * นับจำนวนคำ (รองรับภาษาไทยและอังกฤษ)
     */
    private function countWords(string $content): int
    {
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (empty($text)) {
            return 0;
        }

        $englishWords = str_word_count($text);
        $thaiChars    = preg_match_all('/[\x{0E00}-\x{0E7F}]/u', $text);
        $thaiWords    = (int) ceil($thaiChars / 6);

        return $englishWords + $thaiWords;
    }
}
