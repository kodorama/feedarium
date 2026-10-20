<?php

namespace App\Domains\News\Controllers;

use App\Models\Feed;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class GetNewsUpdateCountController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $latestId = (int) (News::query()->max('id') ?? 0);
        $afterId = $request->integer('after_id');
        $throughId = $request->filled('through_id') ? max(0, $request->integer('through_id')) : $latestId;
        $excludedIds = collect($request->input('exclude_ids', []))->map(fn ($id) => (int) $id)->filter()->all();
        $count = 0;

        if ($request->filled('after_id')) {
            $query = News::query()
                ->where('id', '>', max(0, $afterId))
                ->where('id', '<=', $throughId)
                ->when($excludedIds !== [], fn ($q) => $q->whereNotIn('id', $excludedIds))
                ->when($request->filled('feed_id'), fn ($q) => $q->where('feed_id', $request->integer('feed_id')))
                ->when($request->filled('category_id'), fn ($q) => $q->whereHas('feed', fn ($q2) => $q2->where('category_id', $request->integer('category_id'))))
                ->when($request->boolean('unread_only'), fn ($q) => $q->where('is_read', false));

            $count = $query->count();
        }

        $unreadCounts = Feed::query()
            ->where('active', true)
            ->withCount(['news as unread_count' => fn ($query) => $query->where('is_read', false)])
            ->get(['id'])
            ->mapWithKeys(fn (Feed $feed) => [$feed->id => (int) $feed->unread_count])
            ->all();

        return response()->json([
            'data' => [
                'count' => $count,
                'latest_id' => $request->filled('through_id') ? $throughId : $latestId,
                'unread_counts' => (object) $unreadCounts,
            ],
        ]);
    }
}
