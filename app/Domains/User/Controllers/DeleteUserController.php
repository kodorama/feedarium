<?php

namespace App\Domains\User\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Domains\User\Jobs\DeleteUserJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

final class DeleteUserController extends Controller
{
    use DispatchesJobs;

    public function __invoke(int $id): Response
    {
        $this->dispatchSync(new DeleteUserJob($id));

        return response()->noContent();
    }
}
