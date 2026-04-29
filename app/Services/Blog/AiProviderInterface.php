<?php

namespace App\Services\Blog;

/**
 * AiProviderInterface -- สัญญา (contract) สำหรับ AI provider ทุกตัว
 *
 * ทุก provider (OpenAI, Claude, Gemini) ต้อง implement interface นี้
 * เพื่อให้ AiContentService สลับ provider ได้อย่างโปร่งใส
 */
interface AiProviderInterface
{
    /**
     * สร้างเนื้อหาจาก prompt ที่กำหนด
     *
     * @param  string  $prompt   ข้อความ prompt สำหรับ AI
     * @param  array   $options  ตัวเลือกเพิ่มเติม เช่น temperature, max_tokens, system_prompt
     * @return array{content: string, tokens_input: int, tokens_output: int, cost: float}
     *
     * @throws \RuntimeException เมื่อเรียก API ล้มเหลว
     */
    public function generateContent(string $prompt, array $options = []): array;

    /**
     * ชื่อ provider เช่น 'openai', 'claude', 'gemini'
     */
    public function getProviderName(): string;

    /**
     * ชื่อ model ที่ใช้งานอยู่ เช่น 'gpt-4o-mini', 'claude-sonnet-4-20250514'
     */
    public function getModelName(): string;
}
