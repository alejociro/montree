<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Provider;
use App\Models\Route;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogos mínimos (`id` + `name`) que alimentan el diálogo de salida.
 *
 * Son listas cortas de la agencia y viajan completas como props: el diálogo ya
 * no puede pedirlas por su cuenta y repetir la consulta por cada apertura.
 */
final class DepartureOptionsQuery
{
    /**
     * @return array{guides: array<int, array{id: int, name: string}>, providers: array<int, array{id: int, name: string}>, hotels: array<int, array{id: int, name: string}>}
     */
    public function all(): array
    {
        return [
            'guides' => $this->guides(),
            'providers' => $this->refs(Provider::query()),
            'hotels' => $this->refs(Hotel::query()),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function routes(): array
    {
        return $this->refs(Route::query());
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function guides(): array
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            return [];
        }

        setPermissionsTeamId($tenant->getKey());

        return $tenant->users()
            ->wherePivot('status', TenantMembershipStatus::Active->value)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', UserRole::Guide->value))
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->map(fn (Model $user) => ['id' => (int) $user->getKey(), 'name' => (string) $user->getAttribute('name')])
            ->all();
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return array<int, array{id: int, name: string}>
     */
    private function refs(Builder $query): array
    {
        return $query
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Model $record) => ['id' => (int) $record->getKey(), 'name' => (string) $record->getAttribute('name')])
            ->all();
    }
}
