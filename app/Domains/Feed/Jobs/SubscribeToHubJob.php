<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Sends a WebSub (PubSubHubbub) subscription request to the hub URL.
 * Updates the feed's websub_secret and websub_subscribed_at on success.
 */
final class SubscribeToHubJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $feedId) {}

    public function handle(): void
    {
        $feed = Feed::query()->findOrFail($this->feedId);

        if (! $feed->hub_url) {
            return;
        }

        $secret = Str::random(40);
        $callbackUrl = url("/api/websub/callback/{$feed->id}");

        $response = Http::asForm()->post($feed->hub_url, [
            'hub.callback' => $callbackUrl,
            'hub.mode' => 'subscribe',
            'hub.topic' => $feed->url,
            'hub.secret' => $secret,
            'hub.lease_seconds' => 86400,
        ]);

        if ($response->successful() || $response->status() === 202) {
            Feed::query()->where('id', $this->feedId)->update([
                'websub_secret' => $secret,
                'websub_subscribed_at' => now(),
            ]);
        }
    }
}
