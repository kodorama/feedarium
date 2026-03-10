<?php

namespace App\Domains\Category\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ListCategoryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $categories = Category::query()->latest()->get();

        return response()->json(['categories' => $categories]);
    }
}
