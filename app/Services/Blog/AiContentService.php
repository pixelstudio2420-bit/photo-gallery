<?php

namespace App\Services\Blog;

use App\Models\AppSetting;
use App\Models\BlogAiTask;
use App\Services\Blog\Providers\ClaudeProvider;
use App\Services\Blog\Providers\GeminiProvider;
use App\Services\Blog\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AiContentService -- บริการหลักสำหรับสร้าง/แก้ไข/วิเคราะห์เนื้อหาด้วย AI
 *
 * จัดการ provider ทั้งหมด (OpenAI, Claude, Gemini) ผ่าน AiProviderInterface
 * ทุกการเรียก AI จะถูก log ไว้ใน blog_ai_tasks เพื่อติดตามค่าใช้จ่าย
 */
class AiContentService
{
    private ?AiProviderInterface $provider = null;
    private string $currentProviderName;

    /** Provider class map */
    private const PROVIDERS = [
        'openai' => OpenAiProvider::class,
        'claude' => ClaudeProvider::class,
        'gemini' => GeminiProvider::class,
    ];

    public function __construct()
    {
        $this->currentProviderName = config('blog.ai.default_provider', 'openai');
    }

    /* ====================================================================
     *  Core -- Provider Management
     * ==================================================================== */

    /**
     * ดึง provider instance (lazy-loaded)
     *
     * @param  string|null  $providerName  ชื่อ provider หรือ null ใช้ default
     */
    public function getProvider(?string $providerName = null): AiProviderInterface
    {
        $name = $providerName ?? $this->currentProviderName;

        if ($this->provider !== null && $this->provider->getProviderName() === $name) {
            return $this->provider;
        }

        if (!isset(self::PROVIDERS[$name])) {
            throw new \InvalidArgumentException("ไม่พบ AI provider: {$name} (รองรับ: openai, claude, gemini)");
        }

        $this->provider = new (self::PROVIDERS[$name])();
        $this->currentProviderName = $name;

        return $this->provider;
    }

    /**
     * เปลี่ยน provider ที่ใช้งาน
     */
    public function setProvider(string $providerName): self
    {
        $this->provider = null; // reset เพื่อให้ getProvider() สร้างใหม่
        $this->currentProviderName = $providerName;
        return $this;
    }

    /* ====================================================================
     *  Article Generation
     * ==================================================================== */

