<?php

namespace App\Domains\News\Controllers;

use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ShowNewsBodyController extends Controller
{
    public function __invoke(int $id): JsonResponse
    {
        $news = News::query()->select(['id', 'full_body'])->findOrFail($id);

        return response()->json(['full_body' => $news->full_body]);
    }
}
