<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tour;

use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;

class StoreTourImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tour = $this->route('tour');

        return $tour instanceof Tour && ($this->user()?->can('update', $tour) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_cover' => ['nullable', 'boolean'],
            'alt_text' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.max' => 'Image must not exceed 5MB.',
            'image.mimes' => 'Image must be a JPG, PNG, or WebP file.',
        ];
    }
}
