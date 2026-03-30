<?php

namespace App\Domains\News\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Domains\News\Resources\NewsResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListNewsController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 20);

        $paginator = News::query()
            ->with('feed')
            ->when($request->filled('feed_id'), fn ($q) => $q->where('feed_id', $request->integer('feed_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas('feed', fn ($q2) => $q2->where('category_id', $request->integer('category_id'))))
            ->when($request->boolean('unread_only'), fn ($q) => $q->where('is_read', false))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);

        return NewsResource::collection($paginator);
    }
}
