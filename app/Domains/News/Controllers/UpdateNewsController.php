<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\News\Jobs\UpdateNewsJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\News\Requests\UpdateNewsRequest;

final class UpdateNewsController extends Controller
{
    use DispatchesJobs;

    public function __invoke(UpdateNewsRequest $request, int $id): JsonResponse
    {
        $news = $this->dispatchSync(new UpdateNewsJob($request, $id));

        return response()->json(['news' => $news]);
    }
}
