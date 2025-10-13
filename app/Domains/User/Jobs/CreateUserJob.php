<?php

namespace App\Domains\User\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $data) {}

    public function handle(): User
    {
        $data = $this->data;
        $data['password'] = Hash::make($data['password']);

        return User::create($data);
    }
}
