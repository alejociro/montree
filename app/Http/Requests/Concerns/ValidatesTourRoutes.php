<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Rutas del catálogo de logística que un producto puede operar (spec §G).
 */
trait ValidatesTourRoutes
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function tourRouteRules(): array
    {
        return [
            'routes' => ['sometimes', 'array', 'max:20'],
            'routes.*.id' => ['required', 'integer', 'distinct', Rule::exists('routes', 'id')->where('tenant_id', $this->tenantId())],
            'routes.*.is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Una sola predeterminada por producto: dos marcas son un empate que el
     * servidor no puede desempatar por su cuenta.
     */
    protected function validateSingleDefaultRoute(Validator $validator): void
    {
        $routes = $this->input('routes');

        if (! is_array($routes)) {
            return;
        }

        $defaults = array_filter(
            $routes,
            fn (mixed $route) => is_array($route) && filter_var($route['is_default'] ?? false, FILTER_VALIDATE_BOOL),
        );

        if (count($defaults) > 1) {
            $validator->errors()->add('routes', __('Solo una ruta puede ser la predeterminada del producto.'));
        }
    }
}
