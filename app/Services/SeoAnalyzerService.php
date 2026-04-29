<?php

namespace App\Services;

/**
 * SEO Analyzer — analyzes a page's SEO health and returns score + issues.
 *
 * Usage:
 *   $analyzer = app(SeoAnalyzerService::class);
 *   $result = $analyzer->analyze($html, $url);
 *   // $result['score'], $result['issues'], $result['passes']
 */
class SeoAnalyzerService
{
    private const IDEAL_TITLE_MIN = 30;
    private const IDEAL_TITLE_MAX = 60;
    private const IDEAL_META_DESC_MIN = 120;
    private const IDEAL_META_DESC_MAX = 160;
    private const IDEAL_WORD_COUNT_MIN = 300;

    /**
     * Analyze HTML content and return SEO scorecard.
     */
    public function analyze(string $html, string $url = ''): array
    {
        $issues = [];
        $passes = [];
        $warnings = [];

        // Parse HTML
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

        // ═════════ Checks ═════════

        // 1. Title
        $title = $this->getNodeContent($xpath, '//title');
        $titleLen = mb_strlen($title);
        if (!$title) {
            $issues[] = ['type' => 'critical', 'key' => 'title_missing', 'message' => 'ไม่มี <title> tag'];
        } elseif ($titleLen < self::IDEAL_TITLE_MIN) {
            $warnings[] = ['type' => 'warning', 'key' => 'title_short', 'message' => "Title สั้นเกินไป ({$titleLen} chars, ควร " . self::IDEAL_TITLE_MIN . "-" . self::IDEAL_TITLE_MAX . ")"];
        } elseif ($titleLen > self::IDEAL_TITLE_MAX) {
            $warnings[] = ['type' => 'warning', 'key' => 'title_long', 'message' => "Title ยาวเกินไป ({$titleLen} chars, จะถูกตัดใน Google)"];
        } else {
            $passes[] = ['key' => 'title_ok', 'message' => "Title ยาวเหมาะสม ({$titleLen} chars)"];
        }

        // 2. Meta description
        $metaDesc = $this->getAttribute($xpath, '//meta[@name="description"]', 'content');
        $descLen = mb_strlen($metaDesc);
        if (!$metaDesc) {
            $issues[] = ['type' => 'critical', 'key' => 'meta_desc_missing', 'message' => 'ไม่มี meta description'];
        } elseif ($descLen < self::IDEAL_META_DESC_MIN) {
            $warnings[] = ['type' => 'warning', 'key' => 'meta_desc_short', 'message' => "Meta description สั้น ({$descLen} chars, ควร " . self::IDEAL_META_DESC_MIN . "-" . self::IDEAL_META_DESC_MAX . ")"];
        } elseif ($descLen > self::IDEAL_META_DESC_MAX) {
            $warnings[] = ['type' => 'warning', 'key' => 'meta_desc_long', 'message' => "Meta description ยาวเกิน ({$descLen} chars)"];
        } else {
            $passes[] = ['key' => 'meta_desc_ok', 'message' => "Meta description ยาวเหมาะสม ({$descLen} chars)"];
        }

        // 3. H1 tag
        $h1s = $xpath->query('//h1');
        if ($h1s->length === 0) {
            $issues[] = ['type' => 'critical', 'key' => 'h1_missing', 'message' => 'ไม่มี <h1> tag'];
        } elseif ($h1s->length > 1) {
            $warnings[] = ['type' => 'warning', 'key' => 'h1_multiple', 'message' => "มี <h1> หลายตัว ({$h1s->length}) — ควรมีแค่ 1 ตัว"];
        } else {
            $passes[] = ['key' => 'h1_ok', 'message' => 'มี <h1> tag ครั้งเดียว'];
        }

        // 4. Heading hierarchy
        $h1Count = $xpath->query('//h1')->length;
        $h2Count = $xpath->query('//h2')->length;
        $h3Count = $xpath->query('//h3')->length;
        if ($h1Count > 0 && $h2Count === 0 && $h3Count > 0) {
            $warnings[] = ['type' => 'warning', 'key' => 'heading_skip', 'message' => 'ข้าม H2 (มี H1 และ H3 แต่ไม่มี H2)'];
        }

        // 5. Canonical URL
        $canonical = $this->getAttribute($xpath, '//link[@rel="canonical"]', 'href');
        if (!$canonical) {
            $warnings[] = ['type' => 'warning', 'key' => 'canonical_missing', 'message' => 'ไม่มี canonical URL'];
        } else {
            $passes[] = ['key' => 'canonical_ok', 'message' => 'มี canonical URL'];
        }

        // 6. Viewport meta
        $viewport = $this->getAttribute($xpath, '//meta[@name="viewport"]', 'content');
        if (!$viewport) {
            $issues[] = ['type' => 'critical', 'key' => 'viewport_missing', 'message' => 'ไม่มี viewport meta (ไม่ mobile-friendly)'];
        } else {
            $passes[] = ['key' => 'viewport_ok', 'message' => 'มี viewport meta'];
        }

        // 7. OG tags
        $ogTitle = $this->getAttribute($xpath, '//meta[@property="og:title"]', 'content');
        $ogImage = $this->getAttribute($xpath, '//meta[@property="og:image"]', 'content');
        $ogDescription = $this->getAttribute($xpath, '//meta[@property="og:description"]', 'content');
        $ogMissing = [];
        if (!$ogTitle) $ogMissing[] = 'og:title';
        if (!$ogImage) $ogMissing[] = 'og:image';
        if (!$ogDescription) $ogMissing[] = 'og:description';

        if (count($ogMissing) === 0) {
            $passes[] = ['key' => 'og_tags_ok', 'message' => 'Open Graph tags ครบ'];
        } else {
            $warnings[] = ['type' => 'warning', 'key' => 'og_incomplete', 'message' => 'OG tags ไม่ครบ: ' . implode(', ', $ogMissing)];
        }

        // 8. Twitter Card
        $twitterCard = $this->getAttribute($xpath, '//meta[@name="twitter:card"]', 'content');
        if (!$twitterCard) {
            $warnings[] = ['type' => 'warning', 'key' => 'twitter_missing', 'message' => 'ไม่มี Twitter Card meta'];
        } else {
            $passes[] = ['key' => 'twitter_ok', 'message' => 'มี Twitter Card'];
        }

        // 9. Images without alt
        $allImages = $xpath->query('//img');
        $imagesWithoutAlt = 0;
        foreach ($allImages as $img) {
            $alt = $img->getAttribute('alt');
            if ($alt === '' || $alt === null) {
                $imagesWithoutAlt++;
            }
        }
        if ($allImages->length > 0) {
            if ($imagesWithoutAlt > 0) {
                $warnings[] = ['type' => 'warning', 'key' => 'images_no_alt', 'message' => "มี {$imagesWithoutAlt}/{$allImages->length} รูปภาพไม่มี alt text"];
            } else {
                $passes[] = ['key' => 'images_alt_ok', 'message' => "รูปภาพทั้งหมด ({$allImages->length}) มี alt text"];
            }
        }

        // 10. JSON-LD Structured Data
        $jsonLdScripts = $xpath->query('//script[@type="application/ld+json"]');
        if ($jsonLdScripts->length === 0) {
            $warnings[] = ['type' => 'warning', 'key' => 'no_schema', 'message' => 'ไม่มี JSON-LD structured data'];
        } else {
            $passes[] = ['key' => 'schema_ok', 'message' => "มี JSON-LD {$jsonLdScripts->length} schemas"];
        }

        // 11. Word count
        $bodyText = $this->getNodeContent($xpath, '//body');
        $wordCount = str_word_count(strip_tags($bodyText));
        // Thai doesn't have word boundaries, so estimate differently
        $cleanText = trim(preg_replace('/\s+/', ' ', strip_tags($bodyText)));
        $charCount = mb_strlen($cleanText);
        $estimatedWords = (int) ($charCount / 5); // Rough estimate for Thai

        if ($estimatedWords < self::IDEAL_WORD_COUNT_MIN) {
            $warnings[] = ['type' => 'warning', 'key' => 'low_word_count', 'message' => "เนื้อหาน้อย (~{$estimatedWords} คำ, แนะนำ " . self::IDEAL_WORD_COUNT_MIN . "+)"];
        } else {
            $passes[] = ['key' => 'word_count_ok', 'message' => "เนื้อหาเพียงพอ (~{$estimatedWords} คำ)"];
        }

        // 12. Internal links
        $allLinks = $xpath->query('//a[@href]');
        $internalLinks = 0;
        $externalLinks = 0;
        $host = $url ? parse_url($url, PHP_URL_HOST) : '';
        foreach ($allLinks as $link) {
            $href = $link->getAttribute('href');
            if (str_starts_with($href, '/') || ($host && str_contains($href, $host))) {
                $internalLinks++;
            } elseif (str_starts_with($href, 'http')) {
                $externalLinks++;
            }
        }

        if ($internalLinks === 0 && $allLinks->length > 0) {
            $warnings[] = ['type' => 'warning', 'key' => 'no_internal_links', 'message' => 'ไม่มี internal links — แนะนำเชื่อมโยงหน้าอื่นในเว็บ'];
        }

        // ═════════ Calculate Score ═════════

        $criticalCount = count($issues);
        $warningCount = count($warnings);
        $passCount = count($passes);

        $score = 100;
        $score -= ($criticalCount * 15); // Critical = -15
        $score -= ($warningCount * 5);   // Warning = -5
        $score = max(0, min(100, $score));

        $grade = match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default      => 'F',
        };

