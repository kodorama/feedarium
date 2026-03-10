<?php

namespace App\Domains\User\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Http\RedirectResponse;
use App\Domains\User\Jobs\UpdateUserJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\User\Requests\UpdateUserRequest;

final class UpdateUserController extends Controller
{
    use DispatchesJobs;

    public function __invoke(UpdateUserRequest $request, int $id): JsonResponse|RedirectResponse
    {
        $user = $this->dispatchSync(new UpdateUserJob($request, $id));

        if ($request->expectsJson()) {
            return response()->json(['user' => $user]);
        }

        return redirect()->route('users.index');
    }
}
