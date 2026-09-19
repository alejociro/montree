<?php

declare(strict_types=1);

namespace App\Actions\Category;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

final class ReorderCategoriesAction
{
    /**
     * @param  array<int, int>  $ids  en el orden en que quedan
     */
    public function execute(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $position => $id) {
                Category::query()->whereKey($id)->update(['display_order' => $position + 1]);
            }
        });
    }
}
