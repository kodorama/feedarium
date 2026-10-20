<?php

use App\Models\Feed;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Domains\Feed\Jobs\SubscribeToHubJob;
use App\Domains\News\Jobs\PublisherRequestGate;

describe('WebSub subscription', function () {
    beforeEach(function () {
        Queue::fake();
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('dispatches SubscribeToHubJob when creating a feed with hub_url', function () {
        $response = $this->postJson('/api/feeds', [
            'name' => 'WebSub Feed',
            'url' => 'https://example.com/rss',
            'hub_url' => 'https://pubsubhubbub.appspot.com/',
            'active' => true,
        ]);

        $response->assertCreated();
        Queue::assertPushed(SubscribeToHubJob::class);
    });

    it('does not dispatch SubscribeToHubJob when no hub_url', function () {
        $this->postJson('/api/feeds', [
            'name' => 'Plain Feed',
            'url' => 'https://plain.com/rss',
            'active' => true,
        ]);

        Queue::assertNotPushed(SubscribeToHubJob::class);
    });

    it('sends subscription POST to hub and updates feed websub fields', function () {
        Http::fake([
            '*pubsubhubbub.appspot.com*' => Http::response('', 202),
        ]);

        $feed = Feed::factory()->create(['hub_url' => 'https://pubsubhubbub.appspot.com/']);

        Queue::fake([]); // allow real dispatch
        $this->app->call([new SubscribeToHubJob($feed->id), 'handle']);

        $fresh = $feed->fresh();
        expect($fresh->websub_secret)->not->toBeNull();
        expect($fresh->websub_subscribed_at)->not->toBeNull();
    });

    it('retries 429 responses and shares the hub Retry-After cooldown', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array']);
        Cache::store('array')->flush();
        Http::fake(['*pubsubhubbub.appspot.com*' => Http::response('', 429, ['Retry-After' => '42'])]);
        $feed = Feed::factory()->create(['hub_url' => 'https://pubsubhubbub.appspot.com/']);
        $job = new SubscribeToHubJob($feed->id);

        expect(fn () => $this->app->call([$job, 'handle']))->toThrow(RuntimeException::class)
            ->and($job->backoff())->toBe([42, 42, 42])
            ->and(app(PublisherRequestGate::class)->reserve($feed->hub_url)['allowed'])->toBeFalse();
    });

    it('releases the actual queue wrapper using a WebSub 503 Retry-After delay', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array']);
        Cache::store('array')->flush();
        Http::fake(['*pubsubhubbub.appspot.com*' => Http::response('', 503, ['Retry-After' => '39'])]);
        $feed = Feed::factory()->create(['hub_url' => 'https://pubsubhubbub.appspot.com/']);
        $queueJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(39);
        $job = new SubscribeToHubJob($feed->id);
        $job->setJob($queueJob);

        $this->app->call([$job, 'handle']);
    });

    it('keeps only the feed identifier in its serialized queue payload', function () {
        $job = new SubscribeToHubJob(789);
        $payload = serialize($job);
        $restored = unserialize($payload);

        expect($restored)->toBeInstanceOf(SubscribeToHubJob::class)
            ->and($restored->feedId)->toBe(789)
            ->and($payload)->not->toContain('PublisherRequestGate');
    });
});
