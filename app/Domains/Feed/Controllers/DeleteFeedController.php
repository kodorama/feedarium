<?php

namespace App\Domains\Feed\Controllers;

use App\Models\Feed;
use Illuminate\Http\Response;

class DeleteFeedController
{
    public function __invoke(int $id): Response
    {
        $feed = Feed::findOrFail($id);
        $feed->delete();

        return response()->noContent();
    }
}
