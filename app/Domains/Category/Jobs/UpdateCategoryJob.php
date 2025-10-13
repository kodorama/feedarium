<?php

namespace App\Domains\Category\Jobs;

use App\Models\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Requests\UpdateCategoryRequest;

class UpdateCategoryJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(UpdateCategoryRequest $request, int $id): Category
    {
        $category = Category::findOrFail($id);
        $category->update($request->validated());

        return $category;
    }
}
