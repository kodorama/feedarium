<?php

namespace App\Domains\News\Jobs;

use App\Models\News;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class PruneOldArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function handle(): void
    {
        $enabled = Setting::get('news_retention_enabled', 'true') === 'true';

        if (! $enabled) {
            return;
        }

        $days = (int) Setting::get('news_retention_days', '90');
        $cutoff = now()->subDays($days);

        $savedNewsIds = DB::table('saved_articles')->pluck('news_id');

        $deleted = News::query()
            ->where('created_at', '<', $cutoff)
            ->whereNotIn('id', $savedNewsIds)
            ->delete();

        Log::info("PruneOldArticlesJob: deleted {$deleted} articles older than {$days} days.");
    }
}
