<?php

namespace App\Domains\User\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\User\Requests\CreateUserRequest;

final class CreateUserJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly CreateUserRequest $request,
    ) {}

    public function handle(): User
    {
        return User::query()->create([
            'name' => $this->request->string('name')->toString(),
            'email' => $this->request->string('email')->toString(),
            'password' => Hash::make($this->request->string('password')->toString()),
        ]);
    }
}
