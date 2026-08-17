<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Team;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateMemberRoleRequest extends FormRequest
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
            'role' => ['required', 'in:'.implode(',', array_map(fn (UserRole $role): string => $role->value, [UserRole::Admin, UserRole::Sales, UserRole::Operator, UserRole::Guide, UserRole::Customer]))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Elige un rol.',
            'role.in' => 'Ese rol no es válido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['role' => 'rol'];
    }
}
