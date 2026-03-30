<?php

namespace App\Domains\News\Jobs;

use App\Models\Feed;
use App\Models\News;
use Illuminate\Contracts\Pagination\Paginator;
use App\Domains\News\Requests\SearchNewsRequest;

/**
 * Searches news using Laravel Scout (MeiliSearch in production, database driver in tests).
 * Fetches matching IDs from the search engine, then simple-paginates via Eloquent.
 * Sync job (no ShouldQueue) — returns simple-paginated results.
 */
final class SearchNewsJob
{
    public function handle(SearchNewsRequest $request): Paginator
    {
        $q = $request->string('q')->toString();
        $feedId = $request->filled('feed_id')
            ? $request->integer('feed_id')
            : null;
        $categoryId = $request->filled('category_id')
            ? $request->integer('category_id')
            : null;

        $feedIds = ($feedId === null && $categoryId !== null)
            ? Feed::query()->where('category_id', $categoryId)->pluck('id')->toArray()
            : null;

        return News::search($q)
            ->when($feedId !== null, fn ($builder) => $builder->where('feed_id', $feedId))
            ->when($feedIds !== null, fn ($builder) => $builder->whereIn('feed_id', $feedIds))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->simplePaginate(20);
    }
}
