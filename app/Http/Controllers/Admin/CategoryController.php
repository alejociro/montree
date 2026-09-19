<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Category\DeleteCategoryAction;
use App\Actions\Category\SaveCategoryAction;
use App\Data\CategoryData;
use App\Exceptions\CategoryInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\StoreCategoryRequest;
use App\Http\Requests\Admin\Category\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;

final class CategoryController extends Controller
{
    public function store(StoreCategoryRequest $request, SaveCategoryAction $saveCategory): RedirectResponse
    {
        $saveCategory->execute(CategoryData::fromRequest($request));

        return back()->with('success', __('Categoría creada.'));
    }

    public function update(UpdateCategoryRequest $request, Category $category, SaveCategoryAction $saveCategory): RedirectResponse
    {
        $saveCategory->execute(CategoryData::fromRequest($request), $category);

        return back()->with('success', __('Categoría actualizada.'));
    }

    public function destroy(Category $category, DeleteCategoryAction $deleteCategory): RedirectResponse
    {
        try {
            $deleteCategory->execute($category);
        } catch (CategoryInUseException $inUse) {
            return back()->withErrors(['category' => $inUse->getMessage()]);
        }

        return back()->with('success', __('Categoría eliminada.'));
    }
}
