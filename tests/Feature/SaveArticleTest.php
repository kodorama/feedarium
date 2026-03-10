<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;

describe('Save Article', function () {
    beforeEach(function () {
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('saves an article for the authenticated user', function () {
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id]);

        $response = $this->postJson("/api/news/{$news->id}/save");

        $response->assertOk();
        $this->assertDatabaseHas('saved_articles', [
            'user_id' => User::first()->id,
            'news_id' => $news->id,
        ]);
    });

    it('is idempotent — saving twice does not create duplicate', function () {
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id]);
        $user = User::first();

        $this->postJson("/api/news/{$news->id}/save");
        $this->postJson("/api/news/{$news->id}/save");

        expect(\Illuminate\Support\Facades\DB::table('saved_articles')
            ->where('user_id', $user->id)
            ->where('news_id', $news->id)
            ->count()
        )->toBe(1);
    });

    it('unsaves an article', function () {
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id]);
        $user = User::first();

        $this->postJson("/api/news/{$news->id}/save");
        $response = $this->deleteJson("/api/news/{$news->id}/save");

        $response->assertOk();
        $this->assertDatabaseMissing('saved_articles', [
            'user_id' => $user->id,
            'news_id' => $news->id,
        ]);
    });

    it('returns only the current user\'s saved articles', function () {
        $otherUser = User::factory()->create();
        $feed = Feed::factory()->create();
        $myNews = News::factory()->create(['feed_id' => $feed->id]);
        $theirNews = News::factory()->create(['feed_id' => $feed->id]);

        // Save one for me
        $this->postJson("/api/news/{$myNews->id}/save");
        // Save one directly for other user
        \Illuminate\Support\Facades\DB::table('saved_articles')->insert([
            'user_id' => $otherUser->id,
            'news_id' => $theirNews->id,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/news/saved');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.id'))->toBe($myNews->id);
    });
});
