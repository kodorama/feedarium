<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Domains\News\Jobs\CreateNewsJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\News\Requests\CreateNewsRequest;

class CreateNewsController extends Controller
{
    use DispatchesJobs;

    public function __invoke(CreateNewsRequest $request): JsonResponse
    {
        $news = $this->dispatchSync(new CreateNewsJob, $request);

        return response()->json(['news' => $news], 201);
    }
}
