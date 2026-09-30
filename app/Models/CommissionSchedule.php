<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CommissionScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un esquema de comisión es una lista ORDENADA de rangos contiguos por valor
 * total de reserva (`bookings.total_amount`): `from` inclusivo, `to`
 * exclusivo, el último rango abierto (`to = null`). El porcentaje del rango
 * se aplica sobre TODO el valor de la reserva (no escalonado); `max_charge`
 * es un tope único del ESQUEMA (no del rango) que limita el cobro de
 * cualquier reserva sin importar en qué rango caiga.
 *
 * `tenant_id === null` es el esquema GLOBAL (uno solo; lo garantiza la
 * aplicación con `CommissionSchedule::global()`, no un índice: dos filas con
 * `tenant_id` nulo no chocan en SQLite ni en MySQL). Cualquier otra fila es el
 * esquema PROPIO de una agencia y reemplaza al global para ella.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $currency
 * @property array<int, array{from: string, to: string|null, rate: string}> $tiers
 * @property string|null $max_charge
 */
class CommissionSchedule extends Model
{
    /** @use HasFactory<CommissionScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'currency',
        'tiers',
        'max_charge',
    ];

    protected function casts(): array
    {
        return [
            'tiers' => 'array',
            'max_charge' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * El esquema global, creándolo con un único rango sin cobro si nunca se
     * configuró (no debería pasar: la migración inicial siembra uno).
     */
    public static function global(): self
    {
        // WHY: MySQL/SQLite no aplican el índice único a varios NULL, así que dos
        // primeras peticiones concurrentes podrían crear dos globales. Leer siempre
        // el más antiguo deja la resolución determinista aunque eso pase.
        $existing = self::query()->whereNull('tenant_id')->orderBy('id')->first();

        return $existing ?? self::query()->create([
            'tenant_id' => null,
            'currency' => 'COP',
            'tiers' => [
                ['from' => '0.00', 'to' => null, 'rate' => '0.00'],
            ],
            'max_charge' => null,
        ]);
    }

    public static function forTenantOnly(int $tenantId): ?self
    {
        return self::query()->where('tenant_id', $tenantId)->first();
    }
}
