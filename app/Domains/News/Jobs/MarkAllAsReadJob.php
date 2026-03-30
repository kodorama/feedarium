<?php

namespace App\Domains\News\Jobs;

use App\Models\News;
use Illuminate\Database\Eloquent\Builder;

/**
 * Marks all unread articles as read.
 * Optionally scoped to a single feed or category.
 */
final class MarkAllAsReadJob
{
    public function __construct(
        private readonly ?int $feedId = null,
        private readonly ?int $categoryId = null,
    ) {}

    public function handle(): int
    {
        return News::query()
            ->where('is_read', false)
            ->when($this->feedId !== null, fn (Builder $q) => $q->where('feed_id', $this->feedId))
            ->when(
                $this->categoryId !== null,
                fn (Builder $q) => $q->whereHas('feed', fn (Builder $q2) => $q2->where('category_id', $this->categoryId)),
            )
            ->update(['is_read' => true]);
    }
}
