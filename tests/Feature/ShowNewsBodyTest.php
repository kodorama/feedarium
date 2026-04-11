<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;

describe('Show News Body', function () {
    beforeEach(function () {
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('returns the full body of an article', function () {
        $feed = Feed::factory()->create();
        $news = News::factory()->create([
            'feed_id' => $feed->id,
            'full_body' => '<p>Full article body content.</p>',
        ]);

        $response = $this->getJson("/api/news/{$news->id}/body");

        $response->assertOk()
            ->assertJson(['full_body' => '<p>Full article body content.</p>']);
    });

    it('returns null full_body when article has not been scraped', function () {
        $feed = Feed::factory()->create();
        $news = News::factory()->create([
            'feed_id' => $feed->id,
            'full_body' => null,
        ]);

        $response = $this->getJson("/api/news/{$news->id}/body");

        $response->assertOk()
            ->assertJson(['full_body' => null]);
    });

    it('returns 404 for a non-existent article', function () {
        $response = $this->getJson('/api/news/99999/body');

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $this->app['auth']->logout();
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id]);

        $response = $this->getJson("/api/news/{$news->id}/body");

        $response->assertUnauthorized();
    });

    it('does not include full_body in the news list response', function () {
        $feed = Feed::factory()->create();
        News::factory()->create([
            'feed_id' => $feed->id,
            'full_body' => '<p>Some full body.</p>',
        ]);

        $response = $this->getJson('/api/news');

        $response->assertOk();
        expect(array_key_exists('full_body', $response->json('data.0')))->toBeFalse();
    });
});
