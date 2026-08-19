<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Support\Locale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLocaleRequest extends FormRequest
{
    /**
     * WHY: el idioma es una preferencia de presentación de quien hace el request —
     * invitado incluido, porque el sitio público es la mayoría del tráfico. No hay
     * recurso ajeno que autorizar; lo que cierra el input es la regla `in:`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(Locale::supported())],
        ];
    }
}
