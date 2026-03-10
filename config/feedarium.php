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
];
