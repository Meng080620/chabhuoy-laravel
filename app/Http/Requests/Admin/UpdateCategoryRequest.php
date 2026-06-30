<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        // A category can't be its own parent, nor a child of one of its own
        // descendants — either would create a cycle in the tree. The taxonomy is
        // shallow (one level of nesting), so the descendant set is its children.
        $forbiddenParents = [$category->id, ...$category->children()->pluck('id')->all()];

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('categories', 'name')->ignore($category->id),
            ],
            'parent_id' => [
                'nullable', 'integer',
                'exists:categories,id',
                Rule::notIn($forbiddenParents),
            ],
        ];
    }
}
