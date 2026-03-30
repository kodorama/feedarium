<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;

describe('Mark As Read', function () {
    beforeEach(function () {
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('marks an article as read', function () {
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'is_read' => false]);

        $response = $this->patchJson("/api/news/{$news->id}/read");

        $response->assertOk();
        $this->assertDatabaseHas('news', [
            'id' => $news->id,
            'is_read' => true,
        ]);
    });

    it('is idempotent — marking read twice leaves is_read true', function () {
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'is_read' => false]);

        $this->patchJson("/api/news/{$news->id}/read");
        $response = $this->patchJson("/api/news/{$news->id}/read");

        $response->assertOk();
        $this->assertDatabaseHas('news', [
            'id' => $news->id,
            'is_read' => true,
        ]);
    });

    it('returns 404 for a non-existent article', function () {
        $response = $this->patchJson('/api/news/99999/read');

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        auth()->logout();

        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id]);

        $response = $this->patchJson("/api/news/{$news->id}/read");

        $response->assertUnauthorized();
    });
});
