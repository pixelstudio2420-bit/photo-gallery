<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\SeoService;

class SeoController extends Controller
{
    public function sitemap(SeoService $seo)
    {
        $content = $seo->generateSitemap();
        return response($content, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function robots(SeoService $seo)
    {
        $content = $seo->generateRobotsTxt();
        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
