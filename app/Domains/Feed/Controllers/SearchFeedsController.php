<?php

namespace App\Domains\Feed\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\Feed\Jobs\SearchFeedsJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\Feed\Requests\SearchFeedsRequest;

final class SearchFeedsController extends Controller
{
    use DispatchesJobs;

    public function __invoke(SearchFeedsRequest $request): JsonResponse
    {
        $results = $this->dispatchSync(new SearchFeedsJob($request));

        return response()->json($results);
    }
}
