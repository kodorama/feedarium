<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class FeedUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public int $feedId) {}

    public function broadcastOn(): Channel
    {
        return new Channel('feeds');
    }

    public function broadcastWith(): array
    {
        return ['feed_id' => $this->feedId];
    }
}
