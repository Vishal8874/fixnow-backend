<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\CategoryCollection;
use App\Http\Resources\ServiceCollection;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->getPublicPaginatedList($request->all());

        return ApiResponse::paginated($categories, CategoryCollection::make($categories->getCollection())->resolve(), 'Categories fetched successfully.');
    }

    public function services(Request $request, Category $category): JsonResponse
    {
        $services = $this->categoryService->getPublicCategoryServices($category, $request->all());

        return ApiResponse::paginated($services, ServiceCollection::make($services->getCollection())->resolve(), 'Category services fetched successfully.');
    }
}
