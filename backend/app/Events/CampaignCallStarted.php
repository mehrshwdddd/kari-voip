<?php

namespace App\Events;

use App\Models\Campaign;
use App\Models\CampaignContact;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CampaignCallStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly CampaignContact $campaignContact
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('branch.'.($this->campaign->branch_id ?? 'central')),
        ];
    }

    public function broadcastAs(): string
    {
        return 'campaign.call.started';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'phone_number' => $this->campaignContact->phone_number,
            'attempt_count' => $this->campaignContact->attempt_count,
        ];
    }
}
