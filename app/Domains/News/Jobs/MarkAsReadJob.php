<?php

namespace App\Domains\News\Jobs;

use App\Models\News;

/**
 * Marks a single news article as read.
 */
final class MarkAsReadJob
{
    public function __construct(
        private readonly int $newsId,
    ) {}

    public function handle(): News
    {
        $news = News::query()->findOrFail($this->newsId);
        $news->update(['is_read' => true]);

        return $news;
    }
}
