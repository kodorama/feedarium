<?php

use App\Models\Feed;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use App\Domains\Feed\Jobs\ImportFeedItemsJob;

$rssXml = '<rss version="2.0"><channel><title>T</title><link>https://x.com</link></channel></rss>';

describe('WebSub callback', function () use ($rssXml) {
    beforeEach(function () {
        Queue::fake();
        User::factory()->create(['is_admin' => true]);
    });

    it('responds to GET verification with hub.challenge', function () {
        $feed = Feed::factory()->create();

        $response = $this->get("/api/websub/callback/{$feed->id}?hub.mode=subscribe&hub.challenge=testchallenge&hub.topic=https://example.com/rss");

        $response->assertOk();
        $response->assertSee('testchallenge');
    });

    it('accepts POST push with valid HMAC signature and dispatches import', function () use ($rssXml) {
        $secret = 'my-secret-key';
        $feed = Feed::factory()->create(['websub_secret' => $secret]);

        $signature = 'sha256='.hash_hmac('sha256', $rssXml, $secret);

        $response = $this->call(
            'POST',
            "/api/websub/callback/{$feed->id}",
            [],
            [],
            [],
            ['HTTP_X-Hub-Signature-256' => $signature, 'CONTENT_TYPE' => 'application/rss+xml'],
            $rssXml
        );

        $response->assertOk();
        Queue::assertPushed(ImportFeedItemsJob::class);
    });

    it('rejects POST push with invalid HMAC signature', function () use ($rssXml) {
        $feed = Feed::factory()->create(['websub_secret' => 'correct-secret']);

        $response = $this->call(
            'POST',
            "/api/websub/callback/{$feed->id}",
            [],
            [],
            [],
            ['HTTP_X-Hub-Signature-256' => 'sha256=invalidsignature', 'CONTENT_TYPE' => 'application/rss+xml'],
            $rssXml
        );

        $response->assertForbidden();
        Queue::assertNotPushed(ImportFeedItemsJob::class);
    });

    it('accepts POST push without signature when feed has no secret', function () use ($rssXml) {
        $feed = Feed::factory()->create(['websub_secret' => null]);

        $response = $this->call(
            'POST',
            "/api/websub/callback/{$feed->id}",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/rss+xml'],
            $rssXml
        );

        $response->assertOk();
        Queue::assertPushed(ImportFeedItemsJob::class);
    });
});
