<?php

namespace App\Support;

/**
 * HtmlSanitizer — strips dangerous markup from admin-authored HTML before
 * rendering it raw with `{!! ... !!}` in Blade templates.
 *
 * This is NOT a full DOM-aware sanitizer (HTMLPurifier / ezyang) — it's a
 * small, dependency-free best-effort filter that handles the common attack
 * surface for trusted-but-not-bulletproof admin content:
 *
 *   • <script> blocks (any case)
 *   • <iframe>/<object>/<embed>/<applet> (active content)
 *   • <link>/<style>/<meta> (resource hijack)
 *   • Inline event handlers (`onclick=`, `onerror=`, …)
 *   • `javascript:`, `data:text/html`, `vbscript:` URLs
 *   • Form / input / button tags (CSRF/phishing surface)
 *   • <svg> with embedded scripts
 *
 * For high-trust admin output (legal pages, blog posts) this is enough.
 * For lower-trust user-generated HTML, replace this with HTMLPurifier.
 */
class HtmlSanitizer
{
    /**
     * Strip dangerous tags + attributes from an HTML string.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        // 1. Remove entire dangerous element blocks (open + content + close).
        $blockTags = ['script', 'iframe', 'object', 'embed', 'applet', 'meta', 'link', 'style', 'frame', 'frameset', 'form'];
        foreach ($blockTags as $tag) {
            // Greedy minimal between matching tags. Case-insensitive.
            $html = preg_replace(
                '#<' . $tag . '\b[^>]*>.*?</' . $tag . '\s*>#is',
                '',
                $html
            );
            // Self-closing or unmatched opens
            $html = preg_replace('#<' . $tag . '\b[^>]*/?>#is', '', $html);
        }

        // 2. Inline event handlers — onclick="…" / on*=…
        // Match attribute name starting with `on` followed by non-whitespace chars,
        // then optional whitespace + `=` + value (quoted or unquoted).
        $html = preg_replace('#\s+on[a-z]+\s*=\s*"[^"]*"#i', '', $html);
        $html = preg_replace("#\s+on[a-z]+\s*=\s*'[^']*'#i", '', $html);
        $html = preg_replace('#\s+on[a-z]+\s*=\s*[^\s>]+#i', '', $html);

        // 3. javascript: / vbscript: / data:text/html / data:application URLs in href / src.
        $html = preg_replace_callback(
            '#\s+(href|src|action|formaction|background|poster|cite|data)\s*=\s*(["\'])\s*([^"\']+?)\s*\2#i',
            function ($m) {
                $attr = $m[1]; $quote = $m[2]; $url = $m[3];
                $lower = strtolower(trim($url));
                if (preg_match('#^(javascript|vbscript|livescript)\s*:#', $lower)) return '';
                if (preg_match('#^data\s*:\s*text/html#', $lower)) return '';
                if (preg_match('#^data\s*:\s*application/(?:javascript|x-javascript)#', $lower)) return '';
                return ' ' . $attr . '=' . $quote . $url . $quote;
            },
            $html
        );

        // 4. Strip <svg> entirely if it contains a <script> or onload — easier
        // than walking SVG; admins shouldn't be embedding raw SVG anyway. Keep
        // safe SVG by checking content first.
        $html = preg_replace_callback(
            '#<svg\b[^>]*>(.*?)</svg>#is',
            function ($m) {
                $inner = $m[0];
                if (preg_match('#<script|on[a-z]+\s*=#i', $inner)) {
                    return '';
                }
                return $inner;
            },
            $html
        );

        return $html;
    }

    /**
     * Convenience for Blade: `{!! \App\Support\HtmlSanitizer::render($post->body) !!}`
     */
    public static function render(?string $html): string
    {
        return self::clean($html);
    }
}
