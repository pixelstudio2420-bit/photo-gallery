<?php

namespace App\Services\Blog\Providers;

use App\Services\Blog\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAiProvider -- เชื่อมต่อ OpenAI Chat Completions API
 *
 * รองรับ model: gpt-4o-mini, gpt-4o, gpt-4-turbo และอื่นๆ
 * คำนวณค่าใช้จ่ายตาม token ที่ใช้จริง
 */
class OpenAiProvider implements AiProviderInterface
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
        'gpt-4o-mini'  => ['input' => 0.15,  'output' => 0.60],
        'gpt-4o'       => ['input' => 2.50,  'output' => 10.00],
        'gpt-4-turbo'  => ['input' => 10.00, 'output' => 30.00],
        'gpt-4'        => ['input' => 30.00, 'output' => 60.00],
        'gpt-3.5-turbo'=> ['input' => 0.50,  'output' => 1.50],
    ];

    public function __construct()
    {
        $config = config('blog.ai.providers.openai');

        $this->apiKey      = $config['api_key'] ?? '';
        $this->model       = $config['model'] ?? 'gpt-4o-mini';
        $this->endpoint    = $config['endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
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

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $prompt],
        ];

        $payload = [
            'model'       => $options['model'] ?? $this->model,
            'messages'    => $messages,
            'max_tokens'  => $options['max_tokens'] ?? $this->maxTokens,
            'temperature' => $options['temperature'] ?? $this->temperature,
        ];

        // JSON mode เมื่อขอ structured output
        if (!empty($options['json_mode'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])
                ->timeout($options['timeout'] ?? 120)
                ->post($this->endpoint, $payload);

            if ($response->failed()) {
                $errorBody = $response->json();
                $errorMsg  = $errorBody['error']['message'] ?? $response->body();

                Log::error('OpenAI API error', [
                    'status'  => $response->status(),
                    'error'   => $errorMsg,
                    'model'   => $payload['model'],
                ]);

                throw new \RuntimeException("OpenAI API error ({$response->status()}): {$errorMsg}");
            }

            $data = $response->json();

            $content      = $data['choices'][0]['message']['content'] ?? '';
            $tokensInput  = $data['usage']['prompt_tokens'] ?? 0;
            $tokensOutput = $data['usage']['completion_tokens'] ?? 0;
            $cost         = $this->calculateCost($payload['model'], $tokensInput, $tokensOutput);
            $elapsed      = (int) round((microtime(true) - $startTime) * 1000);

            Log::info('OpenAI request completed', [
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
                'finish_reason'  => $data['choices'][0]['finish_reason'] ?? null,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('OpenAI connection timeout', ['message' => $e->getMessage()]);
            throw new \RuntimeException('การเชื่อมต่อ OpenAI หมดเวลา กรุณาลองใหม่อีกครั้ง');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getProviderName(): string
    {
        return 'openai';
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
        // ค้นหาราคาที่ตรงกัน หรือ prefix ที่ตรงกัน
        $pricing = self::MODEL_PRICING[$model] ?? null;

        if ($pricing === null) {
            foreach (self::MODEL_PRICING as $key => $price) {
                if (str_starts_with($model, $key)) {
                    $pricing = $price;
                    break;
                }
            }
        }

        // default: gpt-4o-mini pricing
        $pricing ??= self::MODEL_PRICING['gpt-4o-mini'];

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
            throw new \RuntimeException('ไม่พบ OpenAI API key กรุณาตั้งค่า OPENAI_API_KEY ในไฟล์ .env');
        }
    }
}
