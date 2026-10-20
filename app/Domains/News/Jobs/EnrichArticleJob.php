<?php

namespace App\Domains\News\Jobs;

use Throwable;
use App\Models\Feed;
use App\Models\News;
use RuntimeException;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use App\Domains\News\Support\ArticleHtmlExtractor;

final class EnrichArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $maxExceptions = 5;

    public ?int $retryDelaySeconds = null;

    public function __construct(public readonly int $newsId) {}

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
        return 'article:'.$this->newsId;
    }

    public function publisherUrl(): ?string
    {
        return News::query()->whereKey($this->newsId)->value('link');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours((int) config('feedarium.publisher_throttle.retry_window_hours', 6));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        if ($this->retryDelaySeconds !== null) {
            return [$this->retryDelaySeconds, $this->retryDelaySeconds, $this->retryDelaySeconds];
        }

        return [30, 60, 120, 300, 600];
    }

    public function handle(ArticleHtmlExtractor $extractor, PublisherRequestGate $gate): void
    {
        $news = News::query()->findOrFail($this->newsId);
        $feed = Feed::query()->find($news->feed_id);
        $scrapeBody = Setting::get('scrape_full_body', 'false') === 'true'
            && ! $feed?->disable_full_article_scraping
            && blank($news->full_body);
        $scrapeImage = blank($news->thumbnail_url);

        if (! $scrapeBody && ! $scrapeImage) {
            return;
        }

        try {
            $response = Http::connectTimeout(15)
                ->timeout(30)
                ->withUserAgent('Feedarium/1.0 (+https://github.com/kodorama/feedarium)')
                ->get($news->link);
        } catch (ConnectionException $exception) {
            Log::warning('Article enrichment connection failure', [
                'news_id' => $this->newsId,
                'attempt' => $this->attempts(),
                'error_class' => $exception::class,
            ]);

            throw $exception;
        }

        $gate->recordRedirect($news->link, (string) $response->effectiveUri());

        if (! $response->successful()) {
            $this->handleUnsuccessfulResponse($news, $response, $gate);

            return;
        }

        $result = $extractor->extract($response->body());
        $updates = [];

        if ($scrapeBody && filled($result['body'])) {
            $updates['full_body'] = $result['body'];
        }
        if ($scrapeImage && filled($result['image'])) {
            $updates['thumbnail_url'] = $result['image'];
        }

        if ($updates !== []) {
            News::query()->whereKey($this->newsId)->update($updates);
        }

        if (($scrapeBody && blank($result['body'])) || ($scrapeImage && blank($result['image']))) {
            Log::info('Article enrichment completed partially', [
                'news_id' => $this->newsId,
                'body_found' => filled($result['body']),
                'image_found' => filled($result['image']),
            ]);
        }
    }

    private function handleUnsuccessfulResponse(News $news, Response $response, PublisherRequestGate $gate): void
    {
        $status = $response->status();
        Log::warning('Article enrichment HTTP failure', [
            'news_id' => $this->newsId,
            'status' => $status,
            'attempt' => $this->attempts(),
        ]);

        if ($status === 429 || in_array($status, [408, 500, 502, 503, 504], true)) {
            $seconds = $gate->cooldownFor($response->header('Retry-After'), $this->attempts());
            $this->retryDelaySeconds = $seconds;
            $originalSaved = $gate->cooldown($news->link, $seconds);
            $effectiveSaved = $gate->cooldown($response->effectiveUri()?->__toString(), $seconds);
            if (! $originalSaved || ! $effectiveSaved) {
                throw new RuntimeException('Unable to persist the publisher cooldown.');
            }

            if ($this->job !== null && in_array($status, [429, 503], true)) {
                $this->release($seconds);

                return;
            }

            throw new RuntimeException("Temporary HTTP {$status} while enriching article {$this->newsId}.");
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Article enrichment exhausted retries', [
            'news_id' => $this->newsId,
            'error_class' => $exception ? $exception::class : null,
        ]);
    }
}
