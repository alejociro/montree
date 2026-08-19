<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TourDate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTourDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TourDate::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'guide_id' => ['nullable', 'integer', $this->guideRule()],
            'route_id' => ['nullable', 'integer', Rule::exists('routes', 'id')->where('tenant_id', $this->tenantId())],
            'provider_id' => ['nullable', 'integer', Rule::exists('providers', 'id')->where('tenant_id', $this->tenantId())],
            'hotel_ids' => ['nullable', 'array'],
            'hotel_ids.*' => ['integer', 'distinct', Rule::exists('hotels', 'id')->where('tenant_id', $this->tenantId())],
        ];
    }

    protected function tenantId(): int
    {
        return Tenant::current()?->getKey() ?? 0;
    }

    protected function guideRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $tenant = Tenant::current();

            if ($tenant === null) {
                $fail(__('El guía seleccionado no es válido.'));

                return;
            }

            setPermissionsTeamId($tenant->getKey());

            $isGuideMember = $tenant->users()
                ->where('users.id', $value)
                ->wherePivot('status', TenantMembershipStatus::Active->value)
                ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Guide->value))
                ->exists();

            if (! $isGuideMember) {
                $fail(__('El guía seleccionado no es válido.'));
            }
        };
    }
}
