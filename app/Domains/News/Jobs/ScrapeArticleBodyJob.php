<?php

namespace App\Domains\News\Jobs;

use Throwable;
use App\Models\News;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Fetches the full article body from the article URL and stores it as full_body.
 * Only runs when the 'scrape_full_body' setting is enabled via the admin settings page.
 *
 * Rate-limited to 5 requests/min per target domain and 30/min globally to prevent
 * external servers from blocking this instance. Queued for non-blocking retryable work.
 */
final class ScrapeArticleBodyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Allow up to 3 unhandled exceptions before the job is permanently failed.
     * Rate-limit releases by the RateLimited middleware do NOT count as exceptions,
     * so they will not consume this budget.
     */
    public int $maxExceptions = 3;

    public int $backoff = 60;

    /**
     * Keep retrying (after rate-limit releases or transient failures) for up to 2 hours.
     * This replaces the fixed $tries = 3 which was consumed by rate-limiter releases,
     * causing MaxAttemptsExceededException before handle() was ever reached.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    /**
     * @param  int  $newsId  The News record to populate.
     * @param  string  $link  The article URL — carried here so the rate limiter
     *                        can key by domain without an extra DB query.
     */
    public function __construct(
        public readonly int $newsId,
        public readonly string $link = '',
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('article-scraping')];
    }

    public function handle(): void
    {
        if (Setting::get('scrape_full_body', 'false') !== 'true') {
            return;
        }

        $news = News::query()->findOrFail($this->newsId);

        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Feedarium/1.0 (+https://github.com/kodorama/feedarium)'])
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
