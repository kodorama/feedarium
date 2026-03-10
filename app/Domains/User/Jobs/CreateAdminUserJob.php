<?php

namespace App\Domains\User\Jobs;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Domains\User\Requests\RegisterAdminRequest;

final class CreateAdminUserJob
{
    public function __construct(
        private readonly RegisterAdminRequest $request,
    ) {}

    public function handle(): User
    {
        return User::query()->create([
            'name' => $this->request->string('name')->toString(),
            'email' => $this->request->string('email')->toString(),
            'password' => Hash::make($this->request->string('password')->toString()),
            'is_admin' => true,
        ]);
    }
}
