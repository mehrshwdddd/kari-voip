<?php

namespace App\Services\Telephony\Contracts;

interface TelephonyAdapterInterface
{
    public function providerName(): string;

    /**
     * Originate an outbound call (Click-to-Call or Campaign).
     *
     * @param  array<string, mixed>  $options
     * @return array{voip_call_id: string, channel: string, status: string}
     */
    public function originateCall(string $callerNumber, string $calleeNumber, array $options = []): array;

    public function answerCall(string $voipCallId): bool;

    public function rejectCall(string $voipCallId): bool;

    public function hangupCall(string $voipCallId): bool;

    public function holdCall(string $voipCallId, bool $hold = true): bool;

    public function muteCall(string $voipCallId, bool $mute = true): bool;

    public function transferCall(string $voipCallId, string $targetExtension): bool;

    public function startRecording(string $voipCallId): string;

    public function stopRecording(string $voipCallId): bool;

    /**
     * Test connectivity to the PBX / SIP provider.
     *
     * @param  array<string, mixed>  $config
     * @return array{connected: bool, latency_ms: int, version: string, message: string}
     */
    public function testConnection(array $config = []): array;
}
