<?php

namespace App\Events;

use App\Models\Campaign;
use App\Models\CampaignCallLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CampaignCallCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly CampaignCallLog $callLog
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
        return 'campaign.call.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'campaign' => [
                'id' => $this->campaign->id,
                'status' => $this->campaign->status->value,
                'total_contacts' => $this->campaign->total_contacts,
                'completed_contacts' => $this->campaign->completed_contacts,
                'success_count' => $this->campaign->success_count,
                'failed_count' => $this->campaign->failed_count,
                'progress_percentage' => $this->campaign->progress_percentage,
            ],
            'log' => $this->callLog,
        ];
    }
}
