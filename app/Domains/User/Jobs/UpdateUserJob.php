<?php

namespace App\Domains\User\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\User\Requests\UpdateUserRequest;

final class UpdateUserJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly UpdateUserRequest $request,
        private readonly int $id,
    ) {}

    public function handle(): User
    {
        $user = User::query()->findOrFail($this->id);

        $user->update([
            'name' => $this->request->string('name')->toString(),
            'email' => $this->request->string('email')->toString(),
        ]);

        return $user->fresh();
    }
}
