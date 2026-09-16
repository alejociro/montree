<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final readonly class BrandingAssetsData
{
    public function __construct(
        public ?UploadedFile $logo = null,
        public ?UploadedFile $favicon = null,
        public ?UploadedFile $heroImage = null,
        public bool $removeLogo = false,
        public bool $removeHeroImage = false,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            logo: self::file($request, 'logo'),
            favicon: self::file($request, 'favicon'),
            heroImage: self::file($request, 'hero_image'),
            removeLogo: $request->boolean('remove_logo'),
            removeHeroImage: $request->boolean('remove_hero_image'),
        );
    }

    public function touchesAnything(): bool
    {
        return $this->logo !== null
            || $this->favicon !== null
            || $this->heroImage !== null
            || $this->removeLogo
            || $this->removeHeroImage;
    }

    private static function file(FormRequest $request, string $key): ?UploadedFile
    {
        $file = $request->file($key);

        return $file instanceof UploadedFile ? $file : null;
    }
}
