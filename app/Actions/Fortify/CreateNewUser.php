<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\AttachUserToTenant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private AttachUserToTenant $attachUserToTenant) {}

    /**
     * Validate and create a newly registered user, attaching it to the current tenant.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ], [
            'email.unique' => __('Las credenciales no son válidas.'),
        ])->validate();

        $tenant = Tenant::current();

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'email' => __('No se pudo determinar la agencia actual.'),
            ]);
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $this->attachUserToTenant->handle($user, $tenant, UserRole::Customer, 'registration');

        return $user;
    }
}
