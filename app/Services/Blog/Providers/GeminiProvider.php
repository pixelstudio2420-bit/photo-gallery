<?php

namespace App\Services\Blog\Providers;

use App\Services\Blog\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GeminiProvider -- เชื่อมต่อ Google Gemini API
 *
 * รองรับ model: gemini-pro, gemini-1.5-pro, gemini-1.5-flash
 * API key ส่งผ่าน query parameter
 */
class GeminiProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model;
    private int    $maxTokens;
    private float  $temperature;

    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1/models';

    /**
     * ราคาต่อ 1 ล้าน token (USD): [input, output]
     * gemini-pro free tier = $0, paid tier มีราคาดังนี้
     */
    private const MODEL_PRICING = [
        'gemini-pro'        => ['input' => 0.50,  'output' => 1.50],
        'gemini-1.5-pro'    => ['input' => 3.50,  'output' => 10.50],
        'gemini-1.5-flash'  => ['input' => 0.075, 'output' => 0.30],
        'gemini-2.0-flash'  => ['input' => 0.10,  'output' => 0.40],
    ];

    public function __construct()
    {
        $config = config('blog.ai.providers.gemini');

        $this->apiKey      = $config['api_key'] ?? '';
        $this->model       = $config['model'] ?? 'gemini-pro';
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

        $model    = $options['model'] ?? $this->model;
        $endpoint = self::BASE_URL . "/{$model}:generateContent";

        $systemPrompt = $options['system_prompt']
            ?? 'You are an expert content writer. Always respond in the language specified by the user.';

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? $this->maxTokens,
                'temperature'     => $options['temperature'] ?? $this->temperature,
            ],
        ];

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
                ->timeout($options['timeout'] ?? 120)
                ->post($endpoint . '?key=' . $this->apiKey, $payload);

            if ($response->failed()) {
                $errorBody = $response->json();
                $errorMsg  = $errorBody['error']['message'] ?? $response->body();

                Log::error('Gemini API error', [
                    'status'  => $response->status(),
                    'error'   => $errorMsg,
                    'model'   => $model,
                ]);

                throw new \RuntimeException("Gemini API error ({$response->status()}): {$errorMsg}");
            }

            $data = $response->json();

            // ดึง text จาก candidates
            $content = '';
            $candidates = $data['candidates'] ?? [];
            if (!empty($candidates)) {
                $parts = $candidates[0]['content']['parts'] ?? [];
                foreach ($parts as $part) {
                    $content .= $part['text'] ?? '';
                }
            }

            // Gemini ส่ง usage metadata ผ่าน usageMetadata
            $tokensInput  = $data['usageMetadata']['promptTokenCount'] ?? 0;
            $tokensOutput = $data['usageMetadata']['candidatesTokenCount'] ?? 0;
            $cost         = $this->calculateCost($model, $tokensInput, $tokensOutput);
            $elapsed      = (int) round((microtime(true) - $startTime) * 1000);

            Log::info('Gemini request completed', [
                'model'           => $model,
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
                'model'          => $model,
                'processing_ms'  => $elapsed,
                'finish_reason'  => $candidates[0]['finishReason'] ?? null,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Gemini connection timeout', ['message' => $e->getMessage()]);
            throw new \RuntimeException('การเชื่อมต่อ Gemini หมดเวลา กรุณาลองใหม่อีกครั้ง');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getProviderName(): string
    {
        return 'gemini';
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

        // default: gemini-pro pricing
        $pricing ??= self::MODEL_PRICING['gemini-pro'];

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
            throw new \RuntimeException('ไม่พบ Gemini API key กรุณาตั้งค่า GEMINI_API_KEY ในไฟล์ .env');
        }
    }
}
