<?php

namespace App\Domains\Feed\Jobs;

use Throwable;
use App\Models\Feed;
use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\News\Jobs\PublisherRequestGate;
use Illuminate\Http\Client\ConnectionException;
use App\Domains\News\Jobs\RateLimitPublisherRequests;
use App\Domains\News\Jobs\WithoutOverlappingPublisherWork;

/**
 * Sends a WebSub (PubSubHubbub) subscription request to the hub URL.
 * Updates the feed's websub_secret and websub_subscribed_at on success.
 */
final class SubscribeToHubJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        return Feed::query()->whereKey($this->feedId)->value('hub_url');
    }

    public function __construct(public readonly int $feedId) {}

    public function handle(PublisherRequestGate $gate): void
    {
        $feed = Feed::query()->findOrFail($this->feedId);

        if (! $feed->hub_url) {
            return;
        }

        $secret = Str::random(40);
        $callbackUrl = url("/api/websub/callback/{$feed->id}");

        try {
            $response = Http::asForm()
                ->connectTimeout(15)
                ->timeout(30)
                ->withUserAgent('Feedarium/1.0 (+https://github.com/kodorama/feedarium)')
                ->post($feed->hub_url, [
                    'hub.callback' => $callbackUrl,
                    'hub.mode' => 'subscribe',
                    'hub.topic' => $feed->url,
                    'hub.secret' => $secret,
                    'hub.lease_seconds' => 86400,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('WebSub subscription connection failure', [
                'feed_id' => $this->feedId,
                'host' => parse_url($feed->hub_url, PHP_URL_HOST),
                'error_class' => $exception::class,
            ]);

            throw $exception;
        }

        $gate->recordRedirect($feed->hub_url, (string) $response->effectiveUri());

        if ($response->successful() || $response->status() === 202) {
            Feed::query()->where('id', $this->feedId)->update([
                'websub_secret' => $secret,
                'websub_subscribed_at' => now(),
            ]);

            return;
        }

        $status = $response->status();
        if ($status === 429 || in_array($status, [408, 500, 502, 503, 504], true)) {
            $seconds = $gate->cooldownFor($response->header('Retry-After'), $this->attempts());
            $this->retryDelaySeconds = $seconds;
            $originalSaved = $gate->cooldown($feed->hub_url, $seconds);
            $effectiveSaved = $gate->cooldown((string) $response->effectiveUri(), $seconds);
            if (! $originalSaved || ! $effectiveSaved) {
                throw new RuntimeException('Unable to persist the publisher cooldown.');
            }

            if ($this->job !== null && in_array($status, [429, 503], true)) {
                $this->release($seconds);

                return;
            }

            throw new RuntimeException("Temporary HTTP {$status} from WebSub hub for feed {$this->feedId}.");
        }

        Log::warning('WebSub subscription rejected', [
            'feed_id' => $this->feedId,
            'host' => parse_url($feed->hub_url, PHP_URL_HOST),
            'status' => $status,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('WebSub subscription exhausted retries', [
            'feed_id' => $this->feedId,
            'error_class' => $exception ? $exception::class : null,
        ]);
    }
}
