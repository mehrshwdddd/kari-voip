<?php

namespace App\Services\AI\Contracts;

interface AIAdapterInterface
{
    public function providerName(): string;

    /**
     * Persian Speech-to-Text (STT).
     *
     * @return array{text: string, confidence: float, language: string}
     */
    public function transcribeSpeech(string $audioInput, string $language = 'fa'): array;

    /**
     * Persian Text-to-Speech (TTS).
     *
     * @return array{audio_path: string, duration_ms: int, voice: string}
     */
    public function synthesizeSpeech(string $text, string $voice = 'fa-IR-DilaraNeural'): array;

    /**
     * Detect caller intent and extract entities from Persian text.
     *
     * @param  array<string, mixed>  $context
     * @return array{intent: string, confidence: float, entities: array<string, mixed>, reply: string}
     */
    public function detectIntentAndEntities(string $utterance, array $context = []): array;

    /**
     * Summarize a call transcript in Persian.
     *
     * @return array{summary: string, sentiment: string, action_items: array<int, string>}
     */
    public function summarizeCall(string $transcript): array;
}
