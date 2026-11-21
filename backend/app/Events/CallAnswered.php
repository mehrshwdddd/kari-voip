<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CallAnswered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Call $call
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('branch.'.($this->call->branch_id ?? 'central')),
        ];

        if ($this->call->user_id) {
            $channels[] = new PrivateChannel('user.'.$this->call->user_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'call.answered';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call' => $this->call->loadMissing(['contact.tagPivots', 'branch:id,name', 'user:id,name']),
        ];
    }
}
