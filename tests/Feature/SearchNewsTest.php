<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;

describe('Search News', function () {
    beforeEach(function () {
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('returns matching news articles', function () {
        $feed = Feed::factory()->create();
        News::factory()->create(['feed_id' => $feed->id, 'title' => 'Laravel is awesome']);
        News::factory()->create(['feed_id' => $feed->id, 'title' => 'Unrelated article']);

        $response = $this->getJson('/api/news/search?q=Laravel');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.title'))->toBe('Laravel is awesome');
    });

    it('returns empty results when no match', function () {
        $feed = Feed::factory()->create();
        News::factory()->create(['feed_id' => $feed->id, 'title' => 'Some article']);

        $response = $this->getJson('/api/news/search?q=nomatch');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(0);
    });

    it('returns 422 when query is less than 2 characters', function () {
        $response = $this->getJson('/api/news/search?q=a');

        $response->assertUnprocessable();
    });

    it('requires authentication', function () {
        $this->app['auth']->logout();
        $response = $this->getJson('/api/news/search?q=test');
        $response->assertUnauthorized();
    });
});
