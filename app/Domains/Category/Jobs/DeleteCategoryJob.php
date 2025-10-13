<?php

namespace App\Domains\Category\Jobs;

use App\Models\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeleteCategoryJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(int $id): void
    {
        Category::findOrFail($id)->delete();
    }
}
