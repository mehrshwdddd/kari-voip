<?php

namespace App\Services\Messaging\Contracts;

interface SmsAdapterInterface
{
    public function providerName(): string;

    /**
     * Send an SMS message to a recipient phone number.
     *
     * @param  array<string, mixed>  $options
     * @return array{success: bool, provider_ref: string, status: string, response: array<string, mixed>}
     */
    public function send(string $to, string $message, ?string $senderNumber = null, array $options = []): array;
}
