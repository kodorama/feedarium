<?php

namespace App\Domains\News\Jobs;

use App\Models\News;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\News\Requests\UpdateNewsRequest;

final class UpdateNewsJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly UpdateNewsRequest $request,
        private readonly int $id,
    ) {}

    public function handle(): News
    {
        $news = News::query()->findOrFail($this->id);

        $news->update([
            'feed_id' => $this->request->integer('feed_id'),
            'title' => $this->request->string('title')->toString(),
            'link' => $this->request->string('link')->toString(),
            'description' => $this->request->filled('description')
                ? $this->request->string('description')->toString()
                : null,
            'published_at' => $this->request->input('published_at'),
            'author' => $this->request->filled('author')
                ? $this->request->string('author')->toString()
                : null,
            'guid' => $this->request->filled('guid')
                ? $this->request->string('guid')->toString()
                : null,
        ]);

        return $news->fresh();
    }
}
