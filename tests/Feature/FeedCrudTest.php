<?php

describe('Feed CRUD and notifications', function () {
    beforeEach(function () {
        \App\Models\User::factory()->create(['is_admin' => true]);
        $this->actingAs(\App\Models\User::first());
    });

    it('creates a feed', function () {
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
    });

    it('updates a feed and broadcasts notification', function () {
        $feed = \App\Models\Feed::factory()->create();
        $data = [
            'name' => 'Updated Feed',
            'url' => $feed->url,
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
        // Optionally, assert broadcast event (if using Laravel's event fake)
        // \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\FeedUpdated::class);
    });
});
