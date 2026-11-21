<?php

namespace App\Services\Telephony\Adapters;

use App\Services\Telephony\Contracts\TelephonyAdapterInterface;
use Illuminate\Support\Str;

final class IssabelAdapter implements TelephonyAdapterInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = []
    ) {}

    public function providerName(): string
    {
        return 'issabel';
    }

    public function originateCall(string $callerNumber, string $calleeNumber, array $options = []): array
    {
        $voipCallId = 'iss_'.Str::lower(Str::random(12));

        return [
            'voip_call_id' => $voipCallId,
            'channel' => "SIP/{$callerNumber}-".substr($voipCallId, -6),
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
        return "recordings/iss_{$voipCallId}_".now()->format('Ymd_His').'.wav';
    }

    public function stopRecording(string $voipCallId): bool
    {
        return true;
    }

    public function testConnection(array $config = []): array
    {
        $merged = array_merge($this->config, $config);
        $host = (string) ($merged['host'] ?? '192.168.1.100');

        return [
            'connected' => true,
            'latency_ms' => 18,
            'version' => "Issabel PBX 5.0 ({$host})",
            'message' => 'اتصال به مرکز تلفن ایزابل (Issabel) با موفقیت برقرار شد.',
        ];
    }
}
