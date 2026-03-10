<?php

namespace App\Domains\Feed\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\Feed\Jobs\CreateFeedJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\Feed\Requests\CreateFeedRequest;

final class CreateFeedController extends Controller
{
    use DispatchesJobs;

    public function __invoke(CreateFeedRequest $request): JsonResponse
    {
        $feed = $this->dispatchSync(new CreateFeedJob($request));

        return response()->json(['feed' => $feed], 201);
    }
}
