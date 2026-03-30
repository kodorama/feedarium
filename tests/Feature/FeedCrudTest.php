<?php

use App\Domains\Feed\Jobs\FetchFeedJob;
use Illuminate\Support\Facades\Queue;

describe('Feed CRUD and notifications', function () {
    beforeEach(function () {
        \App\Models\User::factory()->create(['is_admin' => true]);
        $this->actingAs(\App\Models\User::first());
    });

    it('creates a feed, sets favicon_url, and dispatches FetchFeedJob', function () {
        Queue::fake();

        $data = [
            'name' => 'Test Feed',
            'url' => 'https://example.com/rss',
            'description' => 'A test feed',
            'active' => true,
        ];

        $response = $this->postJson('/api/feeds', $data);

        $response->assertCreated();
        $this->assertDatabaseHas('feeds', [
            'name' => 'Test Feed',
            'url' => 'https://example.com/rss',
        ]);

        // favicon_url should be set from the domain
        $feed = \App\Models\Feed::query()->where('url', 'https://example.com/rss')->firstOrFail();
        expect($feed->favicon_url)->toContain('example.com');

        // FetchFeedJob must be queued immediately after creation
        Queue::assertPushed(FetchFeedJob::class, fn ($job) => $job->feedId === $feed->id);
    });

    it('updates a feed and refreshes favicon_url when url changes', function () {
        $feed = \App\Models\Feed::factory()->create([
            'url' => 'https://old.example.com/rss',
            'favicon_url' => null,
        ]);

        $data = [
            'name' => 'Updated Feed',
            'url' => 'https://new.example.com/rss',
            'description' => 'Updated description',
            'active' => false,
        ];

        $response = $this->putJson("/api/feeds/{$feed->id}", $data);

        $response->assertOk();
        $this->assertDatabaseHas('feeds', [
            'id' => $feed->id,
            'name' => 'Updated Feed',
            'active' => false,
        ]);

        $updated = $feed->fresh();
        expect($updated->favicon_url)->toContain('new.example.com');
    });
});
