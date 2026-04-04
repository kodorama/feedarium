<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Domains\Feed\Jobs\RefreshAllFeedsJob;
use App\Domains\News\Jobs\PruneOldArticlesJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RefreshAllFeedsJob)->everyFifteenMinutes();
Schedule::job(new PruneOldArticlesJob)->daily();
