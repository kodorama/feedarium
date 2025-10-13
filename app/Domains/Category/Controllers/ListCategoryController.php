<?php

namespace App\Domains\Category\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ListCategoryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['categories' => Category::all()]);
    }
}
