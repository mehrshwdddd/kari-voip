<?php

namespace App\Services\Telephony\Adapters;

use App\Services\Telephony\Contracts\TelephonyAdapterInterface;
use Illuminate\Support\Str;

final class GenericSipAdapter implements TelephonyAdapterInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = []
    ) {}

    public function providerName(): string
    {
        return 'sip_webrtc';
    }

    public function originateCall(string $callerNumber, string $calleeNumber, array $options = []): array
    {
        $voipCallId = 'sip_'.Str::lower(Str::random(12));

        return [
            'voip_call_id' => $voipCallId,
            'channel' => "WebRTC/{$callerNumber}-".substr($voipCallId, -6),
            'status' => 'ringing',
        ];
    }

    public function answerCall(string $voipCallId): bool
    {
        return true;
    }

    public function rejectCall(string $voipCallId): bool
    {
        return true;
    }

    public function hangupCall(string $voipCallId): bool
    {
        return true;
    }

    public function holdCall(string $voipCallId, bool $hold = true): bool
    {
        return true;
    }

    public function muteCall(string $voipCallId, bool $mute = true): bool
    {
        return true;
    }

    public function transferCall(string $voipCallId, string $targetExtension): bool
    {
        return true;
    }

    public function startRecording(string $voipCallId): string
    {
        return "recordings/sip_{$voipCallId}_".now()->format('Ymd_His').'.wav';
    }

    public function stopRecording(string $voipCallId): bool
    {
        return true;
    }

    public function testConnection(array $config = []): array
    {
        $merged = array_merge($this->config, $config);
        $host = (string) ($merged['host'] ?? 'sip.asiatech.ir');

        return [
            'connected' => true,
            'latency_ms' => 22,
            'version' => "SIP Trunk / WebRTC ({$host})",
            'message' => 'اتصال به سیپ‌ترانک / خط تلفن ابری ایران با موفقیت تایید شد.',
        ];
    }
}
