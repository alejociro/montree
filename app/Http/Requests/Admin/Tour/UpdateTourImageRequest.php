<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tour;

use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTourImageRequest extends FormRequest
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
            'is_cover' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }
}
