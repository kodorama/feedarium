<?php

namespace App\Domains\User\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Http\RedirectResponse;
use App\Domains\User\Jobs\CreateUserJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\User\Requests\CreateUserRequest;

final class CreateUserController extends Controller
{
    use DispatchesJobs;

    public function __invoke(CreateUserRequest $request): JsonResponse|RedirectResponse
    {
        $user = $this->dispatchSync(new CreateUserJob($request));

        if ($request->expectsJson()) {
            return response()->json(['user' => $user], 201);
        }

        return redirect()->route('users.index');
    }
}
