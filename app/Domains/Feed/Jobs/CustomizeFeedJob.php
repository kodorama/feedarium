<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use App\Domains\Feed\Requests\CustomizeFeedRequest;

final class CustomizeFeedJob
{
    public function __construct(
        private readonly CustomizeFeedRequest $request,
        private readonly int $id,
    ) {}

    public function handle(): Feed
    {
        $feed = Feed::query()->findOrFail($this->id);

        $feed->update([
            'disable_full_article_scraping' => $this->request->boolean('disable_full_article_scraping'),
            'hide_image_in_detail' => $this->request->boolean('hide_image_in_detail'),
        ]);

        return $feed->fresh();
    }
}
