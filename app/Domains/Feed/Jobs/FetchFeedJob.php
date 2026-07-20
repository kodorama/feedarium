<?php

namespace App\Domains\Feed\Jobs;

use Throwable;
use App\Models\Feed;
use RuntimeException;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;

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
 *
 * HTTP note: some servers (e.g. makeuseof.com) run WAFs that silently drop TLS
 * connections from unrecognised User-Agents — Guzzle's default UA triggers this,
 * producing a misleading cURL error 56 ("unexpected eof while reading"). Sending
 * a consistent Feedarium User-Agent and forcing HTTP/1.1 (the server's HTTP/2
 * implementation is broken and returns PROTOCOL_ERROR) resolves both issues.
 */
final class FetchFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

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

        try {
            $response = Http::withHeaders($headers)
                ->withUserAgent(
                    'Feedarium/1.0 (+https://github.com/kodorama/feedarium)'
                )
                ->connectTimeout(15)
                ->timeout(30)
                ->withOptions([
                    'curl' => [
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    ],
                ])
                ->get($feed->url);
        } catch (ConnectionException $exception) {
            Log::warning('FetchFeedJob: connection error', [
                'feed_id' => $this->feedId,
                'url' => $feed->url,
                'attempt' => $this->attempts(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // 304 Not Modified — skip import, just update last_fetched_at
        if ($response->status() === 304) {
            Feed::query()->where('id', $feed->id)->update([
                'last_fetched_at' => now(),
            ]);

            return;
        }

        if (! $response->successful()) {
            $this->handleUnsuccessfulResponse($feed, $response);

            return;
        }

        Feed::query()->where('id', $feed->id)->update([
            'etag' => $response->header('ETag') ?: $feed->etag,
            'last_modified_header' => $response->header('Last-Modified') ?: $feed->last_modified_header,
            'last_fetched_at' => now(),
        ]);

        ImportFeedItemsJob::dispatch($this->feedId, $response->body());
    }

    private function handleUnsuccessfulResponse(
        Feed $feed,
        Response $response,
    ): void {
        Log::warning('FetchFeedJob: unsuccessful response', [
            'feed_id' => $this->feedId,
            'url' => $feed->url,
            'status' => $response->status(),
            'attempt' => $this->attempts(),
        ]);

        if ($this->isRetryableStatus($response->status())) {
            throw new RuntimeException(
                "Temporary HTTP {$response->status()} while fetching feed {$this->feedId}."
            );
        }
    }

    private function isRetryableStatus(int $status): bool
    {
        return in_array($status, [
            408,
            429,
            500,
            502,
            503,
            504,
        ], true);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('FetchFeedJob: exhausted all attempts', [
            'feed_id' => $this->feedId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