    /**
     * สร้างบทความสมบูรณ์จาก keyword ด้วยกระบวนการ multi-step
     *
     * ขั้นตอน: สร้าง outline -> ขยายแต่ละหัวข้อ -> รวม -> SEO optimize
     *
     * @param  string  $keyword  คำค้น/หัวข้อหลัก
     * @param  array   $options  ตัวเลือก: language, word_count, tone, include_faq, include_toc
     * @return array{title: string, content: string, excerpt: string, meta_title: string,
     *               meta_description: string, tags: array, table_of_contents: array,
     *               tokens_used: array, cost: float}
     */
    public function generateArticle(string $keyword, array $options = []): array
    {
        $language   = $options['language'] ?? 'th';
        $wordCount  = $options['word_count'] ?? config('blog.seo.ideal_word_count', 1500);
        $tone       = $options['tone'] ?? 'informative';
        $includeFaq = $options['include_faq'] ?? true;
        $includeToc = $options['include_toc'] ?? true;

        $langLabel = $language === 'th' ? 'ภาษาไทย' : 'English';

        $totalTokensInput  = 0;
        $totalTokensOutput = 0;
        $totalCost         = 0.0;

        // ── Step 1: สร้าง Outline ──
        $outline = $this->generateOutline($keyword, [
            'language'   => $language,
            'tone'       => $tone,
            'word_count' => $wordCount,
        ]);

        $totalTokensInput  += $outline['tokens_input'] ?? 0;
        $totalTokensOutput += $outline['tokens_output'] ?? 0;
        $totalCost         += $outline['cost'] ?? 0;

        // ── Step 2: ขยายแต่ละ section ──
        $expandedSections = [];
        $outlineSections  = $outline['sections'] ?? [];

        foreach ($outlineSections as $section) {
            $heading = $section['heading'] ?? '';
            $context = "บทความเกี่ยวกับ: {$keyword}\nHeading: {$heading}\nSubheadings: " .
                implode(', ', $section['subheadings'] ?? []);

            $expanded = $this->expandSection($heading, $context, [
                'language'   => $language,
                'tone'       => $tone,
                'word_count' => intval($wordCount / max(count($outlineSections), 1)),
            ]);

            $expandedSections[] = [
                'heading' => $heading,
                'content' => $expanded['content'] ?? $expanded,
            ];

            $totalTokensInput  += $expanded['tokens_input'] ?? 0;
            $totalTokensOutput += $expanded['tokens_output'] ?? 0;
            $totalCost         += $expanded['cost'] ?? 0;
        }

        // ── Step 3: รวมเนื้อหาและสร้าง intro/conclusion/FAQ ──
        $combinePrompt = $this->buildPrompt('combine_article', [
            'keyword'     => $keyword,
            'language'    => $langLabel,
            'tone'        => $tone,
            'sections'    => $expandedSections,
            'include_faq' => $includeFaq,
        ]);

        $combineResult = $this->callAi($combinePrompt, [
            'system_prompt' => $this->getSystemPrompt('article_writer', $language),
            'json_mode'     => true,
            'max_tokens'    => 4096,
        ]);

        $totalTokensInput  += $combineResult['tokens_input'];
        $totalTokensOutput += $combineResult['tokens_output'];
        $totalCost         += $combineResult['cost'];

        $articleData = $this->parseJsonResponse($combineResult['content']);

        // ── Step 4: SEO meta tags ──
        $fullContent = $articleData['content'] ?? '';
        $metaTags    = $this->generateMetaTags($fullContent, $keyword);

        $totalTokensInput  += $metaTags['tokens_input'] ?? 0;
        $totalTokensOutput += $metaTags['tokens_output'] ?? 0;
        $totalCost         += $metaTags['cost'] ?? 0;

        // ── สร้าง Table of Contents ──
        $toc = [];
        if ($includeToc) {
            $toc = $this->extractTableOfContents($fullContent);
        }

        $result = [
            'title'             => $articleData['title'] ?? $keyword,
            'content'           => $fullContent,
            'excerpt'           => $articleData['excerpt'] ?? mb_substr(strip_tags($fullContent), 0, 300),
            'meta_title'        => $metaTags['meta_title'] ?? mb_substr($articleData['title'] ?? $keyword, 0, 60),
            'meta_description'  => $metaTags['meta_description'] ?? '',
            'tags'              => $articleData['tags'] ?? [],
            'faq'               => $articleData['faq'] ?? [],
            'table_of_contents' => $toc,
            'tokens_used'       => [
                'input'  => $totalTokensInput,
                'output' => $totalTokensOutput,
                'total'  => $totalTokensInput + $totalTokensOutput,
            ],
            'cost' => round($totalCost, 6),
        ];

        // Log the full task
        $this->logTask('generate_article', "สร้างบทความ: {$keyword}", $result);

        return $result;
    }

    /**
     * สร้าง outline ของบทความ
     *
     * @return array{sections: array, tokens_input: int, tokens_output: int, cost: float}
     */
    public function generateOutline(string $topic, array $options = []): array
    {
        $language  = $options['language'] ?? 'th';
        $langLabel = $language === 'th' ? 'ภาษาไทย' : 'English';

        $prompt = $this->buildPrompt('outline', [
            'topic'      => $topic,
            'language'   => $langLabel,
            'tone'       => $options['tone'] ?? 'informative',
            'word_count' => $options['word_count'] ?? 1500,
        ]);

        $result = $this->callAi($prompt, [
            'system_prompt' => $this->getSystemPrompt('outline_creator', $language),
            'json_mode'     => true,
        ]);

        $parsed = $this->parseJsonResponse($result['content']);

        return [
            'sections'      => $parsed['sections'] ?? [],
            'title'         => $parsed['title'] ?? $topic,
            'tokens_input'  => $result['tokens_input'],
            'tokens_output' => $result['tokens_output'],
            'cost'          => $result['cost'],
        ];
    }

    /**
     * ขยายหัวข้อเดียวเป็นเนื้อหาแบบเต็ม
     */
    public function expandSection(string $heading, string $context, array $options = []): array
    {
        $language  = $options['language'] ?? 'th';
        $langLabel = $language === 'th' ? 'ภาษาไทย' : 'English';

        $prompt = $this->buildPrompt('expand_section', [
            'heading'    => $heading,
            'context'    => $context,
            'language'   => $langLabel,
            'tone'       => $options['tone'] ?? 'informative',
            'word_count' => $options['word_count'] ?? 300,
        ]);

        $result = $this->callAi($prompt, [
            'system_prompt' => $this->getSystemPrompt('section_writer', $language),
        ]);

        return [
            'content'       => $result['content'],
            'tokens_input'  => $result['tokens_input'],
            'tokens_output' => $result['tokens_output'],
            'cost'          => $result['cost'],
        ];
    }

