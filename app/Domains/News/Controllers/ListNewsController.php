<?php

namespace App\Domains\News\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListNewsController extends Controller
{
    public function __invoke(Request $request): LengthAwarePaginator
    {
        $perPage = $request->integer('per_page', 20);

        return News::query()
            ->with('feed')
            ->when($request->filled('feed_id'), fn ($q) => $q->where('feed_id', $request->integer('feed_id')))
            ->latest('published_at')
            ->paginate($perPage);
    }
}
