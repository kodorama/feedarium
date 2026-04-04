<?php

namespace App\Domains\Settings\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\Settings\Jobs\UpdateSettingsJob;
use App\Domains\Settings\Requests\UpdateSettingsRequest;

final class UpdateSettingsController extends Controller
{
    use DispatchesJobs;

    public function __invoke(UpdateSettingsRequest $request): JsonResponse
    {
        $this->dispatchSync(new UpdateSettingsJob($request));

        return response()->json(['message' => 'Settings updated successfully.']);
    }
}
