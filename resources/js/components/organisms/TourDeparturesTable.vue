<script setup lang="ts">
import {
    Ban,
    CalendarPlus,
    ChevronDown,
    Pencil,
    Trash2,
    UsersRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AssignGuideController from '@/actions/App/Http/Controllers/Api/V1/Admin/AssignGuideController';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
import CountTabs from '@/components/molecules/CountTabs.vue';
import type { CountTab } from '@/components/molecules/CountTabs.vue';
import GuideSelect from '@/components/molecules/GuideSelect.vue';
import OccupancyBar from '@/components/molecules/OccupancyBar.vue';
import TourDateStatusBadge from '@/components/molecules/TourDateStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type { DepartureRange } from '@/types/guide-availability';
import type { LogisticsRef, TourDateAdmin } from '@/types/logistics';

const { t } = useTranslations();

/**
 * Las salidas del tour dentro de su pantalla de edición: cuándo, cuánta
 * ocupación, qué guía y en qué estado.
 *
 * WHY (D7): no existe «Falta guía». Toda salida tiene guía desde la Fase 5, así
 * que la celda muestra el nombre y el cambio se hace con el `GuideSelect` de la
 * disponibilidad — que se monta SOLO en la fila que se está tocando: montarlo
 * en las veinte filas dispararía veinte consultas de agenda, una por rango.
 */
type Props = {
    departures: TourDateAdmin[];
    currency: string;
    durationHours: number | null;
    loading?: boolean;
    error?: boolean;
    /** Se ofrecen mientras la agenda no responde; el servidor sigue validando. */
    fallbackGuides?: LogisticsRef[];
    canViewPassengers?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    error: false,
    fallbackGuides: () => [],
    canViewPassengers: false,
});

const emit = defineEmits<{
    (e: 'create'): void;
    (e: 'edit', departure: TourDateAdmin): void;
    (e: 'cancel', departure: TourDateAdmin): void;
    (e: 'remove', departure: TourDateAdmin): void;
    (e: 'passengers', departure: TourDateAdmin): void;
    (e: 'assigned'): void;
    (e: 'retry'): void;
}>();

const api = useApi();

type Scope = 'upcoming' | 'past' | 'cancelled';

const scope = ref<Scope>('upcoming');
const editingGuideFor = ref<number | null>(null);
const savingGuideFor = ref<number | null>(null);

const MS_PER_HOUR = 3_600_000;

function isUpcoming(departure: TourDateAdmin): boolean {
    return new Date(departure.starts_at).getTime() > Date.now();
}

const upcoming = computed(() =>
    props.departures.filter(
        (departure) =>
            departure.status !== 'cancelled' && isUpcoming(departure),
    ),
);

const past = computed(() =>
    props.departures.filter(
        (departure) =>
            departure.status !== 'cancelled' && !isUpcoming(departure),
    ),
);

const cancelled = computed(() =>
    props.departures.filter((departure) => departure.status === 'cancelled'),
);

const visible = computed<TourDateAdmin[]>(() => {
    if (scope.value === 'past') {
        return past.value;
    }

    if (scope.value === 'cancelled') {
        return cancelled.value;
    }

    return upcoming.value;
});

const scopes = computed<CountTab[]>(() => [
    { id: 'upcoming', label: t('Próximas'), count: upcoming.value.length },
    { id: 'past', label: t('Pasadas'), count: past.value.length },
    {
        id: 'cancelled',
        label: t('Inhabilitadas'),
        count: cancelled.value.length,
    },
]);

