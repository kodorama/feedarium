<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Events\QueryExecuted;

describe('Mark all as read', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
    });

    it('marks every matching row beyond the visible page and returns active feed counts', function () {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $firstFeed = Feed::factory()->create(['category_id' => $category->id]);
        $secondFeed = Feed::factory()->create(['category_id' => $category->id]);
        $otherFeed = Feed::factory()->create(['category_id' => $otherCategory->id]);
        $inactiveFeed = Feed::factory()->create(['category_id' => $category->id, 'active' => false]);

        $matchingArticles = News::factory()->count(25)->create(['feed_id' => $firstFeed->id, 'is_read' => false]);
        News::factory()->create(['feed_id' => $secondFeed->id, 'is_read' => false]);
        $outsideCategory = News::factory()->create(['feed_id' => $otherFeed->id, 'is_read' => false]);
        $inactiveArticle = News::factory()->create(['feed_id' => $inactiveFeed->id, 'is_read' => false]);

        $response = $this->postJson('/api/news/read-all', ['category_id' => $category->id])->assertOk();

        expect($response->json('marked'))->toBe(27)
            ->and($matchingArticles->every(fn (News $article) => $article->fresh()->is_read))->toBeTrue()
            ->and($outsideCategory->fresh()->is_read)->toBeFalse()
            ->and($inactiveArticle->fresh()->is_read)->toBeTrue()
            ->and($response->json('unread_counts'))->toBe([
                $firstFeed->id => 0,
                $secondFeed->id => 0,
                $otherFeed->id => 1,
            ])
            ->and($response->json('affected_feed_ids'))->toBe([$firstFeed->id, $secondFeed->id]);
    });

    it('keeps feed-scoped mark-all limited to that feed while returning other feed counts', function () {
        $category = Category::factory()->create();
        $feed = Feed::factory()->create(['category_id' => $category->id]);
        $otherFeed = Feed::factory()->create(['category_id' => $category->id]);
        $matchingArticles = News::factory()->count(21)->create(['feed_id' => $feed->id, 'is_read' => false]);
        $outsideFeed = News::factory()->create(['feed_id' => $otherFeed->id, 'is_read' => false]);

        $response = $this->postJson('/api/news/read-all', ['feed_id' => $feed->id])->assertOk();

        expect($response->json('marked'))->toBe(21)
            ->and($matchingArticles->every(fn (News $article) => $article->fresh()->is_read))->toBeTrue()
            ->and($outsideFeed->fresh()->is_read)->toBeFalse()
            ->and($response->json('unread_counts'))->toBe([
                $feed->id => 0,
                $otherFeed->id => 1,
            ])
            ->and($response->json('affected_feed_ids'))->toBe([$feed->id]);
    });

    it('returns an unread article created after the bulk update in the same response', function () {
        $feed = Feed::factory()->create();
        News::factory()->create(['feed_id' => $feed->id, 'is_read' => false]);
        $insertAfterUpdate = true;

        DB::listen(function (QueryExecuted $query) use ($feed, &$insertAfterUpdate): void {
            if (! $insertAfterUpdate || ! str_starts_with(strtolower(trim($query->sql)), 'update') || ! str_contains($query->sql, 'news')) {
                return;
            }

            $insertAfterUpdate = false;
            News::factory()->create(['feed_id' => $feed->id, 'is_read' => false]);
        });

        $response = $this->postJson('/api/news/read-all')->assertOk();
        $newArticle = News::query()->where('feed_id', $feed->id)->latest('id')->firstOrFail();

        expect($response->json('marked'))->toBe(1)
            ->and($newArticle->is_read)->toBeFalse()
            ->and($response->json("unread_counts.{$feed->id}"))->toBe(1)
            ->and($response->json('affected_feed_ids'))->toBe([$feed->id]);
    });

    it('keeps repeated mark-all requests idempotent and returns current counts', function () {
        $feed = Feed::factory()->create();
        News::factory()->count(2)->create(['feed_id' => $feed->id, 'is_read' => false]);

        $first = $this->postJson('/api/news/read-all')->assertOk();
        $second = $this->postJson('/api/news/read-all')->assertOk();

        expect($first->json('marked'))->toBe(2)
            ->and($second->json('marked'))->toBe(0)
            ->and($second->json("unread_counts.{$feed->id}"))->toBe(0)
            ->and($second->json('affected_feed_ids'))->toBe([$feed->id]);
    });
});
