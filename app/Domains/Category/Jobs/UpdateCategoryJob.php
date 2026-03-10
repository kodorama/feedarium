<?php

namespace App\Domains\Category\Jobs;

use App\Models\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Domains\Category\Requests\UpdateCategoryRequest;

final class UpdateCategoryJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly UpdateCategoryRequest $request,
        private readonly int $id,
    ) {}

    public function handle(): Category
    {
        $category = Category::query()->findOrFail($this->id);

        $category->update([
            'name' => $this->request->string('name')->toString(),
            'description' => $this->request->filled('description')
                ? $this->request->string('description')->toString()
                : null,
        ]);

        return $category->fresh();
    }
}