function toDateOnly(value: Date): string {
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${value.getFullYear()}-${month}-${day}`;
}

/** Los días de calendario que la salida le ocupa al guía (D9). */
function rangeOf(departure: TourDateAdmin): DepartureRange | null {
    const start = new Date(departure.starts_at);

    if (Number.isNaN(start.getTime())) {
        return null;
    }

    const end =
        departure.ends_at !== null
            ? new Date(departure.ends_at)
            : props.durationHours !== null
              ? new Date(start.getTime() + props.durationHours * MS_PER_HOUR)
              : start;

    return {
        from: toDateOnly(start),
        to: toDateOnly(Number.isNaN(end.getTime()) ? start : end),
    };
}

function assignGuide(departure: TourDateAdmin, guideId: number | null): void {
    if (guideId === null || guideId === departure.guide?.id) {
        editingGuideFor.value = null;

        return;
    }

    savingGuideFor.value = departure.id;

    // El endpoint responde solo `{id, guide_id}`, no la salida entera: el
    // nombre del guía y el estado recalculado los trae el recargado.
    void api.patch(
        AssignGuideController(departure.id).url,
        { guide_id: guideId },
        {
            onSuccess: () => {
                toast.success(t('Guía asignado.'));
                editingGuideFor.value = null;
                emit('assigned');
            },
            onError: (errors) => {
                toast.error(
                    errors.guide_id ??
                        errors._global ??
                        t('No se pudo asignar el guía.'),
                );
            },
            onFinish: () => {
                savingGuideFor.value = null;
            },
        },
    );
}

function priceLabel(departure: TourDateAdmin): string {
    return formatCurrency(departure.effective_price, props.currency);
}
</script>

<template>
    <!--
      Una sola tarjeta, como el resto del panel: antes el bloque flotaba sobre
      el fondo de la página y no se leía como una unidad.
    -->
    <section class="rounded-2xl border border-border bg-card p-4 md:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">
                    {{ $t('Salidas programadas') }}
                </h2>
                <p class="text-[13px] text-muted-foreground">
                    {{
                        $t(
                            'El guía se asigna en cada fecha: una salida sin guía no puede abrirse a la venta.',
                        )
                    }}
                </p>
            </div>
            <Button size="sm" @click="emit('create')">
                <CalendarPlus class="size-4" />
                {{ $t('Nueva salida') }}
            </Button>
        </div>

        <CountTabs
            class="mt-4"
            :tabs="scopes"
            :model-value="scope"
            :label="$t('Estado de las salidas')"
            @update:model-value="(value) => (scope = value as Scope)"
        />

        <div v-if="props.loading" class="mt-4 space-y-2">
            <Skeleton v-for="n in 3" :key="n" class="h-24 w-full rounded-xl" />
        </div>

        <div
            v-else-if="props.error"
            class="mt-4 rounded-xl border border-destructive/40 bg-destructive/5 p-6 text-center"
        >
            <p class="text-sm text-destructive">
                {{ $t('No se pudieron cargar las salidas.') }}
            </p>
            <Button
                variant="outline"
                size="sm"
                class="mt-3"
                @click="emit('retry')"
            >
                {{ $t('Reintentar') }}
            </Button>
        </div>

        <div
            v-else-if="visible.length === 0"
            class="mt-4 rounded-xl border border-dashed border-input p-8 text-center"
        >
            <CalendarPlus class="mx-auto size-8 text-muted-foreground/40" />
            <p class="mt-3 font-medium">
                {{
                    scope === 'upcoming'
                        ? $t('Sin salidas próximas')
                        : scope === 'past'
                          ? $t('Sin salidas pasadas')
                          : $t('Sin salidas inhabilitadas')
                }}
            </p>
            <p
                v-if="scope === 'upcoming'"
                class="mt-1 text-sm text-muted-foreground"
            >
                {{
                    $t(
                        'Crea la primera salida para que aparezca en el catálogo.',
                    )
                }}
            </p>
        </div>

        <ul v-else class="mt-2 divide-y divide-brand-line-2">
            <li
                v-for="departure in visible"
                :key="departure.id"
                class="flex flex-col gap-3 py-4 min-[1180px]:flex-row min-[1180px]:items-center min-[1180px]:justify-between"
            >
                <div class="min-w-0 space-y-1.5">
                    <p class="text-[15px] font-semibold capitalize">
                        {{ formatTourDate(departure.starts_at) }}
                    </p>
                    <p class="text-[13px] text-muted-foreground">
                        {{
                            $t(':booked/:capacity reservados · :price', {
                                booked: departure.booked_count,
                                capacity: departure.capacity,
                                price: priceLabel(departure),
                            })
                        }}
                    </p>
                    <OccupancyBar
                        class="max-w-[220px]"
                        size="sm"
                        hide-value
                        :occupied="departure.booked_count"
                        :capacity="departure.capacity"
                    />
                </div>

                <div
                    class="flex flex-wrap items-center gap-2 min-[1180px]:justify-end"
                >
                    <div class="min-w-[190px]">
                        <GuideSelect
                            v-if="editingGuideFor === departure.id"
                            :id="`departure-guide-${departure.id}`"
                            :model-value="departure.guide?.id ?? null"
                            :range="rangeOf(departure)"
                            :exclude-tour-date-id="departure.id"
                            :fallback-guides="props.fallbackGuides"
                            @update:model-value="
                                (value) => assignGuide(departure, value)
                            "
                        />
                        <!--
                          WHY (D9): el `GuideSelect` real consulta la agenda del
                          rango, así que se monta SOLO en la fila que se está
                          tocando. Este disparador se ve como el select que
                          reemplaza —borde, altura y flecha— para que no parezca
                          otro control.
                        -->
                        <button
                            v-else-if="departure.status !== 'cancelled'"
                            type="button"
                            class="flex h-9 w-full items-center gap-1.5 rounded-md border border-input px-3 text-[13px] transition hover:bg-primary-soft focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :disabled="savingGuideFor === departure.id"
                            :aria-label="$t('Cambiar el guía de esta salida')"
                            @click="editingGuideFor = departure.id"
                        >
                            <UsersRound
                                class="size-3.5 shrink-0 text-muted-foreground"
                            />
                            <span class="min-w-0 flex-1 truncate text-left">
                                {{
                                    $t('Guía: :name', {
                                        name:
                                            departure.guide?.name ??
                                            $t('sin asignar'),
                                    })
                                }}
                            </span>
                            <ChevronDown
                                class="size-3.5 shrink-0 text-muted-foreground"
                            />
                        </button>
                        <span
                            v-else
                            class="text-[13px] text-muted-foreground"
                            >{{ departure.guide?.name ?? '—' }}</span
                        >
                    </div>

                    <TourDateStatusBadge :status="departure.status" />

                    <Button
                        v-if="props.canViewPassengers"
                        variant="outline"
                        size="sm"
                        @click="emit('passengers', departure)"
                    >
                        {{ $t('Pasajeros') }}
                    </Button>

                    <!-- Tres iconos sueltos → un solo menú con etiquetas. -->
                    <ActionMenu
                        v-if="departure.status !== 'cancelled'"
                        variant="ghost"
                        :label="$t('Acciones de la salida')"
                    >
                        <DropdownMenuItem @select="emit('edit', departure)">
                            <Pencil class="size-4" />
                            {{ $t('Editar salida') }}
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="emit('cancel', departure)">
                            <Ban class="size-4" />
                            {{ $t('Inhabilitar') }}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            @select="emit('remove', departure)"
                        >
                            <Trash2 class="size-4" />
                            {{ $t('Eliminar') }}
                        </DropdownMenuItem>
                    </ActionMenu>
                </div>
            </li>
        </ul>
    </section>
</template>
