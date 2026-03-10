<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\News\Jobs\SaveArticleJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

final class SaveArticleController extends Controller
{
    use DispatchesJobs;

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $this->dispatchSync(new SaveArticleJob($request->user()->id, $id));

        return response()->json(['message' => 'Article saved.']);
    }
}
