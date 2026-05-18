<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Team;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

final class InviteMemberRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'role' => ['required', 'in:'.implode(',', [UserRole::Admin->value, UserRole::Operator->value, UserRole::Guide->value])],
        ];
    }
}
