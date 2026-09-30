<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Building2, MapPin, Pencil, Truck, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
import { edit as editDeparture } from '@/actions/App/Http/Controllers/Admin/DepartureFormPagesController';
import InitialsAvatar from '@/components/atoms/InitialsAvatar.vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useModules } from '@/composables/useModules';
import { useTenantCurrency } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type {
    TourDateGlobalAdmin,
    TourDateDisplayStatus,
} from '@/types/logistics';

/**
 * Panel lateral con TODO lo de una salida.
 *
 * WHY: la tabla se quedó con seis columnas —tour, fecha, precio, guía,
 * ocupación y acciones— porque una tabla de once columnas no se lee. Lo que se
 * quitó de allí (condiciones logísticas, notas, precio base contra
 * sobreescrito, ventana de fechas) no se perdió: vive acá, a un clic de
 * «Ver detalle».
 */
const { t } = useTranslations();
const currency = useTenantCurrency();
// WHY (T3): con el módulo apagado el panel no muestra proveedor ni hotel aunque
// la salida los tenga guardados de antes de apagarlo.
const { isModuleEnabled } = useModules();

type Props = {
    open: boolean;
    departure: TourDateGlobalAdmin | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const statusLabels: Record<TourDateDisplayStatus, string> = {
    open: t('Abierta'),
    full: t('Llena'),
    closed: t('Cerrada'),
    in_progress: t('En curso'),
    finished: t('Finalizada'),
    cancelled: t('Inhabilitada'),
};

const statusClasses: Record<TourDateDisplayStatus, string> = {
    open: 'bg-primary-soft text-primary-readable',
    full: 'bg-brand-ink text-brand-on-ink',
    closed: 'bg-muted text-muted-foreground',
    in_progress: 'bg-primary text-primary-foreground',
    finished: 'bg-muted text-muted-foreground',
    cancelled: 'bg-brand-drop-50 text-brand-drop',
};

const departure = computed(() => props.departure);

const conditions = computed(() => {
    const value = departure.value;

    if (value === null) {
        return [];
    }

    const items: { icon: typeof MapPin; label: string; name: string }[] = [];

    if (value.route) {
        items.push({
            icon: MapPin,
            label: t('Ruta'),
            name: value.route.name,
        });
    }

    if (!isModuleEnabled('logistics')) {
        return items;
    }

    if (value.provider) {
        items.push({
            icon: Truck,
            label: t('Proveedor'),
            name: value.provider.name,
        });
    }

    value.hotels.forEach((hotel) => {
        items.push({ icon: Building2, label: t('Hotel'), name: hotel.name });
    });

    return items;
});
</script>

<template>
    <Sheet :open="props.open" @update:open="emit('update:open', $event)">
        <SheetContent
            v-if="departure"
            side="right"
            class="w-full gap-0 overflow-y-auto sm:max-w-[470px]"
        >
            <SheetHeader class="gap-1.5">
                <MonoLabel>{{ departure.code }}</MonoLabel>
                <SheetTitle class="text-xl">
                    {{ departure.tour.name }}
                </SheetTitle>
                <SheetDescription>
                    {{ formatTourDate(departure.starts_at) }}
                </SheetDescription>
                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                    <span
                        class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-[11.5px] font-semibold"
                        :class="statusClasses[departure.display_status]"
                    >
                        {{ statusLabels[departure.display_status] }}
                    </span>
                    <!-- T7: cualquier bloque de "Contenido de la salida"
                         (itinerario, incluye, no incluye, qué llevar, punto
                         de encuentro) tiene un valor propio, distinto del
                         producto. -->
                    <span
                        v-if="departure.is_customized"
                        class="inline-flex w-fit items-center rounded-full bg-secondary-soft px-2.5 py-1 text-[11.5px] font-semibold text-secondary-readable"
                    >
                        {{ $t('Personalizada') }}
                    </span>
                </div>
            </SheetHeader>

            <div class="space-y-5 px-4 pb-6">
                <section class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-border p-3">
                        <MonoLabel>{{ $t('Ocupación') }}</MonoLabel>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ departure.booked_count }}/{{
                                departure.capacity
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                $t(':count cupos libres', {
                                    count: departure.available_seats,
                                })
                            }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-border p-3">
                        <MonoLabel>{{ $t('Precio') }}</MonoLabel>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{
                                formatCurrency(
                                    departure.effective_price,
                                    currency,
                                )
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                departure.price_override
                                    ? $t('precio propio de esta salida')
                                    : $t('precio base del tour')
                            }}
                        </p>
                    </div>
                </section>

                <section>
                    <MonoLabel>{{ $t('Ventana de fechas') }}</MonoLabel>
                    <dl class="mt-2 space-y-1.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">
                                {{ $t('Empieza') }}
                            </dt>
                            <dd class="text-right font-medium">
                                {{ formatTourDate(departure.starts_at) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">
                                {{ $t('Termina') }}
                            </dt>
                            <dd class="text-right font-medium">
                                {{
                                    departure.ends_at
                                        ? formatTourDate(departure.ends_at)
                                        : $t('Mismo día')
                                }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section>
                    <MonoLabel>{{ $t('Guía') }}</MonoLabel>
                    <div
                        v-if="departure.guide"
                        class="mt-2 flex items-center gap-2.5 rounded-xl border border-border p-3"
                    >
                        <InitialsAvatar :name="departure.guide.name" />
                        <div>
                            <p class="text-sm font-medium">
                                {{ departure.guide.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ $t('asignado a esta salida') }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-else
                        class="mt-2 flex items-center gap-2 rounded-xl bg-brand-warn-50 p-3 text-sm text-brand-warn"
                    >
                        <UserRound class="size-4" />
                        {{ $t('Guía por asignar.') }}
                    </p>
                </section>

                <section>
                    <MonoLabel>{{ $t('Logística') }}</MonoLabel>
                    <ul v-if="conditions.length > 0" class="mt-2 space-y-1.5">
                        <li
                            v-for="(item, index) in conditions"
                            :key="index"
                            class="flex items-center gap-2.5 rounded-xl border border-border px-3 py-2.5"
                        >
                            <component
                                :is="item.icon"
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="min-w-0 flex-1 text-sm font-medium">
                                {{ item.name }}
                            </span>
                            <MonoLabel as="span">{{ item.label }}</MonoLabel>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        {{ $t('Sin ruta, proveedor ni hotel asociados.') }}
                    </p>
                </section>

                <section v-if="departure.notes">
                    <MonoLabel>{{ $t('Notas') }}</MonoLabel>
                    <p
                        class="mt-2 rounded-xl border border-border p-3 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ departure.notes }}
                    </p>
                </section>
            </div>

            <SheetFooter>
                <Button
                    v-if="departure.display_status !== 'finished'"
                    @click="
                        router.visit(
                            editDeparture.url({ tourDate: departure.id }),
                        )
                    "
                >
                    <Pencil class="size-4" />
                    {{ $t('Editar salida') }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
