<?php

declare(strict_types=1);

namespace App\Actions\Category;

use App\Data\CategoryData;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Crea o actualiza una categoría del tenant.
 *
 * El slug se deriva del nombre y solo se recalcula cuando el nombre cambia: es
 * la llave con la que el catálogo público filtra, y renombrar sin tocar el
 * nombre rompería los enlaces que ya circulan.
 */
final class SaveCategoryAction
{
    public function execute(CategoryData $data, ?Category $category = null): Category
    {
        $category ??= new Category(['display_order' => $this->nextDisplayOrder()]);

        $attributes = [
            'name' => $data->name,
            'description' => $data->description,
            'icon' => $data->icon,
            'is_active' => $data->isActive,
        ];

        if ($category->name !== $data->name) {
            $attributes['slug'] = $this->uniqueSlug($data->name, $category);
        }

        $category->fill($attributes)->save();

        $imagePath = $this->resolveImagePath($category, $data);

        if ($imagePath !== false) {
            $category->forceFill(['image_path' => $imagePath])->save();
        }

        return $category;
    }

    /**
     * El path nuevo, `null` para quitar la imagen, o `false` cuando el
     * formulario no tocó el archivo y hay que dejar el actual como está.
     */
    private function resolveImagePath(Category $category, CategoryData $data): string|null|false
    {
        if ($data->image instanceof UploadedFile) {
            $this->deleteIfPresent($category->image_path);

            return $data->image->store($this->directory($category), 'public') ?: null;
        }

        if ($data->removeImage) {
            $this->deleteIfPresent($category->image_path);

            return null;
        }

        return false;
    }

    private function directory(Category $category): string
    {
        return "tenants/{$category->tenant_id}/categories";
    }

    private function deleteIfPresent(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function nextDisplayOrder(): int
    {
        return (int) Category::query()->max('display_order') + 1;
    }

    private function uniqueSlug(string $name, Category $category): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while ($this->slugTaken($slug, $category)) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function slugTaken(string $slug, Category $category): bool
    {
        return Category::query()
            ->where('slug', $slug)
            ->when($category->exists, fn ($query) => $query->whereKeyNot($category->getKey()))
            ->exists();
    }
}
