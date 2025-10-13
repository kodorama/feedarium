<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\News\Jobs\DeleteNewsJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

class DeleteNewsController extends Controller
{
    use DispatchesJobs;

    public function __invoke(int $id): JsonResponse
    {
        $this->dispatchSync(new DeleteNewsJob, $id);

        return response()->json(['success' => true]);
    }
}
