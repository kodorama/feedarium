<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\News\Jobs\ImportScoutIndexJob;

final class ScoutImportController extends Controller
{
    use DispatchesJobs;

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()->is_admin, 403);

        $this->dispatch(new ImportScoutIndexJob);

        return response()->json(['message' => 'Import job dispatched.']);
    }
}
