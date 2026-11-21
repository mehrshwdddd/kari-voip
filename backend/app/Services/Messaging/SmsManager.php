<?php

namespace App\Services\Messaging;

use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\SmsProvider;
use App\Services\Messaging\Adapters\GhasedakAdapter;
use App\Services\Messaging\Adapters\KavenegarAdapter;
use App\Services\Messaging\Contracts\SmsAdapterInterface;

final class SmsManager
{
    public function __construct(
        private readonly MessageTemplateEngine $templateEngine
    ) {}

    public function adapter(?string $providerName = null, array $credentials = [], ?string $sender = null): SmsAdapterInterface
    {
        return match (strtolower((string) $providerName)) {
            'ghasedak', GhasedakAdapter::class => new GhasedakAdapter($credentials, $sender ?: '30005088'),
            default => new KavenegarAdapter($credentials, $sender ?: '10008663'),
        };
    }

    /**
     * Send an SMS with automatic failover across active providers and audit logging in message_logs.
     *
     * @param  array<string, mixed>  $meta
     */
    public function sendSms(
        string $to,
        string $body,
        ?int $contactId = null,
        ?int $templateId = null,
        array $meta = []
    ): MessageLog {
        $providers = [];

        try {
            $providers = SmsProvider::where('is_active', true)
                ->orderBy('priority')
                ->get()
                ->all();
        } catch (\Throwable) {
            $providers = [];
        }

        if (count($providers) === 0) {
            $providers = [
                new SmsProvider([
                    'name' => 'کاوه‌نگار',
                    'adapter_class' => 'kavenegar',
                    'sender_number' => '10008663',
                ]),
            ];
        }

        $result = [
            'success' => false,
            'provider_ref' => '',
            'status' => 'failed',
            'response' => [],
        ];
        $usedProviderName = 'kavenegar';
        $usedSender = '10008663';

        foreach ($providers as $provider) {
            $adapter = $this->adapter(
                $provider->adapter_class,
                $provider->credentials ?? [],
                $provider->sender_number
            );

            $usedProviderName = $adapter->providerName();
            $usedSender = $provider->sender_number ?: '10008663';

            $result = $adapter->send($to, $body, $usedSender);
            if ($result['success']) {
                break;
            }
        }

        return MessageLog::create([
            'contact_id' => $contactId,
            'channel' => 'sms',
            'to_address' => $to,
            'from_address' => $usedSender,
            'template_id' => $templateId,
            'body' => $body,
            'status' => $result['status'],
            'provider' => $usedProviderName,
            'provider_ref' => $result['provider_ref'],
            'provider_response' => $result['response'],
            'sent_at' => $result['success'] ? now() : null,
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
        ]);
    }

    /**
     * Render a MessageTemplate with variables and send via SMS.
     *
     * @param  array<string, scalar|null>  $variables
     * @param  array<string, mixed>  $meta
     */
    public function sendFromTemplate(
        MessageTemplate $template,
        string $to,
        array $variables = [],
        ?int $contactId = null,
        array $meta = []
    ): MessageLog {
        $renderedBody = $this->templateEngine->render($template->body, $variables);

        return $this->sendSms($to, $renderedBody, $contactId, $template->id, $meta);
    }
}
