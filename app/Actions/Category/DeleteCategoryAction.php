<?php

declare(strict_types=1);

namespace App\Actions\Category;

use App\Exceptions\CategoryInUseException;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;

final class DeleteCategoryAction
{
    private const MAX_LISTED_TOURS = 3;

    public function execute(Category $category): void
    {
        $count = $category->tours()->count();

        if ($count > 0) {
            throw CategoryInUseException::usedByTours($count, $this->tourNames($category));
        }

        if ($category->image_path !== null && $category->image_path !== '') {
            Storage::disk('public')->delete($category->image_path);
        }

        $category->delete();
    }

    /**
     * @return list<string>
     */
    private function tourNames(Category $category): array
    {
        return $category->tours()
            ->orderBy('name')
            ->limit(self::MAX_LISTED_TOURS)
            ->pluck('name')
            ->map(strval(...))
            ->all();
    }
}
