<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\News\Jobs\SearchNewsJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\News\Requests\SearchNewsRequest;

final class SearchNewsController extends Controller
{
    use DispatchesJobs;

    public function __invoke(SearchNewsRequest $request): JsonResponse
    {
        $results = $this->dispatchSync(new SearchNewsJob($request));

        return response()->json($results);
    }
}
