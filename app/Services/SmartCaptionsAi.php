<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Smart Captions — generate descriptive captions for each photo.
 *
 * Two paths:
 *   A. OpenAI Vision (GPT-4o) when OPENAI_API_KEY is set
 *   B. Anthropic Claude Vision when ANTHROPIC_API_KEY is set
 *   C. Heuristic fallback — uses ai_tags + event metadata to
 *      build a stock caption like "Beach photo from Wedding Event"
 *
 * Stored on event_photos.caption.
 */
class SmartCaptionsAi
{
    public function isConfigured(): bool
    {
        return $this->hasOpenAi() || $this->hasAnthropic();
    }

    private function hasOpenAi(): bool   { return !empty(env('OPENAI_API_KEY')); }
    private function hasAnthropic(): bool { return !empty(env('ANTHROPIC_API_KEY')); }

    public function run(Event $event): array
    {
        $photos = $event->photos()
            ->where('status', 'active')
            ->whereNull('caption')
            ->get();

        $processed = 0;
        $captioned = 0;
        $errors    = 0;
        $mode      = $this->hasOpenAi() ? 'openai' : ($this->hasAnthropic() ? 'anthropic' : 'heuristic');

        foreach ($photos as $photo) {
            $processed++;
            try {
                $caption = match ($mode) {
                    'openai'    => $this->captionViaOpenAi($photo),
                    'anthropic' => $this->captionViaAnthropic($photo),
                    default     => $this->heuristicCaption($photo, $event),
                };

                if ($caption) {
                    $photo->forceFill(['caption' => $caption])->save();
                    $captioned++;
                }
            } catch (\Throwable $e) {
                Log::warning('Caption failed photo '.$photo->id, ['err' => $e->getMessage()]);
                $errors++;
            }
        }

        return [
            'processed' => $processed,
            'captioned' => $captioned,
            'errors'    => $errors,
            'mode'      => $mode,
            'configure_hint' => $mode === 'heuristic'
                ? 'ตั้งค่า OPENAI_API_KEY หรือ ANTHROPIC_API_KEY ใน .env เพื่อใช้คำบรรยายจาก AI'
                : null,
        ];
    }

    private function captionViaOpenAi(EventPhoto $photo): ?string
    {
        $disk = $photo->storage_disk ?? 'public';
        $bytes = Storage::disk($disk)->get($photo->original_path);
        if (!$bytes) return null;
        $b64 = 'data:'.$photo->mime_type.';base64,'.base64_encode($bytes);

        $resp = Http::withToken(env('OPENAI_API_KEY'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'max_tokens' => 60,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'Write a single sentence caption (max 15 words) describing this photo. Thai language.'],
                        ['type' => 'image_url', 'image_url' => ['url' => $b64]],
                    ],
                ]],
            ]);

        if (!$resp->successful()) return null;
        return trim($resp->json('choices.0.message.content') ?? '') ?: null;
    }

    private function captionViaAnthropic(EventPhoto $photo): ?string
    {
        $disk = $photo->storage_disk ?? 'public';
        $bytes = Storage::disk($disk)->get($photo->original_path);
        if (!$bytes) return null;

        $resp = Http::withHeaders([
                'x-api-key'         => env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-3-5-haiku-20241022',
                'max_tokens' => 80,
                'messages'   => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => [
                            'type'       => 'base64',
                            'media_type' => $photo->mime_type,
                            'data'       => base64_encode($bytes),
                        ]],
                        ['type' => 'text', 'text' => 'เขียนคำบรรยายภาพนี้เป็นภาษาไทย 1 ประโยค (ไม่เกิน 15 คำ) เน้นสิ่งที่อยู่ในภาพ'],
                    ],
                ]],
            ]);

        if (!$resp->successful()) return null;
        return trim($resp->json('content.0.text') ?? '') ?: null;
    }

    private function heuristicCaption(EventPhoto $photo, Event $event): string
    {
        $tags = $photo->ai_tags ?? [];
        $primary = $tags[0] ?? null;
        $eventName = $event->name ?? 'อีเวนต์';
        if ($primary) {
            return "{$primary} จาก {$eventName}";
        }
        return "ภาพจาก {$eventName}";
    }
}
