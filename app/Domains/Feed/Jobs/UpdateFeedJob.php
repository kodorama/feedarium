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
        $oldUrl = $feed->url;

        $newUrl = $this->request->string('url')->toString();
        $newHubUrl = $this->request->filled('hub_url')
            ? $this->request->string('hub_url')->toString()
            : null;

        // Recompute favicon when the URL domain changes or favicon was never set
        $faviconUrl = $feed->favicon_url;
        if (! $faviconUrl || $newUrl !== $oldUrl) {
            $host = parse_url($newUrl, PHP_URL_HOST) ?? '';
            $faviconUrl = $host ? 'https://www.google.com/s2/favicons?domain='.urlencode($host).'&sz=32' : null;
        }

        $feed->update([
            'name' => $this->request->string('name')->toString(),
            'url' => $newUrl,
            'description' => $this->request->filled('description')
                ? $this->request->string('description')->toString()
                : null,
            'active' => $this->request->boolean('active', true),
            'category_id' => $this->request->filled('category_id')
                ? $this->request->integer('category_id')
                : null,
            'hub_url' => $newHubUrl,
            'favicon_url' => $faviconUrl,
        ]);

        if ($newHubUrl && $newHubUrl !== $oldHubUrl) {
            SubscribeToHubJob::dispatch($feed->id);
        }

        event(new FeedUpdated($feed->id));

        return $feed->fresh();
    }
}
