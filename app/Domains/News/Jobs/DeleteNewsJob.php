<?php

namespace App\Domains\News\Jobs;

use App\Models\News;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class DeleteNewsJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $id,
    ) {}

    public function handle(): void
    {
        News::query()->findOrFail($this->id)->delete();
    }
}
