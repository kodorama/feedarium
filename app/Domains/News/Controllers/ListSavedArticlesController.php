<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\News\Jobs\ListSavedArticlesJob;

final class ListSavedArticlesController extends Controller
{
    use DispatchesJobs;

    public function __invoke(Request $request): JsonResponse
    {
        $articles = $this->dispatchSync(new ListSavedArticlesJob($request->user()));

        return response()->json($articles);
    }
}
