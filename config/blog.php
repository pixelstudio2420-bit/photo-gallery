<?php

return [
    'ai' => [
        'default_provider' => env('BLOG_AI_PROVIDER', 'openai'),
        'providers' => [
            'openai' => [
                'api_key' => env('OPENAI_API_KEY'),
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'max_tokens' => 4096,
                'temperature' => 0.7,
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
            ],
            'claude' => [
                'api_key' => env('ANTHROPIC_API_KEY'),
                'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
                'max_tokens' => 4096,
                'temperature' => 0.7,
                'endpoint' => 'https://api.anthropic.com/v1/messages',
            ],
            'gemini' => [
                'api_key' => env('GEMINI_API_KEY'),
                'model' => env('GEMINI_MODEL', 'gemini-pro'),
                'max_tokens' => 4096,
                'temperature' => 0.7,
            ],
        ],
    ],
    'news' => [
        'fetch_interval' => env('BLOG_NEWS_FETCH_INTERVAL', 6), // hours
        'max_items_per_source' => 20,
        'auto_summarize' => env('BLOG_AUTO_SUMMARIZE', true),
        'auto_publish' => env('BLOG_AUTO_PUBLISH', false),
    ],
    'seo' => [
        'min_word_count' => 300,
        'ideal_word_count' => 1500,
        'max_meta_title' => 60,
        'max_meta_description' => 160,
        'ideal_keyword_density' => 1.5, // percentage
        'max_keyword_density' => 3.0,
    ],
    'affiliate' => [
        'link_prefix' => '/go',
        'default_nofollow' => true,
        'click_cooldown_minutes' => 30, // prevent duplicate clicks
    ],
];
