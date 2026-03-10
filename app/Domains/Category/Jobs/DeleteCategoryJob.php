<?php

namespace App\Domains\Category\Jobs;

use App\Models\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class DeleteCategoryJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $id,
    ) {}

    public function handle(): void
    {
        Category::query()->findOrFail($this->id)->delete();
    }
}
