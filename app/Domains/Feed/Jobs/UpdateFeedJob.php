<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateFeedJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(UpdateFeedRequest $request, int $id): Feed
    {
        $feed = Feed::findOrFail($id);
        $feed->update($request->validated());
        BroadcastFeedUpdatedJob::dispatch($feed->id);

        return $feed;
    }
}
