<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class ToggleFeedActiveJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $id,
    ) {}

    public function handle(): Feed
    {
        $feed = Feed::query()->findOrFail($this->id);

        $feed->update(['active' => ! $feed->active]);

        return $feed->fresh();
    }
}
