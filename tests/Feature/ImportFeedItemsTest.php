<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Domains\News\Jobs\EnrichArticleJob;
use App\Domains\Feed\Jobs\ImportFeedItemsJob;

$rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test Feed</title>
    <link>https://example.com</link>
    <item>
      <title>Test Article</title>
      <link>https://example.com/article-1</link>
      <guid>https://example.com/article-1</guid>
      <description>A test description</description>
      <pubDate>Mon, 09 Mar 2026 10:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

describe('ImportFeedItemsJob', function () use ($rssXml) {
    beforeEach(function () {
        Queue::fake();
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('creates a new News record for a new item', function () use ($rssXml) {
        $feed = Feed::factory()->create();

        (new ImportFeedItemsJob($feed->id, $rssXml))->handle();

        expect(News::query()->where('feed_id', $feed->id)->count())->toBe(1);
        $this->assertDatabaseHas('news', [
            'feed_id' => $feed->id,
            'title' => 'Test Article',
            'link' => 'https://example.com/article-1',
        ]);
    });

    it('does not create a duplicate for an already-imported item', function () use ($rssXml) {
        $feed = Feed::factory()->create();
        News::factory()->create([
            'feed_id' => $feed->id,
            'guid' => 'https://example.com/article-1',
            'link' => 'https://example.com/article-1',
        ]);

        (new ImportFeedItemsJob($feed->id, $rssXml))->handle();

        expect(News::query()->where('feed_id', $feed->id)->count())->toBe(1);
    });

    it('dispatches one unified enrichment job for an article without an image', function () use ($rssXml) {
        $feed = Feed::factory()->create();

        (new ImportFeedItemsJob($feed->id, $rssXml))->handle();

        Queue::assertPushed(EnrichArticleJob::class, 1);
    });

    it('still enriches a missing thumbnail when body scraping is disabled for the feed', function () use ($rssXml) {
        Setting::set('scrape_full_body', 'true');
        $feed = Feed::factory()->create(['disable_full_article_scraping' => true]);

        (new ImportFeedItemsJob($feed->id, $rssXml))->handle();

        Queue::assertPushed(EnrichArticleJob::class, 1);
    });

    it('dispatches one enrichment job when body scraping enabled and feed override is off', function () use ($rssXml) {
        Setting::set('scrape_full_body', 'true');
        $feed = Feed::factory()->create(['disable_full_article_scraping' => false]);

        (new ImportFeedItemsJob($feed->id, $rssXml))->handle();

        Queue::assertPushed(EnrichArticleJob::class, 1);
    });

    it('keeps an RSS media thumbnail and avoids image-only enrichment', function () {
        $feed = Feed::factory()->create();
        $xml = '<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/"><channel><title>x</title><item><title>A</title><link>https://example.com/a</link><guid>a</guid><media:thumbnail url="https://example.com/rss.jpg" /></item></channel></rss>';

        (new ImportFeedItemsJob($feed->id, $xml))->handle();

        expect(News::query()->where('feed_id', $feed->id)->first()->thumbnail_url)->toBe('https://example.com/rss.jpg');
        Queue::assertNotPushed(EnrichArticleJob::class);
    });

    it('rejects non-http image links supplied by RSS metadata', function () {
        $feed = Feed::factory()->create();
        $xml = '<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/"><channel><title>x</title><item><title>A</title><link>https://example.com/a</link><guid>a</guid><media:thumbnail url="javascript:alert(1)" /></item></channel></rss>';

        (new ImportFeedItemsJob($feed->id, $xml))->handle();

        expect(News::query()->where('feed_id', $feed->id)->first()->thumbnail_url)->toBeNull();
        Queue::assertPushed(EnrichArticleJob::class, 1);
    });

    it('releases duplicate feed imports while the same feed import lock is held', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array']);
        $feed = Feed::factory()->create();
        $lock = Cache::store('array')->lock('feedarium:work:import:feed:'.$feed->id, 180);
        expect($lock->get())->toBeTrue();
        $deferredJob = new class($feed->id)
        {
            public int $releasedFor = 0;

            public function __construct(public int $feedId) {}

            public function publisherWorkKey(): string
            {
                return 'import:feed:'.$this->feedId;
            }

            public function publisherWorkLockExpiresAfter(): int
            {
                return 180;
            }

            public function release(int $delay): void
            {
                $this->releasedFor = $delay;
            }
        };
        $ran = false;

        app(\App\Domains\News\Jobs\WithoutOverlappingPublisherWork::class)->handle($deferredJob, function () use (&$ran): void {
            $ran = true;
        });
        $lock->release();

        expect($deferredJob->releasedFor)->toBe(5)->and($ran)->toBeFalse();
    });
});
