<?php

namespace App\Domains\User\Controllers;

use Illuminate\Http\RedirectResponse;
use App\Domains\User\Jobs\UpdateUserJob;
use App\Domains\User\Requests\UpdateUserRequest;

class UpdateUserController
{
    public function __invoke(UpdateUserRequest $request, int $id): RedirectResponse
    {
        UpdateUserJob::dispatchSync($id, $request->validated());

        return redirect()->route('users.index');
    }
}
