<?php

namespace App\Domains\News\Jobs;

use App\Models\News;
use App\Domains\News\Requests\CreateNewsRequest;

class CreateNewsJob
{
    public function handle(CreateNewsRequest $request): News
    {
        return News::query()->create($request->validated());
    }
}
