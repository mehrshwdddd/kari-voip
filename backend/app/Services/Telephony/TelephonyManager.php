<?php

namespace App\Services\Telephony;

use App\Models\Integration;
use App\Services\Telephony\Adapters\AsteriskAdapter;
use App\Services\Telephony\Adapters\GenericSipAdapter;
use App\Services\Telephony\Adapters\IssabelAdapter;
use App\Services\Telephony\Contracts\TelephonyAdapterInterface;

final class TelephonyManager
{
    public function adapter(?string $provider = null): TelephonyAdapterInterface
    {
        $integration = null;

        try {
            $query = Integration::where('type', 'voip')->where('status', 'active');
            if ($provider) {
                $query->where('name', $provider);
            }
            $integration = $query->latest()->first();
        } catch (\Throwable) {
            // Table may not exist during early boot
        }

        $config = array_merge(
            $integration?->config ?? [],
            $integration?->credentials ?? []
        );

        $adapterName = $provider ?? ($integration?->adapter_class ?? 'asterisk');

        return match (strtolower($adapterName)) {
            'issabel', IssabelAdapter::class => new IssabelAdapter($config),
            'sip_webrtc', 'generic_sip', GenericSipAdapter::class => new GenericSipAdapter($config),
            default => new AsteriskAdapter($config),
        };
    }
}
