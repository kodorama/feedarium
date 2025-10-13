<?php

namespace App\Domains\User\Controllers;

use Illuminate\Http\Response;
use App\Domains\User\Jobs\DeleteUserJob;

class DeleteUserController
{
    public function __invoke(int $id): Response
    {
        DeleteUserJob::dispatchSync($id);

        return response()->noContent();
    }
}
