<?php

namespace App\Domains\User\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Domains\User\Jobs\LogoutUserJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

class LogoutController extends Controller
{
    use DispatchesJobs;

    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->dispatchSync(new LogoutUserJob($request->user()));

        return response()->json(['message' => 'Logged out']);
    }
}
