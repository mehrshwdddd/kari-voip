<?php

namespace App\Services\Messaging\Adapters;

use App\Services\Messaging\Contracts\SmsAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class KavenegarAdapter implements SmsAdapterInterface
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        private readonly array $credentials = [],
        private readonly string $defaultSender = '10008663'
    ) {}

    public function providerName(): string
    {
        return 'kavenegar';
    }

    public function send(string $to, string $message, ?string $senderNumber = null, array $options = []): array
    {
        $sender = $senderNumber ?: $this->defaultSender;
        $apiKey = (string) ($this->credentials['api_key'] ?? '');

        if ($apiKey !== '' && $apiKey !== 'local_simulated') {
            try {
                $response = Http::timeout(4)->asForm()->post(
                    "https://api.kavenegar.com/v1/{$apiKey}/sms/send.json",
                    [
                        'receptor' => $to,
                        'sender' => $sender,
                        'message' => $message,
                    ]
                );

                if ($response->successful()) {
                    $json = $response->json();
                    $entry = $json['entries'][0] ?? [];

                    return [
                        'success' => true,
                        'provider_ref' => (string) ($entry['messageid'] ?? ('kvn_'.Str::random(10))),
                        'status' => 'sent',
                        'response' => is_array($json) ? $json : [],
                    ];
                }

                return [
                    'success' => false,
                    'provider_ref' => '',
                    'status' => 'failed',
                    'response' => ['http_status' => $response->status()],
                ];
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'provider_ref' => '',
                    'status' => 'failed',
                    'response' => ['error' => $e->getMessage()],
                ];
            }
        }

        // Local / simulated fallback
        $ref = 'kvn_'.Str::lower(Str::random(10));

        return [
            'success' => true,
            'provider_ref' => $ref,
            'status' => 'sent',
            'response' => [
                'provider' => 'kavenegar',
                'receptor' => $to,
                'sender' => $sender,
                'messageid' => $ref,
            ],
        ];
    }
}
