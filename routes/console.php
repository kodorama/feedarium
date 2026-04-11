<?php

use Illuminate\Support\Facades\Schedule;
use App\Domains\Feed\Jobs\RefreshAllFeedsJob;
use App\Domains\News\Jobs\PruneOldArticlesJob;

Schedule::job(new RefreshAllFeedsJob)->everyFifteenMinutes();
Schedule::job(new PruneOldArticlesJob)->daily();
