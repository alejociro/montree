<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tour;

use App\Models\Route;

final class UpdateRouteRequest extends StoreRouteRequest
{
    public function authorize(): bool
    {
        $route = $this->route('route');

        return $route instanceof Route && ($this->user()?->can('update', $route->tour) ?? false);
    }

    /**
     * El nombre sigue sin poder quedar vacío, pero puede no venir: el diálogo
     * manda la ficha entera y otras superficies parchean campos sueltos.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'name' => ['sometimes', 'required', 'string', 'max:255']];
    }
}
