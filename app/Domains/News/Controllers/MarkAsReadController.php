<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\News\Jobs\MarkAsReadJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

final class MarkAsReadController extends Controller
{
    use DispatchesJobs;

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $this->dispatchSync(new MarkAsReadJob($id));

        return response()->json(['message' => 'Article marked as read.']);
    }
}
