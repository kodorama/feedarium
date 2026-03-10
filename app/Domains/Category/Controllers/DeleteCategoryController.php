<?php

namespace App\Domains\Category\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\Category\Jobs\DeleteCategoryJob;

final class DeleteCategoryController extends Controller
{
    use DispatchesJobs;

    public function __invoke(int $id): JsonResponse
    {
        $this->dispatchSync(new DeleteCategoryJob($id));

        return response()->json(['deleted' => true]);
    }
}
