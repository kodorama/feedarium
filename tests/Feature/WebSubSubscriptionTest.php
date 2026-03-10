<?php

use App\Models\Feed;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use App\Domains\Feed\Jobs\SubscribeToHubJob;

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
            'pubsubhubbub.appspot.com' => Http::response('', 202),
        ]);

        $feed = Feed::factory()->create(['hub_url' => 'https://pubsubhubbub.appspot.com/']);

        Queue::fake([]); // allow real dispatch
        (new SubscribeToHubJob($feed->id))->handle();

        $fresh = $feed->fresh();
        expect($fresh->websub_secret)->not->toBeNull();
        expect($fresh->websub_subscribed_at)->not->toBeNull();
    });
});
