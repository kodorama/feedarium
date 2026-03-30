<?php

namespace App\Domains\News\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Domains\News\Resources\NewsResource;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Domains\News\Jobs\ListSavedArticlesJob;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListSavedArticlesController extends Controller
{
    use DispatchesJobs;

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $paginator = $this->dispatchSync(new ListSavedArticlesJob(
            user: $request->user(),
            cursor: $request->string('cursor')->toString() ?: null,
        ));

        return NewsResource::collection($paginator);
    }
}
