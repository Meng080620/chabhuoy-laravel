<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * The storefront category tree: top-level categories with their children
     * nested one level. Not paginated — a navigation taxonomy is small and a
     * tree doesn't split cleanly across pages.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * A single category (resolved by slug) with its immediate children.
     * Products live behind GET /products?category_id={id}, not here.
     */
    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category->load('children'));
    }
}
