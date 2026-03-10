<?php

namespace App\Domains\Feed\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Domains\Feed\Jobs\DeleteFeedJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

final class DeleteFeedController extends Controller
{
    use DispatchesJobs;

    public function __invoke(int $id): Response
    {
        $this->dispatch(new DeleteFeedJob($id));

        return response()->noContent();
    }
}
