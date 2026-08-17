<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Notifications\Auth\TenantAwareResetPassword;
use App\Notifications\Auth\TenantAwareVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword as DefaultResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail as DefaultVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'avatar_path', 'phone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_set_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Whether the user still needs to define their own password for the first time
     * (e.g. accounts auto-created during guest booking).
     */
    public function mustSetPassword(): bool
    {
        return $this->password_set_at === null;
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->using(TenantUser::class)
            ->withPivot(['status', 'invited_at', 'joined_at', 'suspended_at'])
            ->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Resolve the membership pivot linking this user to the given tenant.
     */
    public function membershipFor(Tenant $tenant): ?TenantUser
    {
        /** @var TenantUser|null $membership */
        $membership = TenantUser::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $this->getKey())
            ->first();

        return $membership;
    }

    public function isActiveMemberOf(Tenant $tenant): bool
    {
        return $this->membershipFor($tenant)?->status === TenantMembershipStatus::Active;
    }

    /**
     * WHY: `isActiveMemberOf` no sirve para autorizar la gestión del equipo — reactivar a
     * alguien exige justamente que hoy NO esté activo. Lo que se valida ahí es pertenencia.
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->membershipFor($tenant) !== null;
    }

    /**
     * WHY: super_admin role lives on the sentinel team_id=0 (see RolesAndPermissionsSeeder
     * + multi-tenancy.md §9.3), so resolving it must switch the permission team first.
     */
    public function isSuperAdmin(): bool
    {
        $this->loadRolesForTeam(0);

        return $this->hasRole(UserRole::SuperAdmin->value);
    }

    /**
     * Scope spatie/permission role resolution to a tenant team and reset the cached
     * roles relation so the next role check reads the correct team.
     */
    public function loadRolesForTeam(int|string|null $teamId): void
    {
        setPermissionsTeamId($teamId);
        $this->unsetRelation('roles');
    }

    public function sendEmailVerificationNotification(): void
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            $this->notify(new DefaultVerifyEmail);

            return;
        }

        $this->notify(TenantAwareVerifyEmail::fromTenant($tenant));
    }

    public function sendPasswordResetNotification($token): void
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            $this->notify(new DefaultResetPassword($token));

            return;
        }

        $this->notify(TenantAwareResetPassword::fromTenant($token, $tenant));
    }
}
