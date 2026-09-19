<?php

declare(strict_types=1);

namespace App\Data;

use App\Http\Requests\Admin\Category\StoreCategoryRequest;
use App\Http\Requests\Admin\Category\UpdateCategoryRequest;
use Illuminate\Http\UploadedFile;

final readonly class CategoryData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $icon,
        public ?UploadedFile $image,
        public bool $removeImage,
        public bool $isActive,
    ) {}

    public static function fromRequest(StoreCategoryRequest|UpdateCategoryRequest $request): self
    {
        return new self(
            name: $request->name(),
            description: $request->description(),
            icon: $request->icon(),
            image: $request->image(),
            removeImage: $request->removeImage(),
            isActive: $request->isActive(),
        );
    }
}