        return [
            'score'     => $score,
            'grade'     => $grade,
            'issues'    => $issues,
            'warnings'  => $warnings,
            'passes'    => $passes,
            'stats'     => [
                'title_length'       => $titleLen,
                'desc_length'        => $descLen,
                'h1_count'           => $h1Count,
                'h2_count'           => $h2Count,
                'h3_count'           => $h3Count,
                'images_total'       => $allImages->length,
                'images_without_alt' => $imagesWithoutAlt,
                'internal_links'     => $internalLinks,
                'external_links'     => $externalLinks,
                'word_count'         => $estimatedWords,
                'schemas_count'      => $jsonLdScripts->length,
            ],
            'meta' => [
                'title'       => $title,
                'description' => $metaDesc,
                'canonical'   => $canonical,
                'og_title'    => $ogTitle,
                'og_image'    => $ogImage,
                'twitter_card' => $twitterCard,
            ],
        ];
    }

    /**
     * Fetch a URL and analyze.
     *
     * SSRF DEFENSE: only http(s) URLs, reject private/loopback/reserved IPs.
     * Without this guard an admin (or hijacked admin session) could use this
     * endpoint as an outbound-request oracle:
     *   - http://169.254.169.254/…        AWS IMDS credentials
     *   - http://localhost:3306/          internal DB port scan
     *   - file:///etc/passwd              local file read
     *   - http://10.0.0.1/admin           internal network pivoting
     * The response body is rendered back to the admin, making it a full
     * read-SSRF sink. We harden by scheme allowlist + DNS-resolved IP check.
     */
    public function analyzeUrl(string $url): array
    {
        $safety = $this->assertFetchable($url);
        if ($safety !== null) {
            return [
                'error'   => $safety,
                'score'   => 0,
                'grade'   => 'F',
                'issues'  => [['type' => 'critical', 'key' => 'url_blocked', 'message' => $safety]],
            ];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withOptions([
                    'allow_redirects' => [
                        'max'             => 3,
                        'strict'          => true,
                        'protocols'       => ['http', 'https'],
                        'track_redirects' => true,
                    ],
                ])
                ->withHeaders(['User-Agent' => 'SEO-Analyzer/1.0'])
                ->get($url);

            // After redirects, re-verify the final URL wasn't bounced into a
            // private range (defense against TOCTOU via DNS rebinding / 30x
            // redirect to http://169.254.169.254/).
            $finalUrl = (string) ($response->effectiveUri() ?? $url);
            if ($finalUrl !== $url) {
                $redirectSafety = $this->assertFetchable($finalUrl);
                if ($redirectSafety !== null) {
                    return [
                        'error'  => 'Redirect ถูกบล็อก: ' . $redirectSafety,
                        'score'  => 0,
                        'grade'  => 'F',
                        'issues' => [['type' => 'critical', 'key' => 'redirect_blocked', 'message' => $redirectSafety]],
                    ];
                }
            }

            if (!$response->successful()) {
                return [
                    'error'   => "HTTP {$response->status()}: ไม่สามารถเข้าถึง URL ได้",
                    'score'   => 0,
                    'grade'   => 'F',
                    'issues'  => [['type' => 'critical', 'key' => 'http_error', 'message' => "HTTP {$response->status()}"]],
                ];
            }

            return array_merge(
                $this->analyze($response->body(), $url),
                ['url' => $url, 'fetched_at' => now()->toIso8601String()]
            );
        } catch (\Throwable $e) {
            return [
                'error'  => $e->getMessage(),
                'score'  => 0,
                'grade'  => 'F',
                'issues' => [['type' => 'critical', 'key' => 'fetch_error', 'message' => 'ไม่สามารถดึงหน้าเว็บได้']],
            ];
        }
    }

    // ─── Helpers ───

    private function getNodeContent(\DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : '';
    }

    private function getAttribute(\DOMXPath $xpath, string $query, string $attr): string
    {
        $nodes = $xpath->query($query);
        return $nodes->length > 0 ? trim($nodes->item(0)->getAttribute($attr)) : '';
    }

    /**
     * Returns null if the URL is safe to fetch, otherwise a user-facing
     * error string explaining why we blocked it.
     *
     * Blocks:
     *   • Schemes other than http/https (file://, gopher://, dict://, ftp://…)
     *   • Hosts whose resolved IPs fall into private/loopback/reserved ranges
     *   • Cloud metadata endpoints (169.254.169.254, metadata.google.internal…)
     *   • Raw IP URLs pointing into private space
     */
    private function assertFetchable(string $url): ?string
    {
        $parts = @parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return 'URL ไม่ถูกต้อง';
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return "ไม่รองรับ scheme '{$scheme}' — ใช้ได้เฉพาะ http:// หรือ https://";
        }

        $host = strtolower($parts['host']);

        // Deny-list for well-known cloud metadata + obvious aliases.
        $blockedHosts = [
            'localhost',
            'metadata.google.internal',
            'metadata',
            'instance-data',
        ];
        if (in_array($host, $blockedHosts, true)) {
            return "ไม่อนุญาตให้วิเคราะห์ host '{$host}' (internal/metadata endpoint)";
        }

        // Resolve every A-record; a host can have multiple IPs and we need
        // them all to be public. `gethostbynamel()` returns false on failure.
        $ips = @gethostbynamel($host);
        if ($ips === false || empty($ips)) {
            // If it's already an IP literal, check that.
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                $ips = [$host];
            } else {
                return "ไม่พบ DNS record ของ '{$host}'";
            }
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return "IP '{$ip}' อยู่ใน private/reserved range — ถูกบล็อกเพื่อป้องกัน SSRF";
            }
        }

        return null;
    }

    /**
     * True only for public-routable IPs (IPv4 + IPv6).
     * Uses FILTER_FLAG_NO_PRIV_RANGE + FILTER_FLAG_NO_RES_RANGE which
     * already rejects 10/8, 172.16/12, 192.168/16, 127/8, 169.254/16,
     * ::1, fc00::/7, etc. We add an explicit 0.0.0.0 check for paranoia.
     */
    private function isPublicIp(string $ip): bool
    {
        if ($ip === '0.0.0.0' || $ip === '::') return false;

        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, ['flags' => $flags]);
    }
}
