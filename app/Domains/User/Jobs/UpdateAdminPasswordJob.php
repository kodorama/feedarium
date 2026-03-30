<?php

namespace App\Domains\User\Jobs;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Bus\Dispatchable;

final class UpdateAdminPasswordJob
{
    use Dispatchable;

    public function __construct(
        private readonly User $user,
        private readonly string $newPassword,
    ) {}

    public function handle(): void
    {
        User::query()
            ->where('id', $this->user->id)
            ->update(['password' => Hash::make($this->newPassword)]);
    }
}
