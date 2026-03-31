<?php

namespace App\Domains\News\Jobs;

use App\Models\News;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Bulk-imports all News records into the Scout search index.
 * Queued so that the admin UI is not blocked during large imports.
 * When SCOUT_QUEUE=true, Scout itself will further dispatch per-batch jobs.
 */
final class ImportScoutIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function handle(): void
    {
        Artisan::call('scout:import', ['model' => News::class]);
    }
}
