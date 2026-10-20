<?php

use App\Models\Feed;
use App\Models\User;
use Illuminate\Support\Facades\Log;
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

        $this->app->call([new FetchFeedJob($feed->id), 'handle']);

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

        $this->app->call([new FetchFeedJob($feed->id), 'handle']);

        Queue::assertNotPushed(ImportFeedItemsJob::class);
        expect($feed->fresh()->last_fetched_at)->not->toBeNull();
    });

    it('rethrows temporary HTTP errors so the queue retries the job', function () {
        Log::spy();

        $feed = Feed::factory()->create([
            'active' => true,
            'url' => 'https://example.com/feed.xml',
        ]);

        Http::fake([
            'example.com/feed.xml' => Http::response('', 500),
        ]);

        expect(fn () => $this->app->call([new FetchFeedJob($feed->id), 'handle']))
            ->toThrow(RuntimeException::class);

        Log::shouldHaveReceived('warning')
            ->once();

        Queue::assertNotPushed(ImportFeedItemsJob::class);
    });

    it('releases the actual queue wrapper using a 429 Retry-After delay', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array']);
        \Illuminate\Support\Facades\Cache::store('array')->flush();
        $feed = Feed::factory()->create(['active' => true, 'url' => 'https://example.com/feed.xml']);
        Http::fake(['*' => Http::response('', 429, ['Retry-After' => '42'])]);
        $queueJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(42);
        $job = new FetchFeedJob($feed->id);
        $job->setJob($queueJob);

        $this->app->call([$job, 'handle']);

        Queue::assertNotPushed(ImportFeedItemsJob::class);
    });

    it('logs and rethrows connection errors so the queue can retry the job', function () {
        Log::spy();

        $feed = Feed::factory()->create([
            'active' => true,
            'url' => 'https://example.com/feed.xml',
        ]);

        Http::fake([
            '*' => static fn () => throw new ConnectionException('Simulated WAF drop / SSL EOF'),
        ]);

        expect(fn () => $this->app->call([new FetchFeedJob($feed->id), 'handle']))
            ->toThrow(ConnectionException::class);

        Log::shouldHaveReceived('warning')
            ->once();

        Queue::assertNotPushed(ImportFeedItemsJob::class);
    });

    it('does not retry permanent HTTP errors', function () {
        Log::spy();

        $feed = Feed::factory()->create([
            'active' => true,
            'url' => 'https://example.com/feed.xml',
        ]);

        Http::fake([
            'example.com/feed.xml' => Http::response('', 404),
        ]);

        $this->app->call([new FetchFeedJob($feed->id), 'handle']);

        Log::shouldHaveReceived('warning')
            ->once();

        Queue::assertNotPushed(ImportFeedItemsJob::class);
    });

    it('keeps only the feed identifier in its serialized queue payload', function () {
        $job = new FetchFeedJob(123);
        $payload = serialize($job);
        $restored = unserialize($payload);

        expect($restored)->toBeInstanceOf(FetchFeedJob::class)
            ->and($restored->feedId)->toBe(123)
            ->and($payload)->not->toContain('PublisherRequestGate');
    });
});
