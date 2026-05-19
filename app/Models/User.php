<?php

declare(strict_types=1);

namespace App\Models;

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
            'two_factor_confirmed_at' => 'datetime',
        ];
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
