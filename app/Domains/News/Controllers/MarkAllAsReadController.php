<?php

namespace App\Domains\News\Controllers;

use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\News\Jobs\MarkAllAsReadJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

final class MarkAllAsReadController extends Controller
{
    use DispatchesJobs;

    public function __invoke(Request $request): JsonResponse
    {
        $count = $this->dispatchSync(new MarkAllAsReadJob(
            feedId: $request->filled('feed_id') ? $request->integer('feed_id') : null,
            categoryId: $request->filled('category_id') ? $request->integer('category_id') : null,
        ));

        $activeFeeds = Feed::query()
            ->where('active', true)
            ->withCount(['news as unread_count' => fn ($query) => $query->where('is_read', false)])
            ->get(['id', 'category_id']);

        $unreadCounts = $activeFeeds->mapWithKeys(fn (Feed $feed) => [$feed->id => (int) $feed->unread_count])->all();
        $affectedFeedIds = $activeFeeds
            ->filter(fn (Feed $feed) => (! $request->filled('feed_id') || $feed->id === $request->integer('feed_id')) &&
                (! $request->filled('category_id') || $feed->category_id === $request->integer('category_id')),
            )
            ->pluck('id')
            ->values()
            ->all();

        return response()->json([
            'marked' => $count,
            'unread_counts' => (object) $unreadCounts,
            'affected_feed_ids' => $affectedFeedIds,
        ]);
    }
}
