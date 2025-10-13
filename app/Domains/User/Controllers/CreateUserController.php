<?php

namespace App\Domains\User\Controllers;

use Illuminate\Http\RedirectResponse;
use App\Domains\User\Jobs\CreateUserJob;
use App\Domains\User\Requests\CreateUserRequest;

class CreateUserController
{
    public function __invoke(CreateUserRequest $request): RedirectResponse
    {
        $user = CreateUserJob::dispatchSync($request->validated());

        return redirect()->route('users.index');
    }
}
