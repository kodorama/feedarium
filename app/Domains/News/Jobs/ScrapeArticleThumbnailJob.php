<?php

namespace App\Domains\News\Jobs;

use Throwable;
use App\Models\News;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Scrapes the og:image meta tag from an article URL and stores it as thumbnail_url.
 *
 * Queued so that scraping is non-blocking and retryable.
 */
final class ScrapeArticleThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $newsId) {}

    public function handle(): void
    {
        $news = News::query()->findOrFail($this->newsId);

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Feedarium/1.0 (+https://github.com/feedarium)'])
                ->get($news->link);

            if (! $response->successful()) {
                return;
            }

            $thumbnailUrl = $this->extractOgImage($response->body());

            News::query()->where('id', $this->newsId)->update([
                'thumbnail_url' => $thumbnailUrl,
            ]);
        } catch (Throwable $e) {
            Log::warning("ScrapeArticleThumbnailJob failed for news #{$this->newsId}: {$e->getMessage()}");

            News::query()->where('id', $this->newsId)->update([
                'thumbnail_url' => null,
            ]);
        }
    }

    private function extractOgImage(string $html): ?string
    {
        // Try og:image first
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return $matches[1] ?: null;
        }

        // Try content before property ordering
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\'][^>]*>/i', $html, $matches)) {
            return $matches[1] ?: null;
        }

        // Twitter card image fallback
        if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return $matches[1] ?: null;
        }

        return null;
    }
}
