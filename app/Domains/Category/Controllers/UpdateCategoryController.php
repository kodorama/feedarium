<?php

namespace App\Domains\Category\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\Category\Jobs\UpdateCategoryJob;
use App\Domains\Category\Requests\UpdateCategoryRequest;

class UpdateCategoryController extends Controller
{
    use DispatchesJobs;

    public function __invoke(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->dispatchSync(new UpdateCategoryJob, $request, $id);

        return response()->json(['category' => $category]);
    }
}
