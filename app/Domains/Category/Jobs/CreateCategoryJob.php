<?php

namespace App\Domains\Category\Jobs;

use App\Models\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\Category\Requests\CreateCategoryRequest;

final class CreateCategoryJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly CreateCategoryRequest $request,
    ) {}

    public function handle(): Category
    {
        return Category::query()->create([
            'name' => $this->request->string('name')->toString(),
            'description' => $this->request->filled('description')
                ? $this->request->string('description')->toString()
                : null,
        ]);
    }
}