    /* ====================================================================
     *  Rewriting
     * ==================================================================== */

    /**
     * เขียนเนื้อหาใหม่ตาม style ที่กำหนด
     *
     * @param  string  $content  เนื้อหาต้นฉบับ
     * @param  string  $style    improve|simplify|professional|casual|seo_optimize
     */
    public function rewriteContent(string $content, string $style = 'improve'): array
    {
        $styleInstructions = [
            'improve'       => 'ปรับปรุงเนื้อหาให้อ่านง่ายขึ้น ไหลลื่นขึ้น และน่าสนใจยิ่งขึ้น คงความหมายเดิมไว้',
            'simplify'      => 'ทำให้เนื้อหาเรียบง่ายขึ้น ใช้ภาษาที่เข้าใจง่าย ประโยคสั้นกระชับ เหมาะกับผู้อ่านทั่วไป',
            'professional'  => 'เขียนใหม่ในรูปแบบมืออาชีพ ใช้ภาษาทางการ เหมาะสำหรับบทความธุรกิจหรือวิชาการ',
            'casual'        => 'เขียนใหม่ในรูปแบบสบายๆ เป็นกันเอง เหมือนคุยกับเพื่อน',
            'seo_optimize'  => 'ปรับปรุงเนื้อหาให้เหมาะกับ SEO โดยเพิ่ม heading tags, keyword placement, internal link suggestions และ meta data',
        ];

        $instruction = $styleInstructions[$style] ?? $styleInstructions['improve'];

        $prompt = "เขียนเนื้อหาต่อไปนี้ใหม่:\n\n{$instruction}\n\nเนื้อหาต้นฉบับ:\n{$content}\n\nส่งกลับเนื้อหาที่เขียนใหม่แล้วเท่านั้น";

        $result = $this->callAi($prompt, [
            'system_prompt' => 'คุณเป็นนักเขียนมืออาชีพที่เชี่ยวชาญในการปรับปรุงเนื้อหา ตอบกลับเป็นภาษาเดียวกับเนื้อหาต้นฉบับ',
        ]);

        $this->logTask('rewrite', "เขียนใหม่แบบ: {$style}", $result);

        return [
            'content'       => $result['content'],
            'style'         => $style,
            'tokens_input'  => $result['tokens_input'],
            'tokens_output' => $result['tokens_output'],
            'cost'          => $result['cost'],
        ];
    }

    /**
     * Paraphrase เนื้อหาให้แตกต่างจากต้นฉบับแต่ความหมายเดิม
     */
    public function paraphraseContent(string $content): array
    {
        $prompt = <<<PROMPT
Paraphrase เนื้อหาต่อไปนี้ให้แตกต่างจากต้นฉบับมากที่สุด แต่คงความหมายเดิมไว้ทั้งหมด
ใช้โครงสร้างประโยคใหม่ เปลี่ยนคำศัพท์ และจัดลำดับใหม่ตามความเหมาะสม

เนื้อหาต้นฉบับ:
{$content}

ส่งกลับเฉพาะเนื้อหาที่ paraphrase แล้ว
PROMPT;

        $result = $this->callAi($prompt, [
            'system_prompt' => 'คุณเป็นผู้เชี่ยวชาญด้านการ paraphrase เนื้อหา ตอบกลับในภาษาเดียวกับต้นฉบับ',
        ]);

        $this->logTask('rewrite', 'Paraphrase เนื้อหา', $result);

        return [
            'content'       => $result['content'],
            'tokens_input'  => $result['tokens_input'],
            'tokens_output' => $result['tokens_output'],
            'cost'          => $result['cost'],
        ];
    }

    /* ====================================================================
     *  Summarization
     * ==================================================================== */

    /**
     * สรุปเนื้อหาตาม format ที่กำหนด
     *
     * @param  string  $format  paragraph|bullet_points|tldr|key_points
     */
    public function summarizeContent(string $content, string $format = 'paragraph'): array
    {
        $formatInstructions = [
            'paragraph'     => 'สรุปเป็นย่อหน้า 2-3 ย่อหน้า ครอบคลุมประเด็นสำคัญทั้งหมด',
            'bullet_points' => 'สรุปเป็นรายการหัวข้อย่อย (bullet points) แต่ละข้อสั้นกระชับ',
            'tldr'          => 'สรุปแบบ TL;DR ใน 1-2 ประโยคสั้นๆ ตรงประเด็น',
            'key_points'    => 'สรุปเป็นประเด็นสำคัญ (key points) 5-7 ข้อ พร้อมคำอธิบายสั้นๆ',
        ];

        $instruction = $formatInstructions[$format] ?? $formatInstructions['paragraph'];

        $prompt = "สรุปเนื้อหาต่อไปนี้:\n\n{$instruction}\n\nเนื้อหา:\n{$content}";

        $result = $this->callAi($prompt, [
            'system_prompt' => 'คุณเป็นผู้เชี่ยวชาญในการสรุปเนื้อหา สรุปตรงประเด็น ครบถ้วน ภาษาเดียวกับต้นฉบับ',
        ]);

        $this->logTask('summarize', "สรุปแบบ: {$format}", $result);

        return [
            'summary'       => $result['content'],
            'format'        => $format,
            'tokens_input'  => $result['tokens_input'],
            'tokens_output' => $result['tokens_output'],
            'cost'          => $result['cost'],
        ];
    }

