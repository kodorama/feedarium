<?php

namespace App\Domains\News\Controllers;

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

        return response()->json(['marked' => $count]);
    }
}
