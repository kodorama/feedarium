<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use App\Models\News;
use App\Models\Setting;
use SimplePie\SimplePie;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Domains\News\Jobs\EnrichArticleJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\News\Jobs\WithoutOverlappingPublisherWork;

/**
 * Parses and imports feed items from raw XML content into the news table.
 *
 * This job is responsible for:
 *  - Parsing RSS/Atom XML via SimplePie
 *  - Deduplicating items by guid or link
 *  - Inserting new News records
 *  - Importing feed-provided preview images before HTML enrichment
 *  - Dispatching one combined article enrichment job when work is needed
 *
 * Queued so that importing is non-blocking and retryable.
 */
final class ImportFeedItemsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $maxExceptions = 5;

    public int $backoff = 30;

    /** @return array<int, class-string> */
    public function middleware(): array
    {
        return [WithoutOverlappingPublisherWork::class];
    }

    public function publisherWorkKey(): string
    {
        return 'import:feed:'.$this->feedId;
    }

    public function publisherWorkLockExpiresAfter(): int
    {
        return 180;
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours((int) config('feedarium.publisher_throttle.retry_window_hours', 6));
    }

    public function __construct(
        public readonly int $feedId,
        public readonly string $rawXml,
    ) {}

    public function handle(): void
    {
        $feed = Feed::query()->findOrFail($this->feedId);

        // Read scrape setting once for the whole batch to avoid N queries.
        $scrapeFullBody = Setting::get('scrape_full_body', 'false') === 'true';

        $simplepie = new SimplePie;
        $simplepie->set_raw_data($this->rawXml);
        $simplepie->set_cache_location(storage_path('framework/cache/simplepie'));
        $simplepie->enable_cache(false);
        $simplepie->init();
        $simplepie->handle_content_type();

        $items = $simplepie->get_items();

        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $guid = $item->get_id();
            $link = $item->get_permalink();

            if (! $link && ! $guid) {
                continue;
            }

            $exists = News::query()
                ->where('feed_id', $feed->id)
                ->where(function ($q) use ($guid, $link) {
                    if ($guid) {
                        $q->where('guid', $guid);
                    }
                    if ($link) {
                        $q->orWhere('link', $link);
                    }
                })
                ->exists();

            if ($exists) {
                continue;
            }

            $publishedAt = null;
            $dateRaw = $item->get_date('U');
            if ($dateRaw) {
                $publishedAt = \Illuminate\Support\Carbon::createFromTimestamp((int) $dateRaw);
            }

            $thumbnailUrl = $this->validImageUrl($item->get_thumbnail()['url'] ?? null);
            if (! $thumbnailUrl) {
                foreach ($item->get_enclosures() ?? [] as $enclosure) {
                    if (str_starts_with(strtolower((string) $enclosure->get_type()), 'image/')) {
                        $thumbnailUrl = $this->validImageUrl($enclosure->get_link());
                        if ($thumbnailUrl) {
                            break;
                        }
                    }
                }
            }

            $news = News::query()->create([
                'feed_id' => $feed->id,
                'title' => $item->get_title() ?? '(no title)',
                'link' => $link ?? $guid,
                'description' => $item->get_description(),
                'published_at' => $publishedAt,
                'author' => $item->get_author()?->get_name(),
                'guid' => $guid,
                'is_read' => false,
                'thumbnail_url' => $thumbnailUrl,
            ]);

            $needsBody = $scrapeFullBody && ! $feed->disable_full_article_scraping;
            if ($link && ($needsBody || ! $thumbnailUrl)) {
                EnrichArticleJob::dispatch($news->id);
            }
        }
    }

    private function validImageUrl(?string $url): ?string
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $url
            : null;
    }
}
