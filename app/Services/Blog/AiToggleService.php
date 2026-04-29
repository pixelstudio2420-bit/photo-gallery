<?php

namespace App\Services\Blog;

use App\Models\AppSetting;

/**
 * AI Toggle Service — manages enable/disable state for AI tools and providers.
 *
 * Settings are stored in app_settings table with keys:
 *  - blog_ai_master_enabled       : '0' or '1' — master switch
 *  - blog_ai_tool_{tool}          : '0' or '1' — per-tool switch
 *  - blog_ai_provider_{provider}  : '0' or '1' — per-provider switch
 *
 * Default: all enabled unless explicitly disabled.
 */
class AiToggleService
{
    /**
     * All available AI tools (matches BlogAiController actions).
     */
    public const TOOLS = [
        'generate'        => ['label' => 'สร้างบทความ',      'icon' => 'file-earmark-plus', 'desc' => 'สร้างบทความใหม่จากคีย์เวิร์ด'],
        'summarize'       => ['label' => 'สรุปเนื้อหา',        'icon' => 'body-text',         'desc' => 'สรุปข้อความหรือ URL ยาวๆ'],
        'rewrite'         => ['label' => 'เขียนใหม่',          'icon' => 'pencil-square',     'desc' => 'เขียน/ปรับปรุงเนื้อหาใหม่'],
        'research'        => ['label' => 'ค้นหาข้อมูล',        'icon' => 'search',            'desc' => 'ค้นหาข้อมูลจากอินเทอร์เน็ต'],
        'keyword-suggest' => ['label' => 'แนะนำคีย์เวิร์ด',   'icon' => 'tags',              'desc' => 'หาคีย์เวิร์ด SEO ที่เกี่ยวข้อง'],
        'seo-analyze'     => ['label' => 'วิเคราะห์ SEO',     'icon' => 'graph-up',          'desc' => 'วิเคราะห์และให้คะแนน SEO'],
        'generate-meta'   => ['label' => 'สร้าง Meta Tags',   'icon' => 'tag',               'desc' => 'สร้าง meta title/description'],
        'news-fetch'      => ['label' => 'ดึงข่าวอัตโนมัติ',   'icon' => 'rss',               'desc' => 'ดึงข่าวจาก RSS feeds'],
        'translate'       => ['label' => 'แปลภาษา',          'icon' => 'translate',         'desc' => 'แปลเนื้อหาเป็นภาษาอื่น'],
    ];

