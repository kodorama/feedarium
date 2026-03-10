<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use App\Events\FeedUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\Feed\Requests\UpdateFeedRequest;

final class UpdateFeedJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly UpdateFeedRequest $request,
        private readonly int $id,
    ) {}

    public function handle(): Feed
    {
        $feed = Feed::query()->findOrFail($this->id);

        $oldHubUrl = $feed->hub_url;

        $newHubUrl = $this->request->filled('hub_url')
            ? $this->request->string('hub_url')->toString()
            : null;

        $feed->update([
            'name' => $this->request->string('name')->toString(),
            'url' => $this->request->string('url')->toString(),
            'description' => $this->request->filled('description')
                ? $this->request->string('description')->toString()
                : null,
            'active' => $this->request->boolean('active', true),
            'category_id' => $this->request->filled('category_id')
                ? $this->request->integer('category_id')
                : null,
            'hub_url' => $newHubUrl,
        ]);

        if ($newHubUrl && $newHubUrl !== $oldHubUrl) {
            SubscribeToHubJob::dispatch($feed->id);
        }

        event(new FeedUpdated($feed->id));

        return $feed->fresh();
    }
}
