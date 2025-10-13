<?php

namespace App\Domains\Feed\Jobs;

use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class BroadcastFeedUpdatedJob implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $feedId) {}

    public function handle(): void
    {
        Broadcast::event(new \App\Events\FeedUpdated($this->feedId));
    }

    public function broadcastOn(): array
    {
        return ['feeds'];
    }

    public function broadcastWith(): array
    {
        return ['feed_id' => $this->feedId];
    }
}
