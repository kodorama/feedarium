<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Fetches the raw RSS/Atom feed XML for a given Feed record.
 *
 * This job is responsible for:
 *  - Performing the HTTP request to the feed URL
 *  - Respecting ETag / Last-Modified caching headers
 *  - Updating feed metadata (etag, last_modified_header, last_fetched_at)
 *  - Dispatching ImportFeedItemsJob with the raw feed content
 *
 * Queued so that fetching is non-blocking and retryable.
 */
final class FetchFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $feedId) {}

    public function handle(): void
    {
        $feed = Feed::query()->findOrFail($this->feedId);

        $headers = [];

        if ($feed->etag) {
            $headers['If-None-Match'] = $feed->etag;
        }

        if ($feed->last_modified_header) {
            $headers['If-Modified-Since'] = $feed->last_modified_header;
        }

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->get($feed->url);

        // 304 Not Modified — skip import, just update last_fetched_at
        if ($response->status() === 304) {
            Feed::query()->where('id', $feed->id)->update([
                'last_fetched_at' => now(),
            ]);

            return;
        }

        if (! $response->successful()) {
            return;
        }

        Feed::query()->where('id', $feed->id)->update([
            'etag' => $response->header('ETag') ?: $feed->etag,
            'last_modified_header' => $response->header('Last-Modified') ?: $feed->last_modified_header,
            'last_fetched_at' => now(),
        ]);

        ImportFeedItemsJob::dispatch($this->feedId, $response->body());
    }
}
