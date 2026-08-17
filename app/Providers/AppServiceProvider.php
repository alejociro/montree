<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use App\Observers\TenantConfigurationObserver;
use App\Observers\TenantObserver;
use App\Policies\BookingPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\SuperAdminTenantPolicy;
use App\Policies\TenantPolicy;
use App\Policies\TourDatePolicy;
use App\Policies\TourPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureObservers();
        $this->configurePolicies();
        $this->configureGates();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureObservers(): void
    {
        Tenant::observe(TenantObserver::class);
        TenantConfiguration::observe(TenantConfigurationObserver::class);
    }

    protected function configurePolicies(): void
    {
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Tour::class, TourPolicy::class);
        Gate::policy(TourDate::class, TourDatePolicy::class);

        Gate::define('manage-platform-tenant', [SuperAdminTenantPolicy::class, 'manage']);
    }

    protected function configureGates(): void
    {
        Gate::before(function (User $user): ?bool {
            // WHY: isSuperAdmin() switches the spatie team scope to the sentinel 0 and
            // leaves it there; restoring it keeps every later permission check on the
            // tenant the request resolved. Returning null (not false) delegates to
            // spatie's own gate so non-super_admin users are evaluated by permission.
            $teamId = getPermissionsTeamId();
            $isSuperAdmin = $user->isSuperAdmin();
            $user->loadRolesForTeam($teamId);

            return $isSuperAdmin ? true : null;
        });
    }
}