    /**
     * ดึงประเด็นสำคัญจากเนื้อหา
     */
    public function extractKeyPoints(string $content): array
    {
        $prompt = <<<PROMPT
วิเคราะห์เนื้อหาต่อไปนี้และดึงประเด็นสำคัญออกมา

เนื้อหา:
{$content}

ส่งกลับเป็น JSON format:
{
  "key_points": [
    {"point": "ประเด็นสำคัญ", "detail": "คำอธิบายเพิ่มเติม"}
  ],
  "main_topic": "หัวข้อหลัก",
  "sentiment": "positive/neutral/negative"
}
PROMPT;

        $result = $this->callAi($prompt, [
            'system_prompt' => 'คุณเป็นนักวิเคราะห์เนื้อหา ตอบกลับเป็น JSON เท่านั้น',
            'json_mode'     => true,
        ]);

        $parsed = $this->parseJsonResponse($result['content']);

        $this->logTask('summarize', 'ดึงประเด็นสำคัญ', $result);

        return [
            'key_points'    => $parsed['key_points'] ?? [],
            'main_topic'    => $parsed['main_topic'] ?? '',
            'sentiment'     => $parsed['sentiment'] ?? 'neutral',
            'tokens_input'  => $result['tokens_input'],
            'tokens_output' => $result['tokens_output'],
            'cost'          => $result['cost'],
        ];
    }

    /* ====================================================================
     *  SEO
     * ==================================================================== */

    /**
     * แนะนำ keywords สำหรับหัวข้อ
     *
     * @return array{keywords: array, tokens_input: int, tokens_output: int, cost: float}
     */
    public function suggestKeywords(string $topic): array
    {
        $prompt = <<<PROMPT
วิเคราะห์หัวข้อต่อไปนี้และแนะนำ keywords สำหรับ SEO

หัวข้อ: {$topic}

ส่งกลับเป็น JSON format:
{
  "primary_keyword": "keyword หลัก",
  "secondary_keywords": ["keyword รอง 1", "keyword รอง 2"],
  "long_tail_keywords": ["long-tail 1", "long-tail 2"],
  "related_topics": ["หัวข้อที่เกี่ยวข้อง 1"],
  "search_intent": "informational/transactional/navigational/commercial"
}
PROMPT;

        $result = $this->callAi($prompt, [
            'system_prompt' => 'คุณเป็นผู้เชี่ยวชาญ SEO ที่รู้จักตลาดไทยเป็นอย่างดี ตอบกลับเป็น JSON เท่านั้น',
            'json_mode'     => true,
        ]);

        $parsed = $this->parseJsonResponse($result['content']);

        $this->logTask('keyword_suggest', "แนะนำ keywords: {$topic}", $result);

        return [
            'primary_keyword'    => $parsed['primary_keyword'] ?? $topic,
            'secondary_keywords' => $parsed['secondary_keywords'] ?? [],
            'long_tail_keywords' => $parsed['long_tail_keywords'] ?? [],
            'related_topics'     => $parsed['related_topics'] ?? [],
            'search_intent'      => $parsed['search_intent'] ?? 'informational',
            'tokens_input'       => $result['tokens_input'],
            'tokens_output'      => $result['tokens_output'],
            'cost'               => $result['cost'],
        ];
    }

