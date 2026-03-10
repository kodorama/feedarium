<?php

namespace App\Domains\News\Jobs;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Returns the authenticated user's saved articles, paginated.
 * Sync job (no ShouldQueue).
 */
final class ListSavedArticlesJob
{
    public function __construct(
        private readonly User $user,
    ) {}

    public function handle(): LengthAwarePaginator
    {
        return $this->user
            ->savedArticles()
            ->with('feed')
            ->latest('saved_articles.created_at')
            ->paginate(20);
    }
}
