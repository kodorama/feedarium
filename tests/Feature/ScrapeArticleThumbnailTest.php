<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use App\Domains\News\Jobs\ScrapeArticleThumbnailJob;

describe('ScrapeArticleThumbnailJob', function () {
    beforeEach(function () {
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('saves og:image url as thumbnail_url', function () {
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://example.com/article']);

        Http::fake([
            'example.com/article' => Http::response(
                '<html><head><meta property="og:image" content="https://example.com/img.jpg" /></head></html>',
                200
            ),
        ]);

        (new ScrapeArticleThumbnailJob($news->id))->handle();

        expect($news->fresh()->thumbnail_url)->toBe('https://example.com/img.jpg');
    });

    it('leaves thumbnail_url null when no og:image is found', function () {
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://example.com/no-image']);

        Http::fake([
            'example.com/no-image' => Http::response('<html><head><title>No image</title></head></html>', 200),
        ]);

        (new ScrapeArticleThumbnailJob($news->id))->handle();

        expect($news->fresh()->thumbnail_url)->toBeNull();
    });

    it('handles network errors gracefully', function () {
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://example.com/fail']);

        Http::fake([
            'example.com/fail' => Http::response('', 500),
        ]);

        (new ScrapeArticleThumbnailJob($news->id))->handle();

        expect($news->fresh()->thumbnail_url)->toBeNull();
    });
});
