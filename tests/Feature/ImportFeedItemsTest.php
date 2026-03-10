<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use App\Domains\Feed\Jobs\ImportFeedItemsJob;
use App\Domains\News\Jobs\ScrapeArticleThumbnailJob;

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

    it('dispatches ScrapeArticleThumbnailJob for each new item', function () use ($rssXml) {
        $feed = Feed::factory()->create();

        (new ImportFeedItemsJob($feed->id, $rssXml))->handle();

        Queue::assertPushed(ScrapeArticleThumbnailJob::class, 1);
    });
});
