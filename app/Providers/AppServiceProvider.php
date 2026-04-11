<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Throttle full-body scraping to avoid getting blocked by external servers.
        // Limits: 5 requests per minute per target domain + 30 per minute globally.
        // When a job exceeds the limit it is released back to the queue automatically.
        RateLimiter::for('article-scraping', function (object $job) {
            /** @var \App\Domains\News\Jobs\ScrapeArticleBodyJob $job */
            $host = $job->link ? (parse_url($job->link, PHP_URL_HOST) ?? 'unknown') : 'unknown';

            return [
                Limit::perMinute(5)->by("scrape-domain:{$host}"),
                Limit::perMinute(30)->by('scrape-global'),
            ];
        });
    }
}
