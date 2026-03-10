<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use App\Domains\Feed\Requests\SearchFeedsRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Searches feeds by name, description, and url using portable LIKE queries.
 * Sync job (no ShouldQueue) — returns paginated results.
 */
final class SearchFeedsJob
{
    public function __construct(
        private readonly SearchFeedsRequest $request,
    ) {}

    public function handle(): LengthAwarePaginator
    {
        $q = $this->request->string('q')->toString();
        $term = "%{$q}%";

        return Feed::query()
            ->with('category')
            ->where(function ($query) use ($term) {
                $query->where('name', 'LIKE', $term)
                    ->orWhere('description', 'LIKE', $term)
                    ->orWhere('url', 'LIKE', $term);
            })
            ->orderBy('name')
            ->paginate(20);
    }
}
