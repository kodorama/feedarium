<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeleteFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $feedId) {}

    public function handle(): void
    {
        $feed = Feed::findOrFail($this->feedId);
        $feed->delete();
    }
}
