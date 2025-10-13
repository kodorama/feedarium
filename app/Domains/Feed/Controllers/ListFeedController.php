<?php

namespace App\Domains\Feed\Controllers;

use App\Models\Feed;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;

class ListFeedController
{
    public function __invoke(Request $request): Response
    {
        $feeds = Feed::query()->latest()->get();

        return Inertia::render('Feed/Index', [
            'feeds' => $feeds,
        ]);
    }
}
