<?php

use App\Models\Feed;
use App\Models\User;

describe('Search Feeds', function () {
    beforeEach(function () {
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('returns matching feeds by name', function () {
        Feed::factory()->create(['name' => 'Laravel News']);
        Feed::factory()->create(['name' => 'PHP Weekly']);

        $response = $this->getJson('/api/feeds/search?q=Laravel');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.name'))->toBe('Laravel News');
    });

    it('returns empty results when no match', function () {
        Feed::factory()->create(['name' => 'Some Feed']);

        $response = $this->getJson('/api/feeds/search?q=nomatch');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(0);
    });

    it('returns 422 when query is less than 2 characters', function () {
        $response = $this->getJson('/api/feeds/search?q=x');

        $response->assertUnprocessable();
    });

    it('requires authentication', function () {
        $this->app['auth']->logout();
        $response = $this->getJson('/api/feeds/search?q=test');
        $response->assertUnauthorized();
    });
});
