<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryCollection;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->getAdminPaginatedList($request->all());

        return ApiResponse::paginated($categories, CategoryCollection::make($categories->getCollection())->resolve(), 'Categories fetched successfully.');
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return ApiResponse::success(CategoryResource::make($category), 'Category created successfully.', 201);
    }

    public function show(Category $category): JsonResponse
    {
        $category->loadCount('services');

        return ApiResponse::success(CategoryResource::make($category), 'Category fetched successfully.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->categoryService->update($category, $request->validated());
        $category->loadCount('services');

        return ApiResponse::success(CategoryResource::make($category), 'Category updated successfully.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->delete($category);

        return ApiResponse::success((object) [], 'Category deleted successfully.');
    }
}