    /**
     * สร้าง meta tags จากเนื้อหาและ keyword
     *
     * @return array{meta_title: string, meta_description: string, og_title: string,
     *               tokens_input: int, tokens_output: int, cost: float}
     */
    public function generateMetaTags(string $content, string $keyword): array
    {
        $maxTitle = config('blog.seo.max_meta_title', 60);
        $maxDesc  = config('blog.seo.max_meta_description', 160);

        $truncatedContent = mb_substr(strip_tags($content), 0, 2000);

        $prompt = <<<PROMPT
สร้าง meta tags สำหรับ SEO จากเนื้อหาต่อไปนี้

Keyword หลัก: {$keyword}
เนื้อหา: {$truncatedContent}

กฎ:
- meta_title: ไม่เกิน {$maxTitle} ตัวอักษร ต้องมี keyword อยู่ในชื่อ
- meta_description: ไม่เกิน {$maxDesc} ตัวอักษร ดึงดูดให้คลิก มี keyword อยู่
- og_title: ชื่อสำหรับ social media อาจยาวกว่า meta_title เล็กน้อย

ส่งกลับเป็น JSON format:
{
  "meta_title": "...",
  "meta_description": "...",
  "og_title": "..."
}
PROMPT;

        $result = $this->callAi($prompt, [
            'system_prompt' => 'คุณเป็นผู้เชี่ยวชาญ SEO สร้าง meta tags ที่มีประสิทธิภาพสูง ตอบกลับเป็น JSON เท่านั้น',
            'json_mode'     => true,
        ]);

        $parsed = $this->parseJsonResponse($result['content']);

        return [
            'meta_title'       => mb_substr($parsed['meta_title'] ?? $keyword, 0, $maxTitle),
            'meta_description' => mb_substr($parsed['meta_description'] ?? '', 0, $maxDesc),
            'og_title'         => $parsed['og_title'] ?? $parsed['meta_title'] ?? $keyword,
            'tokens_input'     => $result['tokens_input'],
            'tokens_output'    => $result['tokens_output'],
            'cost'             => $result['cost'],
        ];
    }

    /**
     * วิเคราะห์ SEO ของเนื้อหา
     *
     * @return array{seo_score: int, readability_score: int, suggestions: array,
     *               keyword_density: float, word_count: int}
     */
    public function analyzeSeo(string $content, string $keyword): array
    {
        $wordCount      = $this->countWords($content);
        $keywordDensity = $this->calculateKeywordDensity($content, $keyword);
        $plainContent   = strip_tags($content);

        $truncatedContent = mb_substr($plainContent, 0, 3000);

        $prompt = <<<PROMPT
วิเคราะห์ SEO ของเนื้อหาต่อไปนี้

Keyword: {$keyword}
จำนวนคำ: {$wordCount}
Keyword density: {$keywordDensity}%

เนื้อหา:
{$truncatedContent}

วิเคราะห์และให้คะแนน:
1. SEO Score (0-100) -- คุณภาพ SEO โดยรวม
2. Readability Score (0-100) -- ความอ่านง่าย
3. คำแนะนำในการปรับปรุง

ส่งกลับเป็น JSON:
{
  "seo_score": 75,
  "readability_score": 80,
  "suggestions": [
    {"type": "warning", "message": "คำแนะนำ..."},
    {"type": "success", "message": "สิ่งที่ดีแล้ว..."}
  ],
  "heading_analysis": "วิเคราะห์โครงสร้าง heading",
  "content_quality": "วิเคราะห์คุณภาพเนื้อหา"
}
PROMPT;

        $result = $this->callAi($prompt, [
            'system_prompt' => 'คุณเป็นผู้เชี่ยวชาญ SEO วิเคราะห์เนื้อหาอย่างละเอียด ตอบกลับเป็น JSON เท่านั้น',
            'json_mode'     => true,
        ]);

        $parsed = $this->parseJsonResponse($result['content']);

        $this->logTask('seo_analyze', "วิเคราะห์ SEO: {$keyword}", $result);

        return [
            'seo_score'         => (int) ($parsed['seo_score'] ?? 0),
            'readability_score' => (int) ($parsed['readability_score'] ?? 0),
            'suggestions'       => $parsed['suggestions'] ?? [],
            'heading_analysis'  => $parsed['heading_analysis'] ?? '',
            'content_quality'   => $parsed['content_quality'] ?? '',
            'keyword_density'   => $keywordDensity,
            'word_count'        => $wordCount,
            'tokens_input'      => $result['tokens_input'],
            'tokens_output'     => $result['tokens_output'],
            'cost'              => $result['cost'],
        ];
    }

    /* ====================================================================
     *  Research
     * ==================================================================== */

