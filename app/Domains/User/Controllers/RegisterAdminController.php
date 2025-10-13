<?php

namespace App\Domains\User\Controllers;

use Throwable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Domains\User\Jobs\CreateAdminUserJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\User\Requests\RegisterAdminRequest;

class RegisterAdminController extends Controller
{
    use DispatchesJobs;

    /**
     * @throws Throwable
     */
    public function __invoke(RegisterAdminRequest $request): RedirectResponse
    {
        DB::beginTransaction();
        try {
            $user = (new CreateAdminUserJob)->handle($request);
            Auth::login($user);
            DB::commit();

            return redirect()->route('dashboard');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['register' => $e->getMessage()]);
        }
    }
}
