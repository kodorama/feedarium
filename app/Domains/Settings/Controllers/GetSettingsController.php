<?php

namespace App\Domains\Settings\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\Settings\Jobs\GetSettingsJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

final class GetSettingsController extends Controller
{
    use DispatchesJobs;

    public function __invoke(): JsonResponse
    {
        $settings = $this->dispatchSync(new GetSettingsJob);

        return response()->json($settings);
    }
}
