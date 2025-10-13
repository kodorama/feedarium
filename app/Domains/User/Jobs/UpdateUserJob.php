<?php

namespace App\Domains\User\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId, public array $data) {}

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);
        $user->update($this->data);
    }
}
