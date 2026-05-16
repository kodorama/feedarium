<?php

namespace App\Domains\Feed\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\Feed\Jobs\CustomizeFeedJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\Feed\Requests\CustomizeFeedRequest;

final class CustomizeFeedController extends Controller
{
    use DispatchesJobs;

    public function __invoke(CustomizeFeedRequest $request, int $id): JsonResponse
    {
        $feed = $this->dispatchSync(new CustomizeFeedJob($request, $id));

        return response()->json(['feed' => $feed]);
    }
}
