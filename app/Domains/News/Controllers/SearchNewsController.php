<?php

namespace App\Domains\News\Controllers;

use Illuminate\Routing\Controller;
use App\Domains\News\Jobs\SearchNewsJob;
use App\Domains\News\Resources\NewsResource;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\News\Requests\SearchNewsRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SearchNewsController extends Controller
{
    use DispatchesJobs;

    public function __invoke(SearchNewsRequest $request): AnonymousResourceCollection
    {
        $results = $this->dispatchSync(new SearchNewsJob($request));

        return NewsResource::collection($results);
    }
}
