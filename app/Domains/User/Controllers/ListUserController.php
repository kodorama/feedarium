<?php

namespace App\Domains\User\Controllers;

use Illuminate\Routing\Controller;
use App\Domains\User\Jobs\ListUserJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

class ListUserController extends Controller
{
    use DispatchesJobs;

    public function __invoke(): \Illuminate\Http\JsonResponse
    {
        $users = $this->dispatchSync(new ListUserJob);

        return response()->json(['users' => $users]);
    }
}
