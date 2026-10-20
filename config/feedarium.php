<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Full Page Body Scraping
    |--------------------------------------------------------------------------
    |
    | When enabled, Feedarium will fetch and store the full article body HTML
    | for each newly imported article. This requires more bandwidth and
    | storage but enables an in-app reading experience.
    |
    */
    'scrape_full_body' => env('FEEDARIUM_SCRAPE_FULL_BODY', false),

    /*
    |--------------------------------------------------------------------------
    | Publisher request coordination
    |--------------------------------------------------------------------------
    |
    | Queue workers and scheduled feed fetches share this atomic cache store.
    | Use a shared store (Redis or database) for multi-host workers. The file
    | store coordinates processes on one host only.
    |
    */
    'publisher_throttle' => [
        'cache_store' => env('FEEDARIUM_PUBLISHER_CACHE_STORE'),
        'requests_per_minute' => (int) env('FEEDARIUM_PUBLISHER_REQUESTS_PER_MINUTE', 6),
        'global_requests_per_minute' => (int) env('FEEDARIUM_GLOBAL_REQUESTS_PER_MINUTE', 30),
        'lock_seconds' => (int) env('FEEDARIUM_PUBLISHER_LOCK_SECONDS', 5),
        'lock_contention_release_seconds' => (int) env('FEEDARIUM_PUBLISHER_LOCK_RELEASE_SECONDS', 2),
        'cooldown_max_seconds' => (int) env('FEEDARIUM_PUBLISHER_COOLDOWN_MAX_SECONDS', 3600),
        'retry_window_hours' => (int) env('FEEDARIUM_PUBLISHER_RETRY_WINDOW_HOURS', 6),
        // Exact hostname => shared publisher key; configure related subdomains explicitly.
        'publisher_aliases' => [],
    ],
];
