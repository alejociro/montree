<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Models\TourDate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rehabilitar es el inverso de inhabilitar, así que pide el mismo permiso:
 * quien puede sacar una salida del catálogo puede devolverla.
 */
final class RestoreTourDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tourDate = $this->route('tourDate');

        return $tourDate instanceof TourDate && ($this->user()?->can('cancel', $tourDate) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
