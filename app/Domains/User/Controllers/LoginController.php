<?php

namespace App\Domains\User\Controllers;

use Illuminate\Routing\Controller;
use App\Domains\User\Jobs\LoginUserJob;
use App\Domains\User\Requests\LoginRequest;
use Illuminate\Foundation\Bus\DispatchesJobs;

class LoginController extends Controller
{
    use DispatchesJobs;

    public function __invoke(LoginRequest $request): \Illuminate\Http\JsonResponse
    {
        $token = $this->dispatchSync(new LoginUserJob($request->validated()));
        if (! $token) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json(['token' => $token]);
    }
}
