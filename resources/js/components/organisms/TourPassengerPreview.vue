<script setup lang="ts">
import { ArrowRight, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';
import InitialsAvatar from '@/components/atoms/InitialsAvatar.vue';
import PaymentStatusChip from '@/components/molecules/PaymentStatusChip.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { Passenger } from '@/types/passenger';

/**
 * Avance de la planilla dentro de la edición del tour.
 *
 * WHY no la planilla entera aquí: la edición es la pantalla donde se cambia el
 * tour, no donde se gestiona la operación de una salida. Meter la tabla
 * completa —filtros, paginación, exportación— empujaba el formulario fuera de
 * la vista y duplicaba lo que ya vive en el detalle. Se muestran las primeras
 * personas para confirmar de un vistazo que hay reservas, y el trabajo de
 * verdad se hace en la lista completa.
 */
const PREVIEW_SIZE = 4;

const props = withDefaults(
    defineProps<{
        passengers: Passenger[];
        total: number;
        loading?: boolean;
    }>(),
    { loading: false },
);

const emit = defineEmits<{
    (e: 'open'): void;
}>();

const visible = computed(() => props.passengers.slice(0, PREVIEW_SIZE));

const remaining = computed(() =>
    Math.max(0, props.total - visible.value.length),
);

function documentLine(passenger: Passenger): string | null {
    const {
        document_type_abbreviation: abbreviation,
        document_number: number,
    } = passenger;

    if (number === null || number === '') {
        return null;
    }

    return abbreviation === null ? number : `${abbreviation} · ${number}`;
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <CardTitle>{{ $t('Pasajeros del tour') }}</CardTitle>
                    <CardDescription>
                        {{
                            $t(
                                'Consolidado de todas las salidas. Para gestionar una salida puntual usa el detalle.',
                            )
                        }}
                    </CardDescription>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="emit('open')"
                >
                    {{ $t('Abrir lista completa') }}
                    <ArrowRight class="size-4" />
                </Button>
            </div>
        </CardHeader>

        <CardContent>
            <div v-if="props.loading" class="space-y-2">
                <Skeleton
                    v-for="row in PREVIEW_SIZE"
                    :key="row"
                    class="h-12 w-full rounded-md"
                />
            </div>

            <div
                v-else-if="visible.length === 0"
                class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border p-8 text-center"
            >
                <UsersRound class="size-7 text-muted-foreground/40" />
                <p class="font-medium text-foreground">
                    {{ $t('Todavía no hay pasajeros en este tour.') }}
                </p>
                <p class="max-w-sm text-sm text-muted-foreground">
                    {{
                        $t(
                            'Aparecerán aquí en cuanto se confirme la primera reserva.',
                        )
                    }}
                </p>
            </div>

            <ul v-else>
                <li
                    v-for="(passenger, index) in visible"
                    :key="passenger.id ?? `pending-${index}`"
                    class="flex items-center gap-3 border-b border-brand-line-2 py-3 first:pt-0 last:border-0 last:pb-0"
                >
                    <InitialsAvatar
                        :name="passenger.full_name"
                        :pending="passenger.id === null"
                        size="sm"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-foreground">
                            {{ passenger.full_name }}
                        </p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{
                                documentLine(passenger) ??
                                $t('Datos pendientes')
                            }}
                        </p>
                    </div>
                    <PaymentStatusChip
                        v-if="passenger.payment"
                        :status="passenger.payment.status"
                        size="sm"
                        short
                    />
                </li>
            </ul>

            <p v-if="remaining > 0" class="pt-3 text-sm text-muted-foreground">
                {{
                    $tc(
                        'y :count pasajero más…|y :count pasajeros más…',
                        remaining,
                        { count: remaining },
                    )
                }}
            </p>
        </CardContent>
    </Card>
</template>
