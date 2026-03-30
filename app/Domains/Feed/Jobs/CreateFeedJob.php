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
        $url = $this->request->string('url')->toString();
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $faviconUrl = $host ? 'https://www.google.com/s2/favicons?domain='.urlencode($host).'&sz=32' : null;

        $feed = Feed::query()->create([
            'name' => $this->request->string('name')->toString(),
            'url' => $url,
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
            'favicon_url' => $faviconUrl,
        ]);

        if ($feed->hub_url) {
            SubscribeToHubJob::dispatch($feed->id);
        }

        // Immediately kick off the first fetch in the background queue
        FetchFeedJob::dispatch($feed->id);

        return $feed;
    }
}
