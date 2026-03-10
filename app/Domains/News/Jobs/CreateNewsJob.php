<?php

namespace App\Domains\News\Jobs;

use App\Models\News;
use App\Domains\News\Requests\CreateNewsRequest;

final class CreateNewsJob
{
    public function __construct(
        private readonly CreateNewsRequest $request,
    ) {}

    public function handle(): News
    {
        return News::query()->create([
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
    }
}
