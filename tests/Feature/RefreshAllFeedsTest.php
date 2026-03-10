<?php

use App\Models\Feed;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use App\Domains\Feed\Jobs\FetchFeedJob;
use App\Domains\Feed\Jobs\RefreshAllFeedsJob;

describe('RefreshAllFeeds', function () {
    beforeEach(function () {
        Queue::fake();
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('dispatches FetchFeedJob for each active feed', function () {
        Feed::factory()->count(3)->create(['active' => true]);
        Feed::factory()->count(2)->create(['active' => false]);

        (new RefreshAllFeedsJob)->handle();

        Queue::assertPushed(FetchFeedJob::class, 3);
    });

    it('does not dispatch FetchFeedJob for inactive feeds', function () {
        Feed::factory()->count(2)->create(['active' => false]);

        (new RefreshAllFeedsJob)->handle();

        Queue::assertNotPushed(FetchFeedJob::class);
    });
});
