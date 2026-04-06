<?php

namespace App\Domains\News\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * Syncs Scout search index settings with the configured search engine.
 * Only effective when using a driver that supports index settings (Meilisearch, Algolia, Typesense).
 * Gracefully handles environments where the command is unavailable.
 */
final class SyncScoutSettingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        try {
            Artisan::call('scout:sync-index-settings');
        } catch (CommandNotFoundException $e) {
            Log::warning('scout:sync-index-settings command is not available. Ensure your Scout driver supports index settings sync (Meilisearch, Algolia, Typesense).', [
                'scout_driver' => config('scout.driver'),
            ]);
        }
    }
}
