<?php

namespace App\Domains\News\Jobs;

use App\Models\News;
use App\Domains\News\Requests\SearchNewsRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Searches news by title and description using portable LIKE queries.
 * Sync job (no ShouldQueue) — returns paginated results.
 */
final class SearchNewsJob
{
    public function __construct(
        private readonly SearchNewsRequest $request,
    ) {}

    public function handle(): LengthAwarePaginator
    {
        $q = $this->request->string('q')->toString();
        $term = "%{$q}%";

        return News::query()
            ->with('feed')
            ->where(function ($query) use ($term) {
                $query->where('title', 'LIKE', $term)
                    ->orWhere('description', 'LIKE', $term);
            })
            ->latest('published_at')
            ->paginate(20);
    }
}
