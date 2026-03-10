<?php

namespace App\Domains\User\Controllers;

use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Domains\User\Jobs\CreateAdminUserJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\User\Requests\RegisterAdminRequest;

final class RegisterAdminController extends Controller
{
    use DispatchesJobs;

    /**
     * @throws Throwable
     */
    public function __invoke(RegisterAdminRequest $request): JsonResponse|RedirectResponse
    {
        DB::beginTransaction();
        try {
            $user = $this->dispatchSync(new CreateAdminUserJob($request));
            Auth::login($user);
            DB::commit();

            if ($request->expectsJson()) {
                return response()->json(['user' => $user], 201);
            }

            return redirect()->route('dashboard');
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['register' => $e->getMessage()]);
        }
    }
}
