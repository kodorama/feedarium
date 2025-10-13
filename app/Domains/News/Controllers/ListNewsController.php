<?php

namespace App\Domains\News\Controllers;

use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ListNewsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['news' => News::all()]);
    }
}
