<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatcherService
{
    /**
     * Dispatch an event to all subscribed webhooks.
     */
    public function dispatch(string $eventType, array $payload): array
    {
        $webhooks = Webhook::query()
            ->where('is_active', true)
            ->get();

        $deliveries = [];

        foreach ($webhooks as $webhook) {
            if ($webhook->listensTo($eventType)) {
                $deliveries[] = $this->sendDirectly($webhook, $eventType, $payload);
            }
        }

        return $deliveries;
    }

    /**
     * Send an HTTP request directly to the webhook and record delivery.
     */
    public function sendDirectly(Webhook $webhook, string $eventType, array $payload, int $attempt = 1): WebhookDelivery
    {
        $timestamp = time();
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $timestamp.'.'.$jsonPayload, $webhook->secret);

        $startTime = microtime(true);
        $statusCode = null;
        $responseBody = null;
        $deliveredAt = null;
        $failedAt = null;

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'VoiceReach-OS-Webhook/1.0',
                    'X-VoiceReach-Event' => $eventType,
                    'X-VoiceReach-Timestamp' => (string) $timestamp,
                    'X-VoiceReach-Signature' => $signature,
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($webhook->url);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();
            $responseBody = mb_substr($response->body(), 0, 2000); // cap length

            if ($response->successful()) {
                $deliveredAt = now();
            } else {
                $failedAt = now();
            }
        } catch (Exception $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $responseBody = mb_substr($e->getMessage(), 0, 2000);
            $failedAt = now();
            Log::warning("Webhook dispatch failed for webhook ID {$webhook->id}: ".$e->getMessage());
        }

        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'response_status' => $statusCode,
            'response_body' => $responseBody,
            'duration_ms' => $durationMs,
            'attempt_number' => $attempt,
            'delivered_at' => $deliveredAt,
            'failed_at' => $failedAt,
        ]);

        $webhook->update([
            'last_triggered_at' => now(),
            'last_status_code' => $statusCode,
        ]);

        return $delivery;
    }

    /**
     * Send a ping/test event to verify webhook connectivity.
     */
    public function test(Webhook $webhook): WebhookDelivery
    {
        $testPayload = [
            'event' => 'ping',
            'timestamp' => now()->toIso8601String(),
            'message' => 'این یک پیام آزمایشی از سیستم صوتی و کال‌سنتر VoiceReach OS است.',
            'webhook' => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'events' => $webhook->events,
            ],
        ];

        return $this->sendDirectly($webhook, 'ping', $testPayload);
    }
}
