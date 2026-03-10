<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\Feed\Requests\CreateFeedRequest;

final class CreateFeedJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly CreateFeedRequest $request,
    ) {}

    public function handle(): Feed
    {
        $feed = Feed::query()->create([
            'name' => $this->request->string('name')->toString(),
            'url' => $this->request->string('url')->toString(),
            'description' => $this->request->filled('description')
                ? $this->request->string('description')->toString()
                : null,
            'active' => $this->request->boolean('active', true),
            'category_id' => $this->request->filled('category_id')
                ? $this->request->integer('category_id')
                : null,
            'hub_url' => $this->request->filled('hub_url')
                ? $this->request->string('hub_url')->toString()
                : null,
        ]);

        if ($feed->hub_url) {
            SubscribeToHubJob::dispatch($feed->id);
        }

        return $feed;
    }
}
