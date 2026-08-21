<script setup lang="ts">
import { BellRing } from 'lucide-vue-next';
import type { TourPickupChangeImpact } from '@/types/tour';

/**
 * Regla 6: cambiar la parada de recogida de un tour con reservas vivas manda un
 * correo a cada pasajero. Se advierte ANTES de guardar —cuando todavía se puede
 * deshacer— y con la cuenta que emite el servidor (`pickup_change_impact`), que
 * es la misma con la que después decide a quién escribirle.
 *
 * `pending` es el matiz que importa: mientras nadie toque la recogida esto es
 * una advertencia; en cuanto la parada cambia en el formulario, es lo que va a
 * pasar al pulsar «Guardar».
 */
type Props = {
    impact: TourPickupChangeImpact;
    pending?: boolean;
};

const props = withDefaults(defineProps<Props>(), { pending: false });
</script>

<template>
    <div
        v-if="props.impact.passengers > 0"
        class="flex items-start gap-2.5 rounded-lg border border-brand-warn/30 bg-brand-warn-50 px-4 py-3"
        role="note"
    >
        <BellRing
            class="mt-0.5 size-4 shrink-0 text-brand-warn"
            aria-hidden="true"
        />
        <p class="text-[13px] text-brand-warn">
            <template v-if="props.pending">
                {{
                    $tc(
                        'Al guardar se notificará por correo a :count pasajero con reserva activa.|Al guardar se notificará por correo a :count pasajeros con reserva activa.',
                        props.impact.passengers,
                        { count: props.impact.passengers },
                    )
                }}
            </template>
            <template v-else>
                {{
                    $tc(
                        'Cambiar la parada de recogida notifica por correo a :count pasajero con reserva activa.|Cambiar la parada de recogida notifica por correo a :count pasajeros con reserva activa.',
                        props.impact.passengers,
                        { count: props.impact.passengers },
                    )
                }}
            </template>
        </p>
    </div>
</template>
