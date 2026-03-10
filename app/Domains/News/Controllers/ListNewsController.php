<?php

namespace App\Domains\News\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ListNewsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $news = News::query()
            ->with('feed')
            ->latest('published_at')
            ->get();

        return response()->json(['news' => $news]);
    }
}
