<?php

use App\Models\Feed;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use App\Domains\Feed\Jobs\FetchFeedJob;
use App\Domains\Feed\Jobs\ImportFeedItemsJob;
use Illuminate\Http\Client\ConnectionException;

describe('FetchFeedJob', function () {
    beforeEach(function () {
        Queue::fake();
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('dispatches ImportFeedItemsJob on successful fetch', function () {
        $feed = Feed::factory()->create(['active' => true, 'url' => 'https://example.com/feed.xml']);

        Http::fake([
            'example.com/feed.xml' => Http::response('<rss></rss>', 200),
        ]);

        (new FetchFeedJob($feed->id))->handle();

        Queue::assertPushed(ImportFeedItemsJob::class, fn ($job) => $job->feedId === $feed->id);
    });

    it('skips import on 304 Not Modified and updates last_fetched_at', function () {
        $feed = Feed::factory()->create([
            'active' => true,
            'url' => 'https://example.com/feed.xml',
            'etag' => '"abc123"',
        ]);

        Http::fake([
            'example.com/feed.xml' => Http::response('', 304),
        ]);

        (new FetchFeedJob($feed->id))->handle();

        Queue::assertNotPushed(ImportFeedItemsJob::class);
        expect($feed->fresh()->last_fetched_at)->not->toBeNull();
    });

    it('skips import on non-successful response', function () {
        $feed = Feed::factory()->create(['active' => true, 'url' => 'https://example.com/feed.xml']);

        Http::fake([
            'example.com/feed.xml' => Http::response('', 500),
        ]);

        (new FetchFeedJob($feed->id))->handle();

        Queue::assertNotPushed(ImportFeedItemsJob::class);
    });

    it('logs a warning and skips cleanly on connection error', function () {
        $feed = Feed::factory()->create(['active' => true, 'url' => 'https://example.com/feed.xml']);

        Http::fake([
            '*' => static fn () => throw new ConnectionException('Simulated WAF drop / SSL EOF'),
        ]);

        expect(fn () => (new FetchFeedJob($feed->id))->handle())->not->toThrow(Exception::class);

        Queue::assertNotPushed(ImportFeedItemsJob::class);
    });
});
