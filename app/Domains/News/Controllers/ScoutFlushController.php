<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\News\Jobs\FlushScoutIndexJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

final class ScoutFlushController extends Controller
{
    use DispatchesJobs;

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()->is_admin, 403);

        $this->dispatch(new FlushScoutIndexJob);

        return response()->json(['message' => 'Flush job dispatched.']);
    }
}
