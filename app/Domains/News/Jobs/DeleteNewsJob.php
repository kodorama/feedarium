<?php

namespace App\Domains\News\Jobs;

use App\Models\News;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DeleteNewsJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(int $id): void
    {
        News::findOrFail($id)->delete();
    }
}
