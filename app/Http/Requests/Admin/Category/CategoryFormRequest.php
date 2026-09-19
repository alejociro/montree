<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Category;

use App\Enums\CategoryIcon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

abstract class CategoryFormRequest extends FormRequest
{
    private const MAX_IMAGE_KILOBYTES = 1024;

    public function authorize(): bool
    {
        return $this->user()?->can('categories.manage') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', Rule::enum(CategoryIcon::class)],
            'image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:'.self::MAX_IMAGE_KILOBYTES],
            'remove_image' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function name(): string
    {
        return trim((string) $this->validated('name'));
    }

    public function description(): ?string
    {
        $description = trim((string) ($this->validated('description') ?? ''));

        return $description === '' ? null : $description;
    }

    public function icon(): ?string
    {
        $icon = $this->validated('icon');

        return is_string($icon) && $icon !== '' ? $icon : null;
    }

    public function image(): ?UploadedFile
    {
        $image = $this->file('image');

        return $image instanceof UploadedFile ? $image : null;
    }

    public function removeImage(): bool
    {
        return $this->boolean('remove_image');
    }

    public function isActive(): bool
    {
        return $this->has('is_active') ? $this->boolean('is_active') : true;
    }
}
