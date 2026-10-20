<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use App\Domains\News\Jobs\EnrichArticleJob;

describe('ScrapeArticleBodyJob', function () {
    beforeEach(function () {
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('stores full_body when scrape_full_body setting is enabled', function () {
        Setting::set('scrape_full_body', 'true');

        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://example.com/article']);

        Http::fake([
            'example.com/article' => Http::response(
                '<html><body><article><p>This is the article content.</p></article></body></html>',
                200
            ),
        ]);

        $this->app->call([new EnrichArticleJob($news->id), 'handle']);

        expect($news->fresh()->full_body)->toContain('This is the article content.');
    });

    it('does not scrape when scrape_full_body setting is disabled', function () {
        Setting::set('scrape_full_body', 'false');

        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://example.com/article']);

        Http::fake();

        $this->app->call([new EnrichArticleJob($news->id), 'handle']);

        Http::assertSentCount(1); // Thumbnail enrichment remains active when body scraping is disabled.
        expect($news->fresh()->full_body)->toBeNull();
    });
});
