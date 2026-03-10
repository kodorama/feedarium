<?php

namespace App\Domains\News\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Removes an article from the current user's reading list.
 */
final class UnsaveArticleJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $userId,
        private readonly int $newsId,
    ) {}

    public function handle(): void
    {
        DB::table('saved_articles')
            ->where('user_id', $this->userId)
            ->where('news_id', $this->newsId)
            ->delete();
    }
}
