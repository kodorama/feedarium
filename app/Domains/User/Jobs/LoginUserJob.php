<?php

namespace App\Domains\User\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class LoginUserJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected array $credentials) {}

    public function handle(): ?string
    {
        if (! auth()->attempt($this->credentials)) {
            return null;
        }
        /** @var User $user */
        $user = auth()->user();

        return $user->createToken('api')->plainTextToken;
    }
}
