<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryIcon;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

final class CategoryPagesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Categories/Index', $this->props());
    }

    /**
     * @return array<string, mixed>
     */
    private function props(): array
    {
        return [
            'categories' => CategoryResource::collection(
                Category::query()
                    ->withCount('tours')
                    ->orderBy('display_order')
                    ->orderBy('name')
                    ->get()
            )->resolve(),
            'icons' => $this->icons(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function icons(): array
    {
        return array_map(
            static fn (CategoryIcon $icon): array => ['value' => $icon->value, 'label' => $icon->label()],
            CategoryIcon::cases(),
        );
    }
}
