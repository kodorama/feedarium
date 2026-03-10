<?php

namespace App\Domains\News\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Saves an article to the current user's reading list (idempotent via insertOrIgnore).
 */
final class SaveArticleJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $userId,
        private readonly int $newsId,
    ) {}

    public function handle(): void
    {
        DB::table('saved_articles')->insertOrIgnore([
            'user_id' => $this->userId,
            'news_id' => $this->newsId,
            'created_at' => now(),
        ]);
    }
}
