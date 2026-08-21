<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Geocoding;

use Illuminate\Foundation\Http\FormRequest;

final class GeocodeSearchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:3', 'max:160'],
        ];
    }

    public function term(): string
    {
        return (string) $this->validated('q');
    }
}
