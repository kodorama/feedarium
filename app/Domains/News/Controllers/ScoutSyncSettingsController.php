<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;

final class ScoutSyncSettingsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()->is_admin, 403);

        Artisan::call('scout:sync-index-settings', ['--no-interaction' => true]);

        return response()->json(['message' => 'Index settings synced.']);
    }
}
