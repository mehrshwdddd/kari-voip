<?php

namespace App\Services\Messaging\Adapters;

use App\Services\Messaging\Contracts\SmsAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GhasedakAdapter implements SmsAdapterInterface
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        private readonly array $credentials = [],
        private readonly string $defaultSender = '30005088'
    ) {}

    public function providerName(): string
    {
        return 'ghasedak';
    }

    public function send(string $to, string $message, ?string $senderNumber = null, array $options = []): array
    {
        $sender = $senderNumber ?: $this->defaultSender;
        $apiKey = (string) ($this->credentials['api_key'] ?? '');

        if ($apiKey !== '' && $apiKey !== 'local_simulated') {
            try {
                $response = Http::timeout(4)
                    ->withHeaders(['apikey' => $apiKey])
                    ->asForm()
                    ->post('https://api.ghasedak.me/v2/sms/send/simple', [
                        'receptor' => $to,
                        'linenumber' => $sender,
                        'message' => $message,
                    ]);

                if ($response->successful()) {
                    $json = $response->json();

                    return [
                        'success' => true,
                        'provider_ref' => (string) ($json['items'][0] ?? ('ghs_'.Str::random(10))),
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

        $ref = 'ghs_'.Str::lower(Str::random(10));

        return [
            'success' => true,
            'provider_ref' => $ref,
            'status' => 'sent',
            'response' => [
                'provider' => 'ghasedak',
                'receptor' => $to,
                'sender' => $sender,
                'messageid' => $ref,
            ],
        ];
    }
}
