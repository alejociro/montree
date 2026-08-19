<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Role;

use App\Models\Tenant;
use App\Services\Rbac\PermissionCatalog;
use App\Services\Rbac\TenantRoleCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('team.role.update') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'permissions' => ['sometimes', 'required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(app(PermissionCatalog::class)->slugs())],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $name = trim((string) $this->string('name'));

                if ($name === '' || $validator->errors()->has('name')) {
                    return;
                }

                if (TenantRoleCatalog::isReservedName($name)) {
                    $validator->errors()->add('name', __('Ese nombre pertenece a un rol base. Elegí otro.'));

                    return;
                }

                if ($this->nameTaken($name)) {
                    $validator->errors()->add('name', __('Ya tenés un rol con ese nombre.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Ponle un nombre al rol.'),
            'name.max' => __('Máximo :max caracteres.'),
            'permissions.required' => __('Elige al menos un permiso.'),
            'permissions.min' => __('Elige al menos un permiso.'),
            'permissions.*.in' => __('Ese permiso no existe.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'permissions' => 'permisos'];
    }

    private function nameTaken(string $name): bool
    {
        $tenant = Tenant::current();
        $role = $this->route('role');

        if ($tenant === null || ! $role instanceof Role) {
            return false;
        }

        return app(TenantRoleCatalog::class)->nameTaken($tenant, $name, (int) $role->getKey());
    }
}
