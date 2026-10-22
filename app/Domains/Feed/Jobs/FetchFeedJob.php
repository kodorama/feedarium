<?php

namespace App\Domains\Feed\Jobs;

use Throwable;
use App\Models\Feed;
use RuntimeException;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\News\Jobs\PublisherRequestGate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use App\Support\OutboundHttp\SafeOutboundResponse;
use App\Support\OutboundHttp\SafeOutboundHttpClient;
use App\Domains\News\Jobs\RateLimitPublisherRequests;
use App\Support\OutboundHttp\OutboundRequestDeferred;
use App\Support\OutboundHttp\OutboundRequestRejected;
use App\Support\OutboundHttp\HasPublisherRedirectContinuation;
use App\Support\OutboundHttp\PublisherRedirectContinuationAware;
use App\Support\OutboundHttp\PublisherRedirectContinuationStore;

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
final class FetchFeedJob implements PublisherRedirectContinuationAware, ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasPublisherRedirectContinuation;

    public int $maxExceptions = 5;

    public ?int $retryDelaySeconds = null;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        if ($this->retryDelaySeconds !== null) {
            return [$this->retryDelaySeconds, $this->retryDelaySeconds, $this->retryDelaySeconds];
        }

        return [60, 300, 900];
    }

    /** @return array<int, class-string> */
    public function middleware(): array
    {
        return [
            \App\Domains\News\Jobs\WithoutOverlappingPublisherWork::class,
            RateLimitPublisherRequests::class,
        ];
    }

    public function publisherWorkKey(): string
    {
        return 'feed:'.$this->feedId;
    }

    public function publisherUrl(): ?string
    {
        return $this->continuationUrl ?? Feed::query()->whereKey($this->feedId)->value('url');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours((int) config('feedarium.publisher_throttle.retry_window_hours', 6));
    }

    public function __construct(public readonly int $feedId) {}

    public function handle(PublisherRequestGate $gate, SafeOutboundHttpClient $client, PublisherRedirectContinuationStore $continuations): void
    {
        $this->restorePublisherRedirectContinuation($continuations);
        $feed = Feed::query()->findOrFail($this->feedId);

        $headers = [];

        $requestUrl = $this->continuationUrl ?? $feed->url;
        if ($this->continuationUrl === null && $feed->etag) {
            $headers['If-None-Match'] = $feed->etag;
        }

        if ($this->continuationUrl === null && $feed->last_modified_header) {
            $headers['If-Modified-Since'] = $feed->last_modified_header;
        }

        try {
            $headers['User-Agent'] = 'Feedarium/1.0 (+https://github.com/kodorama/feedarium)';
            $response = $client->get($requestUrl, $headers, 10_485_760, function (string $url, string $method, int $redirects, array $visited) use ($gate, $continuations): void {
                $this->savePublisherRedirectContinuation($continuations, $url, $method, $redirects, $visited);
                $reservation = $gate->reserve($url);
                if (! $reservation['allowed']) {
                    throw new OutboundRequestDeferred($reservation['delay']);
                }
            }, $this->continuationRedirects, $this->continuationVisited);
        } catch (OutboundRequestDeferred $exception) {
            $this->release($exception->delaySeconds);

            return;
        } catch (OutboundRequestRejected $exception) {
            $this->clearPublisherRedirectContinuation($continuations);
            Log::warning('FetchFeedJob: outbound request blocked', ['feed_id' => $this->feedId, 'reason' => $exception->reason]);

            return;
        } catch (ConnectionException $exception) {
            Log::warning('FetchFeedJob: connection error', [
                'feed_id' => $this->feedId,
                'host' => parse_url($feed->url, PHP_URL_HOST),
                'attempt' => $this->attempts(),
                'error_class' => $exception::class,
            ]);

            throw $exception;
        }

        // 304 Not Modified — skip import, just update last_fetched_at
        if ($response->status() === 304) {
            $this->clearPublisherRedirectContinuation($continuations);
            Feed::query()->where('id', $feed->id)->update([
                'last_fetched_at' => now(),
            ]);

            return;
        }

        if (! $response->successful()) {
            $this->handleUnsuccessfulResponse($feed, $response, $gate, $continuations);

            return;
        }

        Feed::query()->where('id', $feed->id)->update([
            'etag' => $response->header('ETag') ?: $feed->etag,
            'last_modified_header' => $response->header('Last-Modified') ?: $feed->last_modified_header,
            'last_fetched_at' => now(),
        ]);

        $this->clearPublisherRedirectContinuation($continuations);
        ImportFeedItemsJob::dispatch($this->feedId, $response->body());
    }

    private function handleUnsuccessfulResponse(
        Feed $feed,
        SafeOutboundResponse $response,
        PublisherRequestGate $gate,
        PublisherRedirectContinuationStore $continuations,
    ): void {
        Log::warning('FetchFeedJob: unsuccessful response', [
            'feed_id' => $this->feedId,
            'host' => parse_url($feed->url, PHP_URL_HOST),
            'status' => $response->status(),
            'attempt' => $this->attempts(),
        ]);

        if ($this->isRetryableStatus($response->status())) {
            if (in_array($response->status(), [429, 503], true)) {
                $seconds = $gate->cooldownFor($response->header('Retry-After'), $this->attempts());
                $this->retryDelaySeconds = $seconds;
                $originalSaved = $gate->cooldown($feed->url, $seconds);
                $effectiveSaved = $gate->cooldown($response->effectiveUrl, $seconds);
                if (! $originalSaved || ! $effectiveSaved) {
                    throw new RuntimeException('Unable to persist the publisher cooldown.');
                }
            }

            if ($this->job !== null && in_array($response->status(), [429, 503], true)) {
                $this->release($this->retryDelaySeconds ?? 30);

                return;
            }

            throw new RuntimeException(
                "Temporary HTTP {$response->status()} while fetching feed {$this->feedId}."
            );
        }

        $this->clearPublisherRedirectContinuation($continuations);
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
        app(PublisherRedirectContinuationStore::class)->forget($this->publisherRedirectIdentity());
        Log::error('FetchFeedJob: exhausted all attempts', [
            'feed_id' => $this->feedId,
            'error_class' => $exception ? $exception::class : null,
        ]);
    }
}
