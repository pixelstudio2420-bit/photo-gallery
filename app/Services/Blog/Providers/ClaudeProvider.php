<?php

namespace App\Services\Blog\Providers;

use App\Services\Blog\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ClaudeProvider -- เชื่อมต่อ Anthropic Messages API
 *
 * รองรับ model: claude-sonnet-4-20250514, claude-3-haiku และอื่นๆ
 * ใช้ header x-api-key + anthropic-version: 2023-06-01
 */
class ClaudeProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $endpoint;
    private int    $maxTokens;
    private float  $temperature;

    /**
     * ราคาต่อ 1 ล้าน token (USD): [input, output]
     */
    private const MODEL_PRICING = [
        'claude-sonnet-4-20250514' => ['input' => 3.00,  'output' => 15.00],
        'claude-3-5-sonnet'     => ['input' => 3.00,  'output' => 15.00],
        'claude-3-opus'         => ['input' => 15.00, 'output' => 75.00],
        'claude-3-sonnet'       => ['input' => 3.00,  'output' => 15.00],
        'claude-3-haiku'        => ['input' => 0.25,  'output' => 1.25],
    ];

    public function __construct()
    {
        $config = config('blog.ai.providers.claude');

        $this->apiKey      = $config['api_key'] ?? '';
        $this->model       = $config['model'] ?? 'claude-sonnet-4-20250514';
        $this->endpoint    = $config['endpoint'] ?? 'https://api.anthropic.com/v1/messages';
        $this->maxTokens   = $config['max_tokens'] ?? 4096;
        $this->temperature = $config['temperature'] ?? 0.7;
    }

    /* ------------------------------------------------------------------ */
    /*  AiProviderInterface                                               */
    /* ------------------------------------------------------------------ */

    /**
     * {@inheritDoc}
     */
    public function generateContent(string $prompt, array $options = []): array
    {
        $this->ensureApiKey();

        $systemPrompt = $options['system_prompt']
            ?? 'You are an expert content writer. Always respond in the language specified by the user.';

        $payload = [
            'model'       => $options['model'] ?? $this->model,
            'max_tokens'  => $options['max_tokens'] ?? $this->maxTokens,
            'temperature' => $options['temperature'] ?? $this->temperature,
            'system'      => $systemPrompt,
            'messages'    => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version'  => '2023-06-01',
                'Content-Type'       => 'application/json',
            ])
                ->timeout($options['timeout'] ?? 120)
                ->post($this->endpoint, $payload);

            if ($response->failed()) {
                $errorBody = $response->json();
                $errorMsg  = $errorBody['error']['message'] ?? $response->body();

                Log::error('Claude API error', [
                    'status'  => $response->status(),
                    'error'   => $errorMsg,
                    'model'   => $payload['model'],
                ]);

                throw new \RuntimeException("Claude API error ({$response->status()}): {$errorMsg}");
            }

            $data = $response->json();

            // Claude ส่ง content กลับมาเป็น array ของ blocks
            $content = '';
            foreach ($data['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $content .= $block['text'];
                }
            }

            $tokensInput  = $data['usage']['input_tokens'] ?? 0;
            $tokensOutput = $data['usage']['output_tokens'] ?? 0;
            $cost         = $this->calculateCost($payload['model'], $tokensInput, $tokensOutput);
            $elapsed      = (int) round((microtime(true) - $startTime) * 1000);

            Log::info('Claude request completed', [
                'model'           => $payload['model'],
                'tokens_input'    => $tokensInput,
                'tokens_output'   => $tokensOutput,
                'cost_usd'        => $cost,
                'processing_ms'   => $elapsed,
            ]);

            return [
                'content'        => $content,
                'tokens_input'   => $tokensInput,
                'tokens_output'  => $tokensOutput,
                'cost'           => $cost,
                'model'          => $payload['model'],
                'processing_ms'  => $elapsed,
                'stop_reason'    => $data['stop_reason'] ?? null,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Claude connection timeout', ['message' => $e->getMessage()]);
            throw new \RuntimeException('การเชื่อมต่อ Claude หมดเวลา กรุณาลองใหม่อีกครั้ง');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getProviderName(): string
    {
        return 'claude';
    }

    /**
     * {@inheritDoc}
     */
    public function getModelName(): string
    {
        return $this->model;
    }

    /* ------------------------------------------------------------------ */
    /*  Internal helpers                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * คำนวณค่าใช้จ่ายจาก token ที่ใช้
     */
    private function calculateCost(string $model, int $tokensInput, int $tokensOutput): float
    {
        $pricing = self::MODEL_PRICING[$model] ?? null;

        if ($pricing === null) {
            foreach (self::MODEL_PRICING as $key => $price) {
                if (str_contains($model, $key) || str_starts_with($model, $key)) {
                    $pricing = $price;
                    break;
                }
            }
        }

        // default: claude-3-sonnet pricing
        $pricing ??= self::MODEL_PRICING['claude-3-sonnet'];

        $inputCost  = ($tokensInput / 1_000_000) * $pricing['input'];
        $outputCost = ($tokensOutput / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * ตรวจสอบว่ามี API key หรือไม่
     */
    private function ensureApiKey(): void
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('ไม่พบ Anthropic API key กรุณาตั้งค่า ANTHROPIC_API_KEY ในไฟล์ .env');
        }
    }
}
