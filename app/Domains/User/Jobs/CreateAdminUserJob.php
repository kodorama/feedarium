<?php

namespace App\Domains\User\Jobs;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Domains\User\Requests\RegisterAdminRequest;

class CreateAdminUserJob
{
    public function handle(RegisterAdminRequest $request): User
    {
        return User::query()->create([
            'name' => $request->validated()['name'],
            'email' => $request->validated()['email'],
            'password' => Hash::make($request->validated()['password']),
            'is_admin' => true,
        ]);
    }
}
