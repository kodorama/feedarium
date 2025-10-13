<?php

namespace App\Domains\Category\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\Category\Jobs\CreateCategoryJob;
use App\Domains\Category\Requests\CreateCategoryRequest;

class CreateCategoryController extends Controller
{
    use DispatchesJobs;

    public function __invoke(CreateCategoryRequest $request): JsonResponse
    {
        $category = $this->dispatchSync(new CreateCategoryJob, $request);

        return response()->json(['category' => $category], 201);
    }
}