    /**
     * All available AI providers.
     */
    public const PROVIDERS = [
        'openai' => ['label' => 'OpenAI',       'icon' => '🤖', 'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo']],
        'claude' => ['label' => 'Anthropic Claude', 'icon' => '🧠', 'models' => ['claude-sonnet-4-5', 'claude-opus-4', 'claude-sonnet-4']],
        'gemini' => ['label' => 'Google Gemini', 'icon' => '💎', 'models' => ['gemini-pro', 'gemini-pro-vision']],
    ];

    /**
     * Check if AI system is globally enabled.
     */
    public function isMasterEnabled(): bool
    {
        return AppSetting::get('blog_ai_master_enabled', '1') === '1';
    }

    /**
     * Check if a specific tool is enabled.
     */
    public function isToolEnabled(string $tool): bool
    {
        if (!$this->isMasterEnabled()) return false;
        if (!array_key_exists($tool, self::TOOLS)) return false;
        return AppSetting::get("blog_ai_tool_{$tool}", '1') === '1';
    }

    /**
     * Check if a provider is enabled.
     */
    public function isProviderEnabled(string $provider): bool
    {
        if (!$this->isMasterEnabled()) return false;
        if (!array_key_exists($provider, self::PROVIDERS)) return false;

        // Must be explicitly enabled AND have an API key configured
        $enabled = AppSetting::get("blog_ai_provider_{$provider}", '1') === '1';
        if (!$enabled) return false;

        return $this->hasApiKey($provider);
    }

    /**
     * Check if the provider has an API key configured (in config or settings).
     */
    public function hasApiKey(string $provider): bool
    {
        $key = match ($provider) {
            'openai' => config('blog.ai.providers.openai.api_key') ?: AppSetting::get('openai_api_key'),
            'claude' => config('blog.ai.providers.claude.api_key') ?: AppSetting::get('anthropic_api_key'),
            'gemini' => config('blog.ai.providers.gemini.api_key') ?: AppSetting::get('gemini_api_key'),
            default  => null,
        };
        return !empty($key);
    }

    /**
     * Get the default (preferred) enabled provider.
     */
    public function defaultProvider(): ?string
    {
        if (!$this->isMasterEnabled()) return null;

        // Prefer the one configured in settings
        $preferred = AppSetting::get('blog_ai_default_provider', config('blog.ai.default_provider', 'openai'));
        if ($preferred && $this->isProviderEnabled($preferred)) {
            return $preferred;
        }

        // Fallback: any enabled provider
        foreach (array_keys(self::PROVIDERS) as $p) {
            if ($this->isProviderEnabled($p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Get list of enabled providers.
     */
    public function enabledProviders(): array
    {
        if (!$this->isMasterEnabled()) return [];

        $result = [];
        foreach (self::PROVIDERS as $key => $meta) {
            if ($this->isProviderEnabled($key)) {
                $result[$key] = $meta;
            }
        }
        return $result;
    }

    /**
     * Get list of enabled tools.
     */
    public function enabledTools(): array
    {
        if (!$this->isMasterEnabled()) return [];

        $result = [];
        foreach (self::TOOLS as $key => $meta) {
            if ($this->isToolEnabled($key)) {
                $result[$key] = $meta;
            }
        }
        return $result;
    }

    /**
     * Get full status matrix (used by admin UI).
     */
    public function statusMatrix(): array
    {
        return [
            'master'    => $this->isMasterEnabled(),
            'tools'     => array_map(fn($k) => [
                'key'         => $k,
                'meta'        => self::TOOLS[$k],
                'enabled'     => $this->isToolEnabled($k),
            ], array_keys(self::TOOLS)),
            'providers' => array_map(fn($k) => [
                'key'         => $k,
                'meta'        => self::PROVIDERS[$k],
                'enabled'     => AppSetting::get("blog_ai_provider_{$k}", '1') === '1',
                'has_api_key' => $this->hasApiKey($k),
                'usable'      => $this->isProviderEnabled($k),
            ], array_keys(self::PROVIDERS)),
        ];
    }

    /**
     * Save toggle settings from admin form.
     */
    public function saveSettings(array $data): void
    {
        // Master
        AppSetting::set('blog_ai_master_enabled', !empty($data['master']) ? '1' : '0');

        // Tools
        foreach (array_keys(self::TOOLS) as $tool) {
            AppSetting::set("blog_ai_tool_{$tool}", !empty($data['tools'][$tool]) ? '1' : '0');
        }

        // Providers
        foreach (array_keys(self::PROVIDERS) as $provider) {
            AppSetting::set("blog_ai_provider_{$provider}", !empty($data['providers'][$provider]) ? '1' : '0');
        }

        // Optional: save default provider + model + temperature
        if (!empty($data['default_provider'])) {
            AppSetting::set('blog_ai_default_provider', $data['default_provider']);
        }
        if (!empty($data['default_model'])) {
            AppSetting::set('blog_ai_default_model', $data['default_model']);
        }
        if (isset($data['temperature'])) {
            AppSetting::set('blog_ai_temperature', (string) $data['temperature']);
        }
    }

    /**
     * Throw exception if a tool is disabled (for use in controllers).
     */
    public function assertToolEnabled(string $tool): void
    {
        if (!$this->isMasterEnabled()) {
            throw new \RuntimeException('ระบบ AI ถูกปิดใช้งานโดย Admin');
        }
        if (!$this->isToolEnabled($tool)) {
            $label = self::TOOLS[$tool]['label'] ?? $tool;
            throw new \RuntimeException("เครื่องมือ \"{$label}\" ถูกปิดใช้งาน กรุณาติดต่อ Admin");
        }
    }

    /**
     * Throw exception if a provider is not usable.
     */
    public function assertProviderUsable(string $provider): void
    {
        if (!$this->isMasterEnabled()) {
            throw new \RuntimeException('ระบบ AI ถูกปิดใช้งานโดย Admin');
        }
        if (!$this->isProviderEnabled($provider)) {
            if (!$this->hasApiKey($provider)) {
                throw new \RuntimeException("Provider '{$provider}' ยังไม่ได้ตั้ง API key");
            }
            throw new \RuntimeException("Provider '{$provider}' ถูกปิดใช้งาน");
        }
    }
}
