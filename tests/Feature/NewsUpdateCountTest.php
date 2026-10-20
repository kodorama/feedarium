<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;
use App\Models\Category;
use Illuminate\Support\Facades\Queue;
use App\Domains\Feed\Jobs\ImportFeedItemsJob;

describe('News update count', function () {
    beforeEach(function () {
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('counts multiple new articles using the active feed, category and unread filters', function () {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $feed = Feed::factory()->create(['category_id' => $category->id]);
        $otherFeed = Feed::factory()->create(['category_id' => $otherCategory->id]);
        $baseline = News::factory()->create(['feed_id' => $feed->id]);
        News::factory()->count(2)->create(['feed_id' => $feed->id, 'is_read' => false]);
        News::factory()->create(['feed_id' => $feed->id, 'is_read' => true]);
        News::factory()->create(['feed_id' => $otherFeed->id, 'is_read' => false]);

        $response = $this->getJson('/api/news/updates?after_id='.$baseline->id.'&category_id='.$category->id.'&unread_only=1');

        $response->assertOk();
        expect($response->json('data.count'))->toBe(2)
            ->and($response->json('data.latest_id'))->toBe(News::query()->max('id'));
    });

    it('uses a high-water mark so routine polls do not report the same articles again', function () {
        $feed = Feed::factory()->create();
        $baseline = News::factory()->create(['feed_id' => $feed->id]);
        $arrival = News::factory()->create(['feed_id' => $feed->id]);

        $first = $this->getJson('/api/news/updates?after_id='.$baseline->id)->assertOk()->json('data');
        $second = $this->getJson('/api/news/updates?after_id='.$first['latest_id'])->assertOk()->json('data');

        expect($first['count'])->toBe(1)
            ->and($second['count'])->toBe(0)
            ->and($first['latest_id'])->toBe($arrival->id);
    });

    it('returns current unread counts for active sidebar feeds only', function () {
        $activeFeed = Feed::factory()->create(['active' => true]);
        $emptyActiveFeed = Feed::factory()->create(['active' => true]);
        $inactiveFeed = Feed::factory()->create(['active' => false]);
        News::factory()->count(2)->create(['feed_id' => $activeFeed->id, 'is_read' => false]);
        News::factory()->create(['feed_id' => $activeFeed->id, 'is_read' => true]);
        News::factory()->create(['feed_id' => $inactiveFeed->id, 'is_read' => false]);

        $counts = $this->getJson('/api/news/updates')->assertOk()->json('data.unread_counts');

        expect($counts)->toBe([
            $activeFeed->id => 2,
            $emptyActiveFeed->id => 0,
        ])->and(array_key_exists($inactiveFeed->id, $counts))->toBeFalse();
    });

    it('keeps the list inside a captured snapshot and counts undisplayed arrivals after acknowledgement', function () {
        $feed = Feed::factory()->create();
        $baseline = News::factory()->create(['feed_id' => $feed->id]);
        $displayed = News::factory()->create(['feed_id' => $feed->id]);
        $notDisplayed = News::factory()->create(['feed_id' => $feed->id]);
        $snapshot = $notDisplayed->id;
        $arrivedDuringListLoad = News::factory()->create(['feed_id' => $feed->id]);

        $this->getJson('/api/news?per_page=20&snapshot_id='.$snapshot)
            ->assertOk()
            ->assertJsonMissing(['id' => $arrivedDuringListLoad->id]);

        $acknowledgement = $this->getJson('/api/news/updates?after_id='.$baseline->id.'&through_id='.$snapshot.'&exclude_ids[]='.$displayed->id)
            ->assertOk()
            ->json('data');

        expect($acknowledgement['count'])->toBe(1)
            ->and($acknowledgement['latest_id'])->toBe($snapshot);
    });

    it('accepts more than one thousand displayed IDs as a bounded JSON acknowledgement', function () {
        $feed = Feed::factory()->create();
        $baseline = News::factory()->create(['feed_id' => $feed->id]);
        $arrivals = News::factory()->count(1100)->create(['feed_id' => $feed->id]);
        $displayedIds = $arrivals->take(1099)->pluck('id')->all();
        $payload = [
            'after_id' => $baseline->id,
            'through_id' => $arrivals->last()->id,
            'exclude_ids' => $displayedIds,
        ];

        expect(strlen(json_encode($payload)))->toBeLessThan(16000);

        $response = $this->postJson('/api/news/updates', $payload)->assertOk()->json('data');

        expect(count($displayedIds))->toBe(1099)
            ->and($response['count'])->toBe(1)
            ->and($response['latest_id'])->toBe($arrivals->last()->id);
    });

    it('does not count duplicate feed imports as arrivals', function () {
        Queue::fake();
        $feed = Feed::factory()->create();
        $existing = News::factory()->create([
            'feed_id' => $feed->id,
            'guid' => 'https://example.com/article-1',
            'link' => 'https://example.com/article-1',
        ]);
        $xml = <<<'XML'
            <rss version="2.0"><channel><title>Test</title><item><title>Duplicate</title><link>https://example.com/article-1</link><guid>https://example.com/article-1</guid></item></channel></rss>
            XML;

        (new ImportFeedItemsJob($feed->id, $xml))->handle();

        $response = $this->getJson('/api/news/updates?after_id='.$existing->id);

        expect($response->assertOk()->json('data.count'))->toBe(0)
            ->and(News::query()->where('feed_id', $feed->id)->count())->toBe(1);
    });

    it('requires authentication like the article list', function () {
        $this->app['auth']->logout();

        $this->getJson('/api/news/updates')->assertUnauthorized();
    });
});