    /**
     * ค้นหาบทความที่เกี่ยวข้องจากเว็บ (ใช้ SerpAPI หรือ fallback เป็น AI)
     */
    public function searchWeb(string $query): array
    {
        $serpApiKey = AppSetting::get('serp_api_key');

        if (!empty($serpApiKey)) {
            return $this->searchWithSerpApi($query, $serpApiKey);
        }

        // Fallback: ใช้ AI สร้างคำแนะนำแหล่งข้อมูล
        $prompt = <<<PROMPT
ค้นหาและแนะนำแหล่งข้อมูลเกี่ยวกับหัวข้อ: {$query}

ส่งกลับเป็น JSON:
{
  "results": [
    {
      "title": "ชื่อบทความ/แหล่งข้อมูล",
      "url": "URL (ถ้ามี)",
      "snippet": "สรุปสั้นๆ",
      "relevance": "high/medium/low"
    }
  ],
  "suggested_angles": ["มุมมอง/แง่มุมที่น่าสนใจ"]
}
PROMPT;

        $result = $this->callAi($prompt, [
            'system_prompt' => 'คุณเป็นนักวิจัยที่เชี่ยวชาญในการค้นหาข้อมูล ตอบกลับเป็น JSON',
            'json_mode'     => true,
        ]);

        $parsed = $this->parseJsonResponse($result['content']);

        return [
            'results'          => $parsed['results'] ?? [],
            'suggested_angles' => $parsed['suggested_angles'] ?? [],
            'source'           => 'ai_suggestion',
            'tokens_input'     => $result['tokens_input'],
            'tokens_output'    => $result['tokens_output'],
            'cost'             => $result['cost'],
        ];
    }

