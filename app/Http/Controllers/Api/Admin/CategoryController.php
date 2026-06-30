<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    /**
     * The full taxonomy as a tree — top-level categories with their children
     * nested one level, alphabetical. Not paginated: a category tree is small
     * and doesn't split cleanly across pages. Same shape the storefront uses.
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

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'name' => $request->validated('name'),
            'slug' => $this->uniqueSlug($request->validated('name')),
            'parent_id' => $request->validated('parent_id'),
        ]);

        return CategoryResource::make($category->load('children'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update([
            'name' => $request->validated('name'),
            // Rename follows through to the slug so storefront URLs stay readable.
            'slug' => $this->uniqueSlug($request->validated('name'), $category->id),
            'parent_id' => $request->validated('parent_id'),
        ]);

        return CategoryResource::make($category->load('children'));
    }

    /**
     * Delete only an empty leaf. The products FK and the parent FK both
     * `nullOnDelete`, so deleting a category in use would silently uncategorise
     * its products or reparent its children to the root — a quiet taxonomy
     * mutation. We refuse it (422) and make the admin reassign first.
     */
    public function destroy(Category $category): Response
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that still has products. Reassign them first.',
            ]);
        }

        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that has sub-categories. Remove or move them first.',
            ]);
        }

        $category->delete();

        return response()->noContent();
    }

    /**
     * A URL-safe slug derived from the name, guaranteed unique against the
     * `categories.slug` index. Two distinct names can slugify to the same base
     * (e.g. "Silk" and "Silk!") — the suffix keeps the insert from blowing up.
     */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (
            Category::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
