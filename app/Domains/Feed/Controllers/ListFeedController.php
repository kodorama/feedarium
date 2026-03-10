<?php

namespace App\Domains\Feed\Controllers;

use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ListFeedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $feeds = Feed::query()
            ->with('category')
            ->latest()
            ->get();

        return response()->json(['feeds' => $feeds]);
    }
}
