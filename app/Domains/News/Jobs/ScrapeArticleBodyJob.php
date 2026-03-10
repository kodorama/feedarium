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
 * Fetches the full article body from the article URL and stores it as full_body.
 * Only runs when config('feedarium.scrape_full_body') is true.
 *
 * Queued so that scraping is non-blocking and retryable.
 */
final class ScrapeArticleBodyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $newsId) {}

    public function handle(): void
    {
        if (! config('feedarium.scrape_full_body', false)) {
            return;
        }

        $news = News::query()->findOrFail($this->newsId);

        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Feedarium/1.0 (+https://github.com/feedarium)'])
                ->get($news->link);

            if (! $response->successful()) {
                return;
            }

            $body = $this->extractMainContent($response->body());

            News::query()->where('id', $this->newsId)->update([
                'full_body' => $body,
            ]);
        } catch (Throwable $e) {
            Log::warning("ScrapeArticleBodyJob failed for news #{$this->newsId}: {$e->getMessage()}");
        }
    }

    private function extractMainContent(string $html): ?string
    {
        // Try to extract article/main content via regex
        // Attempt <article> tag first
        if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $matches)) {
            return $this->cleanHtml($matches[1]);
        }

        // Attempt <main> tag
        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $matches)) {
            return $this->cleanHtml($matches[1]);
        }

        // Attempt div with content-related class
        if (preg_match('/<div[^>]+class=["\'][^"\']*(?:content|post-body|entry-content)[^"\']*["\'][^>]*>(.*?)<\/div>/is', $html, $matches)) {
            return $this->cleanHtml($matches[1]);
        }

        return null;
    }

    private function cleanHtml(string $html): string
    {
        // Strip scripts and styles
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;

        // Strip dangerous attributes (onclick, onerror, etc.)
        $html = preg_replace('/\s+on\w+=["\'][^"\']*["\']/i', '', $html) ?? $html;

        return trim($html);
    }
}
