<?php

namespace App\Domains\News\Jobs;

use App\Models\User;
use Illuminate\Pagination\CursorPaginator;

/**
 * Returns the authenticated user's saved articles, cursor-paginated.
 */
final class ListSavedArticlesJob
{
    public function __construct(
        private readonly User $user,
        private readonly ?string $cursor = null,
        private readonly int $perPage = 20,
    ) {}

    public function handle(): CursorPaginator
    {
        return $this->user
            ->savedArticles()
            ->with('feed')
            ->orderByDesc('saved_articles.created_at')
            ->orderByDesc('news.id')
            ->cursorPaginate($this->perPage, ['*'], 'cursor', $this->cursor);
    }
}
