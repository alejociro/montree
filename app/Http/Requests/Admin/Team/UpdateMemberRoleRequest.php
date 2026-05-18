<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Team;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:'.implode(',', array_map(fn ($c) => $c->value, [UserRole::Admin, UserRole::Operator, UserRole::Guide, UserRole::Customer]))],
        ];
    }
}
