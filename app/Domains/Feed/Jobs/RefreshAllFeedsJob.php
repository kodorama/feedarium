<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Fans out one FetchFeedJob per active feed.
 * Scheduled to run every 15 minutes via the console scheduler.
 */
final class RefreshAllFeedsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Feed::query()
            ->where('active', true)
            ->each(fn (Feed $feed) => FetchFeedJob::dispatch($feed->id));
    }
}
