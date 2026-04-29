<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SeoAnalyzerService;
use App\Services\SeoService;
use Illuminate\Http\Request;

class SeoAnalyzerController extends Controller
{
    public function __construct(
        private SeoAnalyzerService $analyzer,
        private SeoService $seo
    ) {
    }

    /**
     * SEO analyzer dashboard.
     */
    public function index(Request $request)
    {
        $result = null;
        $url = $request->input('url');

        if ($url) {
            $result = $this->analyzer->analyzeUrl($url);
            $result['url'] = $url;
        }

        // Pre-suggest common URLs
        $baseUrl = rtrim(config('app.url', url('/')), '/');
        $suggestions = [
            ['label' => 'หน้าแรก',     'url' => $baseUrl . '/'],
            ['label' => 'อีเวนต์',     'url' => $baseUrl . '/events'],
            ['label' => 'บล็อก',       'url' => $baseUrl . '/blog'],
            ['label' => 'ช่างภาพ',     'url' => $baseUrl . '/photographers'],
            ['label' => 'สินค้า',      'url' => $baseUrl . '/products'],
            ['label' => 'ติดต่อ',      'url' => $baseUrl . '/contact'],
        ];

        // Quick stats from sitemap
        $stats = $this->getSitemapStats();

        return view('admin.seo.analyzer', compact('result', 'url', 'suggestions', 'stats'));
    }

    /**
     * Trigger sitemap generation/cache refresh.
     */
    public function refreshSitemap()
    {
        // Generate and cache sitemap
        $sitemap = $this->seo->generateSitemap();
        \Cache::put('seo:sitemap', $sitemap, now()->addHours(24));

        return back()->with('success', 'อัปเดต sitemap เรียบร้อย (' . number_format(substr_count($sitemap, '<url>')) . ' URLs)');
    }

    protected function getSitemapStats(): array
    {
        $sitemap = $this->seo->generateSitemap();

        return [
            'total_urls'  => substr_count($sitemap, '<url>'),
            'size_kb'     => round(strlen($sitemap) / 1024, 1),
            'cached'      => \Cache::has('seo:sitemap'),
            'last_generated' => now()->format('d/m/Y H:i'),
        ];
    }
}
