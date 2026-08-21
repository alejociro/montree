<script setup lang="ts">
import { AlertTriangle, Check, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency } from '@/lib/format';
import type { PassengerManifestSummary } from '@/types/passenger';

/**
 * A quién le toca un cambio en este tour: pasajeros con reserva, salidas
 * abiertas y saldos pendientes.
 *
 * WHY: editar el precio, la duración o una parada no es un acto privado — hay
 * gente que ya compró. La tarjeta lo dice antes de guardar, con las cifras que
 * el backend ya calcula (`meta.summary` de la planilla y las salidas del tour).
 * Sin resumen no se pinta el bloque de pasajeros: un `0` prestado se leería
 * como «no hay nadie».
 */
type Props = {
    /** `null` mientras carga o cuando la planilla no está permitida. */
    summary: PassengerManifestSummary | null;
    openDepartures: number;
    loading?: boolean;
    /** Sin permiso de planilla no se ofrece el enlace a los pasajeros. */
    canViewPassengers?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    canViewPassengers: false,
});

const emit = defineEmits<{
    (e: 'view-passengers'): void;
}>();

const withDue = computed<number>(() => props.summary?.with_due ?? 0);

const dueAmount = computed<string | null>(() =>
    props.summary === null
        ? null
        : formatCurrency(
              props.summary.total_due_amount,
              props.summary.currency,
          ),
);
</script>

<template>
    <Card>
        <CardContent class="space-y-3">
            <MonoLabel>{{ $t('Impacto de los cambios') }}</MonoLabel>

            <div v-if="props.loading" class="space-y-2">
                <Skeleton class="h-5 w-full" />
                <Skeleton class="h-5 w-4/5" />
            </div>

            <ul v-else class="space-y-2 text-[13px]">
                <li
                    v-if="props.summary"
                    class="flex items-start gap-2 rounded-lg bg-brand-green-50 px-2.5 py-2"
                >
                    <Users class="mt-0.5 size-4 shrink-0 text-foreground/70" />
                    <span>
                        {{
                            $tc(
                                ':count pasajero con reserva activa|:count pasajeros con reserva activa',
                                props.summary.total_passengers,
                                { count: props.summary.total_passengers },
                            )
                        }}
                    </span>
                </li>

                <li class="flex items-start gap-2 px-2.5 py-2">
                    <Check class="mt-0.5 size-4 shrink-0 text-foreground/70" />
                    <span>
                        {{
                            $tc(
                                ':count salida abierta a la venta|:count salidas abiertas a la venta',
                                props.openDepartures,
                                { count: props.openDepartures },
                            )
                        }}
                    </span>
                </li>

                <li
                    v-if="props.summary && withDue > 0"
                    class="flex items-start gap-2 rounded-lg bg-brand-drop-50 px-2.5 py-2"
                >
                    <AlertTriangle
                        class="mt-0.5 size-4 shrink-0 text-brand-warn"
                    />
                    <span>
                        {{
                            $tc(
                                ':count pasajero con saldo pendiente|:count pasajeros con saldo pendiente',
                                withDue,
                                { count: withDue },
                            )
                        }}
                        <span v-if="dueAmount" class="text-muted-foreground">
                            · {{ dueAmount }}
                        </span>
                    </span>
                </li>

                <li
                    v-if="props.summary === null"
                    class="px-2.5 py-2 text-muted-foreground"
                >
                    {{ $t('No hay datos de pasajeros para este tour.') }}
                </li>
            </ul>

            <Button
                v-if="props.canViewPassengers"
                type="button"
                variant="outline"
                size="sm"
                class="w-full"
                @click="emit('view-passengers')"
            >
                {{ $t('Ver pasajeros') }}
            </Button>
        </CardContent>
    </Card>
</template>
