<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\News\Jobs\SyncScoutSettingsJob;

final class ScoutSyncSettingsController extends Controller
{
    use DispatchesJobs;

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()->is_admin, 403);

        $this->dispatch(new SyncScoutSettingsJob);

        return response()->json(['message' => 'Index settings sync queued.']);
    }
}
