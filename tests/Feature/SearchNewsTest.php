<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;
use App\Models\Category;

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
        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.title'))->toBe('Laravel is awesome');
    });

    it('returns empty results when no match', function () {
        $feed = Feed::factory()->create();
        News::factory()->create(['feed_id' => $feed->id, 'title' => 'Some article']);

        $response = $this->getJson('/api/news/search?q=nomatch');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(0);
    });

    it('filters results by category_id', function () {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        $feedA = Feed::factory()->create(['category_id' => $categoryA->id]);
        $feedB = Feed::factory()->create(['category_id' => $categoryB->id]);

        News::factory()->create(['feed_id' => $feedA->id, 'title' => 'Vue article in category A']);
        News::factory()->create(['feed_id' => $feedB->id, 'title' => 'Vue article in category B']);

        $response = $this->getJson('/api/news/search?q=Vue&category_id='.$categoryA->id);

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.title'))->toBe('Vue article in category A');
    });

    it('filters results by feed_id', function () {
        $feedA = Feed::factory()->create();
        $feedB = Feed::factory()->create();

        News::factory()->create(['feed_id' => $feedA->id, 'title' => 'React article in feed A']);
        News::factory()->create(['feed_id' => $feedB->id, 'title' => 'React article in feed B']);

        $response = $this->getJson('/api/news/search?q=React&feed_id='.$feedA->id);

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.title'))->toBe('React article in feed A');
    });

    it('returns simple-paginated response shape', function () {
        $feed = Feed::factory()->create();
        News::factory()->create(['feed_id' => $feed->id, 'title' => 'Cursor test article']);

        $response = $this->getJson('/api/news/search?q=Cursor');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links' => ['first', 'prev', 'next'], 'meta' => ['path', 'per_page', 'current_page']]);
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
