<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\News\Jobs\UnsaveArticleJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

final class UnsaveArticleController extends Controller
{
    use DispatchesJobs;

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $this->dispatchSync(new UnsaveArticleJob($request->user()->id, $id));

        return response()->json(['message' => 'Article removed from saved list.']);
    }
}
