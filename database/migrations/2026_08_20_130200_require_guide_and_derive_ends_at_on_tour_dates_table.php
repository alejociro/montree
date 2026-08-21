<?php

declare(strict_types=1);

use App\Enums\TourDateStatus;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Toda salida lleva guía (D7) y `ends_at` deja de ser un dato que el cliente
     * inventa: se deriva de `tours.duration_hours` (D9).
     *
     * Orden deliberado — primero se planifica todo en memoria y recién después se
     * escribe. Si a algún tenant le falta un guía, la migración aborta con el
     * listado **antes** del primer UPDATE: nunca deja la base a medias ni inventa
     * un guía que no existe.
     *
     * Los solapes preexistentes (dos salidas del mismo guía en días calendario
     * que se cruzan) se **reportan**, no se deshacen: son dato del negocio y
     * corregirlos en silencio sería peor que informarlos.
     */
    public function up(): void
    {
        $departures = $this->loadDepartures();

        // 1. Planificación completa en memoria. Aborta acá si falta un guía.
        $assignments = $this->planGuideAssignments($departures);

        // 2. Escrituras de datos, ya sin posibilidad de aborto por dato faltante.
        DB::transaction(function () use ($departures, $assignments): void {
            $this->applyDerivedEndsAt($departures);
            $this->applyAssignments($assignments);
        });

        // 3. La constraint, una vez que no queda ningún `guide_id` en null.
        // WHY: la FK vieja era `nullOnDelete()`, incompatible con NOT NULL —
        // MySQL rechaza la columna. Pasa a `restrictOnDelete()`: borrar a un guía
        // con salidas asignadas deja de ser posible en vez de vaciar la columna.
        Schema::table('tour_dates', function (Blueprint $table): void {
            $table->dropForeign(['guide_id']);
        });

        Schema::table('tour_dates', function (Blueprint $table): void {
            $table->unsignedBigInteger('guide_id')->nullable(false)->change();
        });

        Schema::table('tour_dates', function (Blueprint $table): void {
            $table->foreign('guide_id')->references('id')->on('users')->restrictOnDelete();
        });

        // 4. Informe de solapes sobre el estado final.
        $this->reportOverlaps($departures, $assignments);
    }

    public function down(): void
    {
        // `ends_at` no se restaura: el valor previo no era verdad (nadie lo derivaba
        // de la duración del tour), así que no hay nada mejor a lo que volver.
        Schema::table('tour_dates', function (Blueprint $table): void {
            $table->dropForeign(['guide_id']);
        });

        Schema::table('tour_dates', function (Blueprint $table): void {
            $table->unsignedBigInteger('guide_id')->nullable()->change();
        });

        Schema::table('tour_dates', function (Blueprint $table): void {
            $table->foreign('guide_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * @return array<int, array{id:int, tenant_id:int, tour_id:int, tour_name:string, guide_id:int|null, default_guide_id:int|null, status:string, starts_at:Carbon, ends_at:Carbon, days:array<int, string>}>
     */
    private function loadDepartures(): array
    {
        $rows = DB::table('tour_dates')
            ->join('tours', 'tours.id', '=', 'tour_dates.tour_id')
            ->orderBy('tour_dates.starts_at')
            ->orderBy('tour_dates.id')
            ->get([
                'tour_dates.id',
                'tour_dates.tenant_id',
                'tour_dates.tour_id',
                'tour_dates.guide_id',
                'tour_dates.starts_at',
                'tour_dates.status',
                'tours.name as tour_name',
                'tours.duration_hours',
                'tours.default_guide_id',
            ]);

        $departures = [];

        foreach ($rows as $row) {
            $startsAt = Carbon::parse((string) $row->starts_at);
            $endsAt = (clone $startsAt)->addHours(max(1, (int) $row->duration_hours));

            $departures[] = [
                'id' => (int) $row->id,
                'tenant_id' => (int) $row->tenant_id,
                'tour_id' => (int) $row->tour_id,
                'tour_name' => (string) $row->tour_name,
                'guide_id' => $row->guide_id === null ? null : (int) $row->guide_id,
                'default_guide_id' => $row->default_guide_id === null ? null : (int) $row->default_guide_id,
                'status' => (string) $row->status,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'days' => $this->calendarDays($startsAt, $endsAt),
            ];
        }

        return $departures;
    }

    /**
     * Días calendario ocupados, `[date(starts_at) … date(ends_at)]`: un tour de
     * 5 días bloquea los 5 aunque el último termine a las 9 de la mañana.
     *
     * @return array<int, string>
     */
    private function calendarDays(Carbon $startsAt, Carbon $endsAt): array
    {
        $days = [];
        $cursor = $startsAt->copy()->startOfDay();
        $last = $endsAt->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param  array<int, array<string, mixed>>  $departures
     * @return array<int, int> tour_date_id => guide_id
     */
    private function planGuideAssignments(array $departures): array
    {
        $pending = array_values(array_filter($departures, static fn (array $d): bool => $d['guide_id'] === null));

        if ($pending === []) {
            return [];
        }

        $guidesByTenant = [];
        $tenantsWithoutGuides = [];

        foreach (array_unique(array_column($pending, 'tenant_id')) as $tenantId) {
            $guides = $this->guideIdsForTenant((int) $tenantId);

            if ($guides === []) {
                $tenantsWithoutGuides[] = (int) $tenantId;

                continue;
            }

            $guidesByTenant[(int) $tenantId] = $guides;
        }

        if ($tenantsWithoutGuides !== []) {
            throw new RuntimeException($this->missingGuidesMessage($tenantsWithoutGuides));
        }

        $occupied = $this->occupancyMap($departures);
        $assignments = [];

        foreach ($pending as $departure) {
            $guides = $guidesByTenant[$departure['tenant_id']];
            $candidates = $departure['default_guide_id'] !== null
                ? array_values(array_unique(array_merge([$departure['default_guide_id']], $guides)))
                : $guides;

            $chosen = null;

            foreach ($candidates as $candidate) {
                if (! $this->collides($occupied[$candidate] ?? [], $departure['days'])) {
                    $chosen = $candidate;

                    break;
                }
            }

            // Todos ocupados: se asigna igual (la columna pasa a NOT NULL) y el
            // solape sale en el informe del paso 4.
            $chosen ??= $candidates[0];

            $assignments[$departure['id']] = $chosen;

            if ($this->occupies($departure['status'])) {
                foreach ($departure['days'] as $day) {
                    $occupied[$chosen][$day] = true;
                }
            }
        }

        return $assignments;
    }

    /**
     * Usuarios con rol `guide` en el tenant. `spatie/laravel-permission` con
     * `teams = true` guarda el tenant en `model_has_roles.tenant_id`.
     *
     * @return array<int, int>
     */
    private function guideIdsForTenant(int $tenantId): array
    {
        $pivot = (string) config('permission.table_names.model_has_roles');
        $roles = (string) config('permission.table_names.roles');
        $teamKey = (string) config('permission.column_names.team_foreign_key');
        $roleKey = (string) (config('permission.column_names.role_pivot_key') ?? 'role_id');
        $modelKey = (string) (config('permission.column_names.model_morph_key') ?? 'model_id');

        if (! Schema::hasTable($pivot) || ! Schema::hasTable($roles)) {
            return [];
        }

        return DB::table($pivot)
            ->join($roles, $roles.'.id', '=', $pivot.'.'.$roleKey)
            ->where($roles.'.name', 'guide')
            ->where($pivot.'.model_type', (new User)->getMorphClass())
            ->where($pivot.'.'.$teamKey, $tenantId)
            ->orderBy($pivot.'.'.$modelKey)
            ->pluck($pivot.'.'.$modelKey)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $tenantIds
     */
    private function missingGuidesMessage(array $tenantIds): string
    {
        $names = DB::table('tenants')
            ->whereIn('id', $tenantIds)
            ->pluck('name', 'id');

        $lines = array_map(
            static fn (int $id): string => sprintf('  - tenant #%d (%s)', $id, $names[$id] ?? 'sin nombre'),
            $tenantIds,
        );

        return implode("\n", [
            'No se puede exigir guía en toda salida: estos tenants tienen salidas sin guía y',
            'ningún usuario con rol `guide` a quien asignárselas:',
            ...$lines,
            '',
            'Asigná el rol `guide` a un usuario de cada uno y volvé a correr la migración.',
            'No se escribió nada en la base.',
        ]);
    }

    /**
     * Días ya ocupados por guía, contando solo las salidas que ocupan.
     *
     * @param  array<int, array<string, mixed>>  $departures
     * @return array<int, array<string, true>>
     */
    private function occupancyMap(array $departures): array
    {
        $occupied = [];

        foreach ($departures as $departure) {
            if ($departure['guide_id'] === null || ! $this->occupies($departure['status'])) {
                continue;
            }

            foreach ($departure['days'] as $day) {
                $occupied[$departure['guide_id']][$day] = true;
            }
        }

        return $occupied;
    }

    /**
     * @param  array<string, true>  $occupiedDays
     * @param  array<int, string>  $days
     */
    private function collides(array $occupiedDays, array $days): bool
    {
        foreach ($days as $day) {
            if (isset($occupiedDays[$day])) {
                return true;
            }
        }

        return false;
    }

    private function occupies(string $status): bool
    {
        return in_array($status, [TourDateStatus::Open->value, TourDateStatus::Closed->value], true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $departures
     */
    private function applyDerivedEndsAt(array $departures): void
    {
        foreach ($departures as $departure) {
            DB::table('tour_dates')
                ->where('id', $departure['id'])
                ->update(['ends_at' => $departure['ends_at']->toDateTimeString()]);
        }
    }

    /**
     * @param  array<int, int>  $assignments
     */
    private function applyAssignments(array $assignments): void
    {
        foreach ($assignments as $tourDateId => $guideId) {
            DB::table('tour_dates')->where('id', $tourDateId)->update(['guide_id' => $guideId]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $departures
     * @param  array<int, int>  $assignments
     */
    private function reportOverlaps(array $departures, array $assignments): void
    {
        $byGuide = [];

        foreach ($departures as $departure) {
            if (! $this->occupies($departure['status'])) {
                continue;
            }

            $guideId = $assignments[$departure['id']] ?? $departure['guide_id'];

            if ($guideId === null) {
                continue;
            }

            $byGuide[$guideId][] = $departure;
        }

        $overlaps = [];

        foreach ($byGuide as $guideId => $guideDepartures) {
            $seen = [];

            foreach ($guideDepartures as $departure) {
                foreach ($departure['days'] as $day) {
                    if (isset($seen[$day])) {
                        $overlaps[] = sprintf(
                            '  - guía #%d, %s: salida #%d (%s) contra salida #%d (%s)',
                            $guideId,
                            $day,
                            $seen[$day]['id'],
                            $seen[$day]['tour_name'],
                            $departure['id'],
                            $departure['tour_name'],
                        );

                        continue 2;
                    }
                }

                foreach ($departure['days'] as $day) {
                    $seen[$day] = $departure;
                }
            }
        }

        if ($overlaps === []) {
            return;
        }

        $this->write(implode("\n", [
            '',
            sprintf('Aviso: %d solape(s) de guía en el dato previo. No se modifican;', count($overlaps)),
            'la regla nueva los rechazaría al crear o editar la salida:',
            ...$overlaps,
            '',
        ]));
    }

    private function write(string $message): void
    {
        if (defined('STDOUT')) {
            fwrite(STDOUT, $message.PHP_EOL);
        }
    }
};
