<?php

namespace App\Domains\Feed\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\Feed\Jobs\UpdateFeedJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\Feed\Requests\UpdateFeedRequest;

class UpdateFeedController extends Controller
{
    use DispatchesJobs;

    public function __invoke(UpdateFeedRequest $request, int $id): JsonResponse
    {
        $feed = $this->dispatchSync(new UpdateFeedJob, $request, $id);

        return response()->json(['feed' => $feed]);
    }
}
