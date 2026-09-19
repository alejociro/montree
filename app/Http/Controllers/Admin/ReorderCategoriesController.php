<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Category\ReorderCategoriesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\ReorderCategoriesRequest;
use Illuminate\Http\RedirectResponse;

final class ReorderCategoriesController extends Controller
{
    public function __invoke(ReorderCategoriesRequest $request, ReorderCategoriesAction $reorderCategories): RedirectResponse
    {
        $reorderCategories->execute($request->ids());

        return back()->with('success', __('Orden actualizado.'));
    }
}
