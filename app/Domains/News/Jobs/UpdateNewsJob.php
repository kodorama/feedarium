<?php

namespace App\Domains\News\Jobs;

use App\Models\News;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\News\Requests\UpdateNewsRequest;

class UpdateNewsJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(UpdateNewsRequest $request, int $id): News
    {
        $news = News::findOrFail($id);
        $news->update($request->validated());

        return $news;
    }
}
