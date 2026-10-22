<?php

namespace App\Domains\Feed\Jobs;

use Throwable;
use App\Models\Feed;
use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\News\Jobs\PublisherRequestGate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use App\Support\OutboundHttp\SafeOutboundHttpClient;
use App\Domains\News\Jobs\RateLimitPublisherRequests;
use App\Support\OutboundHttp\OutboundRequestDeferred;
use App\Support\OutboundHttp\OutboundRequestRejected;
use App\Domains\News\Jobs\WithoutOverlappingPublisherWork;
use App\Support\OutboundHttp\HasPublisherRedirectContinuation;
use App\Support\OutboundHttp\PublisherRedirectContinuationAware;
use App\Support\OutboundHttp\PublisherRedirectContinuationStore;

/**
 * Sends a WebSub (PubSubHubbub) subscription request to the hub URL.
 * Updates the feed's websub_secret and websub_subscribed_at on success.
 */
final class SubscribeToHubJob implements PublisherRedirectContinuationAware, ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasPublisherRedirectContinuation;

    public int $maxExceptions = 5;

    public ?int $retryDelaySeconds = null;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return $this->retryDelaySeconds === null
            ? [60, 300, 900]
            : [$this->retryDelaySeconds, $this->retryDelaySeconds, $this->retryDelaySeconds];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours((int) config('feedarium.publisher_throttle.retry_window_hours', 6));
    }

    /** @return array<int, class-string> */
    public function middleware(): array
    {
        return [
            WithoutOverlappingPublisherWork::class,
            RateLimitPublisherRequests::class,
        ];
    }

    public function publisherWorkKey(): string
    {
        return 'websub:'.$this->feedId;
    }

    public function publisherUrl(): ?string
    {
        return $this->continuationUrl ?? Feed::query()->whereKey($this->feedId)->value('hub_url');
    }

    public function __construct(public readonly int $feedId) {}

    public function handle(PublisherRequestGate $gate, SafeOutboundHttpClient $client, PublisherRedirectContinuationStore $continuations): void
    {
        $this->restorePublisherRedirectContinuation($continuations);
        $feed = Feed::query()->findOrFail($this->feedId);

        if (! $feed->hub_url) {
            $this->clearPublisherRedirectContinuation($continuations);

            return;
        }

        $secret = $this->continuationSecret ?? Str::random(40);
        $callbackUrl = url("/api/websub/callback/{$feed->id}");
        $requestUrl = $this->continuationUrl ?? $feed->hub_url;
        $headers = ['User-Agent' => 'Feedarium/1.0 (+https://github.com/kodorama/feedarium)'];

        try {
            $beforeRedirect = function (string $url, string $method, int $redirects, array $visited) use ($gate, $secret, $continuations): void {
                $this->savePublisherRedirectContinuation($continuations, $url, $method, $redirects, $visited, $secret);
                $reservation = $gate->reserve($url);
                if (! $reservation['allowed']) {
                    throw new OutboundRequestDeferred($reservation['delay']);
                }
            };
            $response = $this->continuationMethod === 'GET'
                ? $client->get($requestUrl, $headers, 1_048_576, $beforeRedirect, $this->continuationRedirects, $this->continuationVisited)
                : $client->postForm($requestUrl, [
                    'hub.callback' => $callbackUrl,
                    'hub.mode' => 'subscribe',
                    'hub.topic' => $feed->url,
                    'hub.secret' => $secret,
                    'hub.lease_seconds' => 86400,
                ], 1_048_576, $beforeRedirect, $this->continuationRedirects, $this->continuationVisited);
        } catch (OutboundRequestDeferred $exception) {
            $this->release($exception->delaySeconds);

            return;
        } catch (OutboundRequestRejected $exception) {
            $this->clearPublisherRedirectContinuation($continuations);
            Log::warning('WebSub subscription outbound request blocked', ['feed_id' => $this->feedId, 'reason' => $exception->reason]);

            return;
        } catch (ConnectionException $exception) {
            Log::warning('WebSub subscription connection failure', [
                'feed_id' => $this->feedId,
                'host' => parse_url($feed->hub_url, PHP_URL_HOST),
                'error_class' => $exception::class,
            ]);

            throw $exception;
        }

        if ($response->successful() || $response->status() === 202) {
            Feed::query()->where('id', $this->feedId)->update([
                'websub_secret' => $secret,
                'websub_subscribed_at' => now(),
            ]);
            $this->clearPublisherRedirectContinuation($continuations);

            return;
        }

        $status = $response->status();
        if ($status === 429 || in_array($status, [408, 500, 502, 503, 504], true)) {
            $seconds = $gate->cooldownFor($response->header('Retry-After'), $this->attempts());
            $this->retryDelaySeconds = $seconds;
            $originalSaved = $gate->cooldown($feed->hub_url, $seconds);
            $effectiveSaved = $gate->cooldown($response->effectiveUrl, $seconds);
            if (! $originalSaved || ! $effectiveSaved) {
                throw new RuntimeException('Unable to persist the publisher cooldown.');
            }

            if ($this->job !== null && in_array($status, [429, 503], true)) {
                $this->release($seconds);

                return;
            }

            throw new RuntimeException("Temporary HTTP {$status} from WebSub hub for feed {$this->feedId}.");
        }

        $this->clearPublisherRedirectContinuation($continuations);

        Log::warning('WebSub subscription rejected', [
            'feed_id' => $this->feedId,
            'host' => parse_url($feed->hub_url, PHP_URL_HOST),
            'status' => $status,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        app(PublisherRedirectContinuationStore::class)->forget($this->publisherRedirectIdentity());
        Log::error('WebSub subscription exhausted retries', [
            'feed_id' => $this->feedId,
            'error_class' => $exception ? $exception::class : null,
        ]);
    }
}
