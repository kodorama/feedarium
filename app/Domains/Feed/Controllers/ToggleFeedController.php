<?php

namespace App\Domains\Feed\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\Feed\Jobs\ToggleFeedActiveJob;

final class ToggleFeedController extends Controller
{
    use DispatchesJobs;

    public function __invoke(int $id): JsonResponse
    {
        $feed = $this->dispatchSync(new ToggleFeedActiveJob($id));

        return response()->json(['feed' => $feed]);
    }
}
