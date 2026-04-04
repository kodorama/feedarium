<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use App\Domains\News\Jobs\PruneOldArticlesJob;

describe('PruneOldArticlesJob', function () {
    beforeEach(function () {
        Setting::set('news_retention_enabled', 'true');
        Setting::set('news_retention_days', '30');
    });

    it('deletes articles older than the retention period', function () {
        $feed = Feed::factory()->create();

        $old = News::factory()->create([
            'feed_id' => $feed->id,
            'created_at' => now()->subDays(31),
        ]);
        $recent = News::factory()->create([
            'feed_id' => $feed->id,
            'created_at' => now()->subDays(5),
        ]);

        (new PruneOldArticlesJob)->handle();

        expect(News::query()->find($old->id))->toBeNull()
            ->and(News::query()->find($recent->id))->not->toBeNull();
    });

    it('never deletes saved articles even if they are old', function () {
        $user = User::factory()->create();
        $feed = Feed::factory()->create();

        $saved = News::factory()->create([
            'feed_id' => $feed->id,
            'created_at' => now()->subDays(100),
        ]);

        DB::table('saved_articles')->insert([
            'user_id' => $user->id,
            'news_id' => $saved->id,
            'created_at' => now(),
        ]);

        (new PruneOldArticlesJob)->handle();

        expect(News::query()->find($saved->id))->not->toBeNull();
    });

    it('does nothing when retention is disabled', function () {
        Setting::set('news_retention_enabled', 'false');

        $feed = Feed::factory()->create();

        $old = News::factory()->create([
            'feed_id' => $feed->id,
            'created_at' => now()->subDays(100),
        ]);

        (new PruneOldArticlesJob)->handle();

        expect(News::query()->find($old->id))->not->toBeNull();
    });
});