    /**
     * ดึงเนื้อหาหลักจาก URL
     */
    public function extractContentFromUrl(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; BlogBot/1.0)',
            ])
                ->timeout(15)
                ->get($url);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'error'   => "ไม่สามารถเข้าถึง URL: HTTP {$response->status()}",
                ];
            }

            $html = $response->body();

            // ดึง title
            $title = '';
            if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $matches)) {
                $title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
            }

            // ดึง meta description
            $metaDesc = '';
            if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/si', $html, $matches)) {
                $metaDesc = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
            }

            // ลบ script, style, nav, header, footer
            $cleaned = preg_replace('/<(script|style|nav|header|footer|aside|noscript)[^>]*>.*?<\/\1>/si', '', $html);

            // ดึง article / main content
            $mainContent = '';
            if (preg_match('/<(article|main)[^>]*>(.*?)<\/\1>/si', $cleaned, $matches)) {
                $mainContent = $matches[2];
            } else {
                // Fallback: ดึงจาก body
                if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $cleaned, $matches)) {
                    $mainContent = $matches[1];
                }
            }

            // ลบ tags เหลือแค่ text
            $text = strip_tags($mainContent);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            // ดึงรูปภาพ
            $images = [];
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $mainContent, $imgMatches)) {
                $images = array_slice($imgMatches[1], 0, 10);
            }

            return [
                'success'          => true,
                'title'            => $title,
                'meta_description' => $metaDesc,
                'content'          => mb_substr($text, 0, 10000),
                'images'           => $images,
                'url'              => $url,
                'word_count'       => $this->countWords($text),
            ];

        } catch (\Exception $e) {
            Log::warning('Failed to extract content from URL', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error'   => 'เกิดข้อผิดพลาดในการดึงเนื้อหา: ' . $e->getMessage(),
            ];
        }
    }

    /* ====================================================================
     *  Internal helpers
     * ==================================================================== */

    /**
     * เรียก AI provider ปัจจุบัน
     */
    private function callAi(string $prompt, array $options = []): array
    {
        return $this->getProvider()->generateContent($prompt, $options);
    }

    /**
     * สร้าง prompt สำหรับแต่ละประเภทงาน
     */
    private function buildPrompt(string $type, array $params): string
    {
        return match ($type) {
            'outline' => $this->buildOutlinePrompt($params),
            'expand_section' => $this->buildExpandSectionPrompt($params),
            'combine_article' => $this->buildCombineArticlePrompt($params),
            default => throw new \InvalidArgumentException("ไม่รู้จักประเภท prompt: {$type}"),
        };
    }

    /**
     * Prompt สำหรับสร้าง outline
     */
    private function buildOutlinePrompt(array $params): string
    {
        $topic     = $params['topic'];
        $language  = $params['language'];
        $tone      = $params['tone'];
        $wordCount = $params['word_count'];

        return <<<PROMPT
สร้าง outline สำหรับบทความเกี่ยวกับ: {$topic}

ข้อกำหนด:
- ภาษา: {$language}
- โทนเสียง: {$tone}
- จำนวนคำโดยประมาณ: {$wordCount} คำ
- ใช้ H2 สำหรับหัวข้อหลัก และ H3 สำหรับหัวข้อย่อย
- มี 4-7 หัวข้อหลัก
- แต่ละหัวข้อมี 2-4 หัวข้อย่อย

ส่งกลับเป็น JSON:
{
  "title": "ชื่อบทความ",
  "sections": [
    {
      "heading": "หัวข้อหลัก (H2)",
      "subheadings": ["หัวข้อย่อย (H3) 1", "หัวข้อย่อย (H3) 2"],
      "key_points": ["ประเด็นที่ต้องครอบคลุม"]
    }
  ]
}
PROMPT;
    }

    /**
     * Prompt สำหรับขยาย section
     */
    private function buildExpandSectionPrompt(array $params): string
    {
        $heading   = $params['heading'];
        $context   = $params['context'];
        $language  = $params['language'];
        $tone      = $params['tone'];
        $wordCount = $params['word_count'];

        return <<<PROMPT
ขยายหัวข้อต่อไปนี้เป็นเนื้อหาแบบเต็ม:

หัวข้อ: {$heading}
บริบท: {$context}

ข้อกำหนด:
- ภาษา: {$language}
- โทนเสียง: {$tone}
- ความยาว: ประมาณ {$wordCount} คำ
- ใช้ HTML tags สำหรับ formatting (<h3>, <p>, <ul>, <li>, <strong>)
- เพิ่มตัวอย่างและข้อมูลเชิงสถิติที่เกี่ยวข้อง
- เขียนให้น่าสนใจและให้ข้อมูลเชิงลึก

ส่งกลับเนื้อหา HTML เท่านั้น (ไม่ต้องใส่ <h2> ของหัวข้อหลัก เพราะจะเพิ่มภายหลัง)
PROMPT;
    }

    /**
     * Prompt สำหรับรวมบทความ
     */
    private function buildCombineArticlePrompt(array $params): string
    {
        $keyword    = $params['keyword'];
        $language   = $params['language'];
        $tone       = $params['tone'];
        $sections   = $params['sections'];
        $includeFaq = $params['include_faq'] ?? true;

        $sectionsText = '';
        foreach ($sections as $i => $section) {
            $num = $i + 1;
            $sectionsText .= "\n--- Section {$num}: {$section['heading']} ---\n";
            $sectionsText .= $section['content'] . "\n";
        }

        $faqInstruction = $includeFaq
            ? '- เพิ่ม FAQ section พร้อมคำถาม-คำตอบ 3-5 ข้อ'
            : '';

        return <<<PROMPT
รวมเนื้อหาต่อไปนี้เป็นบทความที่สมบูรณ์เกี่ยวกับ: {$keyword}

Sections:
{$sectionsText}

ข้อกำหนด:
- ภาษา: {$language}
- โทนเสียง: {$tone}
- เพิ่ม introduction ที่น่าสนใจ ดึงดูดผู้อ่าน
- รวม sections ให้ต่อเนื่องกันอย่างเป็นธรรมชาติ
- เพิ่ม transition ระหว่าง sections
- เขียน conclusion ที่สรุปครบถ้วน
{$faqInstruction}
- ใช้ HTML tags: <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>
- keyword หลัก "{$keyword}" ควรปรากฏ 3-5 ครั้งอย่างเป็นธรรมชาติ

ส่งกลับเป็น JSON:
{
  "title": "ชื่อบทความที่ดึงดูดและมี keyword",
  "content": "<h2>...</h2><p>...</p>...",
  "excerpt": "สรุปสั้นๆ 2-3 ประโยค",
  "tags": ["tag1", "tag2", "tag3"],
  "faq": [
    {"question": "คำถาม", "answer": "คำตอบ"}
  ]
}
PROMPT;
    }

    /**
     * ดึง system prompt ตามบทบาท
     */
    private function getSystemPrompt(string $role, string $language = 'th'): string
    {
        $langInstruction = $language === 'th'
            ? 'เขียนเป็นภาษาไทย'
            : 'Write in English';

        return match ($role) {
            'article_writer' => "คุณเป็นนักเขียนบทความ SEO มืออาชีพ สร้างเนื้อหาคุณภาพสูง มีโครงสร้างดี เหมาะกับ search engine {$langInstruction} ตอบกลับเป็น JSON เท่านั้น",
            'outline_creator' => "คุณเป็นผู้เชี่ยวชาญในการวางโครงสร้างบทความ สร้าง outline ที่ครอบคลุมและมีตรรกะ {$langInstruction} ตอบกลับเป็น JSON เท่านั้น",
            'section_writer' => "คุณเป็นนักเขียนเนื้อหาที่เชี่ยวชาญ เขียนเนื้อหาเชิงลึกที่น่าสนใจ มีข้อมูลสนับสนุน {$langInstruction}",
            'seo_expert' => "คุณเป็นผู้เชี่ยวชาญ SEO ที่รู้จักตลาดไทย วิเคราะห์และแนะนำการปรับปรุง SEO {$langInstruction}",
            default => "คุณเป็นผู้ช่วย AI ที่เป็นประโยชน์ {$langInstruction}",
        };
    }

    /**
     * ค้นหาด้วย SerpAPI
     */
    private function searchWithSerpApi(string $query, string $apiKey): array
    {
        try {
            $response = Http::timeout(15)->get('https://serpapi.com/search', [
                'q'       => $query,
                'api_key' => $apiKey,
                'engine'  => 'google',
                'hl'      => 'th',
                'gl'      => 'th',
                'num'     => 10,
            ]);

            if ($response->failed()) {
                Log::warning('SerpAPI request failed', ['status' => $response->status()]);
                return ['results' => [], 'source' => 'serp_api', 'error' => 'API request failed'];
            }

            $data    = $response->json();
            $results = [];

            foreach ($data['organic_results'] ?? [] as $item) {
                $results[] = [
                    'title'     => $item['title'] ?? '',
                    'url'       => $item['link'] ?? '',
                    'snippet'   => $item['snippet'] ?? '',
                    'position'  => $item['position'] ?? 0,
                    'relevance' => 'high',
                ];
            }

            return [
                'results' => $results,
                'source'  => 'serp_api',
            ];

        } catch (\Exception $e) {
            Log::warning('SerpAPI error', ['error' => $e->getMessage()]);
            return ['results' => [], 'source' => 'serp_api', 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse JSON จาก AI response (จัดการกรณีที่ AI ครอบ markdown fences)
     */
    private function parseJsonResponse(string $content): array
    {
        // ลบ markdown code fences ถ้ามี
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/i', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse AI JSON response', [
                'error'   => json_last_error_msg(),
                'content' => mb_substr($content, 0, 500),
            ]);

            // พยายามดึง JSON จากเนื้อหาที่มีข้อความแถมมา
            if (preg_match('/\{[\s\S]*\}/u', $content, $matches)) {
                $decoded = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }

            return ['content' => $content];
        }

        return $decoded;
    }

    /**
     * ดึง Table of Contents จาก HTML content
     */
    private function extractTableOfContents(string $htmlContent): array
    {
        $toc = [];

        if (preg_match_all('/<(h[23])[^>]*>(.*?)<\/\1>/si', $htmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $level = $match[1]; // h2 or h3
                $text  = strip_tags($match[2]);
                $id    = Str::slug($text);

                $toc[] = [
                    'level'   => $level === 'h2' ? 2 : 3,
                    'text'    => $text,
                    'id'      => $id,
                ];
            }
        }

        return $toc;
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

        // นับคำภาษาอังกฤษ
        $englishWords = str_word_count($text);

        // ประมาณจำนวนคำภาษาไทย (เฉลี่ย 6 ตัวอักษรต่อ 1 คำ)
        $thaiChars = preg_match_all('/[\x{0E00}-\x{0E7F}]/u', $text);
        $thaiWords = (int) ceil($thaiChars / 6);

        return $englishWords + $thaiWords;
    }

    /**
     * คำนวณ keyword density
     */
    private function calculateKeywordDensity(string $content, string $keyword): float
    {
        $text      = mb_strtolower(strip_tags($content));
        $keyword   = mb_strtolower($keyword);
        $wordCount = $this->countWords($text);

        if ($wordCount === 0) {
            return 0.0;
        }

        $keywordCount = mb_substr_count($text, $keyword);
        $keywordWords = str_word_count($keyword) ?: 1;

        return round(($keywordCount * $keywordWords / $wordCount) * 100, 2);
    }

    /**
     * Log ทุกการเรียก AI ลง blog_ai_tasks
     */
    private function logTask(string $type, string $prompt, array $result, ?int $postId = null): ?BlogAiTask
    {
        try {
            $provider = $this->getProvider();

            return BlogAiTask::create([
                'type'              => $type,
                'title'             => mb_substr($prompt, 0, 255),
                'prompt'            => mb_substr($prompt, 0, 65535),
                'output_data'       => json_encode($result, JSON_UNESCAPED_UNICODE),
                'provider'          => $provider->getProviderName(),
                'model'             => $result['model'] ?? $provider->getModelName(),
                'status'            => 'completed',
                'post_id'           => $postId,
                'admin_id'          => auth()->id(),
                'tokens_input'      => $result['tokens_input'] ?? 0,
                'tokens_output'     => $result['tokens_output'] ?? 0,
                'cost_usd'          => $result['cost'] ?? 0,
                'processing_time_ms'=> $result['processing_ms'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log AI task', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
