<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Ban,
    CheckCircle2,
    Eye,
    Pencil,
    Trash2,
    UserRoundCog,
} from 'lucide-vue-next';
import { show as tourShowPage } from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import InitialsAvatar from '@/components/atoms/InitialsAvatar.vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { useTenantCurrency } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import {
    formatCurrency,
    formatDayDistance,
    formatDayMonth,
    formatWeekdayTime,
} from '@/lib/format';
import type {
    DepartureBoardTotals,
    TourDateGlobalAdmin,
} from '@/types/logistics';

/**
 * La tabla del tablero de salidas: seis columnas y el pie con los totales del
 * corte que el usuario está viendo. No muta nada —cada acción sube al tablero,
 * que es quien tiene los formularios.
 */
const { t } = useTranslations();
const currency = useTenantCurrency();

type Props = {
    departures: TourDateGlobalAdmin[];
    totals: DepartureBoardTotals;
    /** Salida con una acción en vuelo: su menú queda deshabilitado. */
    busyId?: number | null;
};

const props = withDefaults(defineProps<Props>(), {
    busyId: null,
});

const emit = defineEmits<{
    detail: [value: TourDateGlobalAdmin];
    edit: [value: TourDateGlobalAdmin];
    cancel: [value: TourDateGlobalAdmin];
    restore: [value: TourDateGlobalAdmin];
    'assign-guide': [value: TourDateGlobalAdmin];
    remove: [value: TourDateGlobalAdmin];
}>();

function durationLabel(date: TourDateGlobalAdmin): string {
    if (date.ends_at === null) {
        return '';
    }

    const start = new Date(date.starts_at);
    const end = new Date(date.ends_at);
    const days =
        Math.round((end.getTime() - start.getTime()) / (24 * 60 * 60 * 1000)) +
        1;

    return days > 1 ? t(':count días', { count: days }) : t('1 día');
}

function isDisabled(date: TourDateGlobalAdmin): boolean {
    return date.display_status === 'cancelled';
}

function subtitleFor(date: TourDateGlobalAdmin): string {
    const parts = [date.code];
    const duration = durationLabel(date);

    if (duration !== '') {
        parts.push(duration);
    }

    if (isDisabled(date)) {
        parts.push(t('inhabilitada'));
    }

    return parts.join(' · ');
}

function priceLabel(date: TourDateGlobalAdmin): string {
    return formatCurrency(date.effective_price, currency.value);
}

function occupancyPercent(date: TourDateGlobalAdmin): number {
    if (date.capacity <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((date.booked_count / date.capacity) * 100));
}

/**
 * La barra sigue al sistema de diseño: tinta cuando ya no queda cupo, línea
 * cuando no se ha vendido nada, y el color de la agencia en el medio.
 */
function occupancyBarClass(date: TourDateGlobalAdmin): string {
    const percent = occupancyPercent(date);

    if (percent >= 100) {
        return 'bg-brand-ink';
    }

    return percent === 0 ? 'bg-border' : 'bg-primary';
}

/** Una salida ya realizada no se edita ni se inhabilita: solo se consulta. */
function canManage(date: TourDateGlobalAdmin): boolean {
    return date.display_status !== 'finished';
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[880px] text-sm">
            <thead>
                <tr class="border-b border-border text-left">
                    <MonoLabel as="th" class="px-4 py-3">{{
                        $t('Tour')
                    }}</MonoLabel>
                    <MonoLabel as="th" class="px-4 py-3">{{
                        $t('Fecha')
                    }}</MonoLabel>
                    <MonoLabel as="th" class="px-4 py-3">{{
                        $t('Precio')
                    }}</MonoLabel>
                    <MonoLabel as="th" class="px-4 py-3">{{
                        $t('Guía')
                    }}</MonoLabel>
                    <MonoLabel as="th" class="px-4 py-3">{{
                        $t('Ocupación')
                    }}</MonoLabel>
                    <MonoLabel as="th" class="px-4 py-3 text-right">
                        {{ $t('Acciones') }}
                    </MonoLabel>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="date in props.departures"
                    :key="date.id"
                    class="border-b border-brand-line-2 align-middle transition last:border-0 hover:bg-primary-soft/50"
                    :class="isDisabled(date) ? 'opacity-[.62]' : ''"
                >
                    <td class="px-4 py-3.5">
                        <Link
                            :href="tourShowPage(date.tour.id).url"
                            class="text-[14.5px] font-semibold text-foreground underline-offset-4 hover:underline"
                        >
                            {{ date.tour.name }}
                        </Link>
                        <MonoLabel class="mt-1">{{
                            subtitleFor(date)
                        }}</MonoLabel>
                    </td>

                    <td class="px-4 py-3.5">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="grid w-[46px] shrink-0 place-items-center rounded-lg border border-border bg-background py-1"
                            >
                                <span
                                    class="text-base leading-none font-semibold tabular-nums"
                                >
                                    {{ formatDayMonth(date.starts_at).day }}
                                </span>
                                <span
                                    class="mt-0.5 text-[10px] font-semibold tracking-[0.09em] text-muted-foreground"
                                >
                                    {{ formatDayMonth(date.starts_at).month }}
                                </span>
                            </span>
                            <span class="min-w-0">
                                <span
                                    class="block text-[13px] font-medium text-foreground"
                                >
                                    {{ formatWeekdayTime(date.starts_at) }}
                                </span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ formatDayDistance(date.starts_at) }}
                                </span>
                            </span>
                        </div>
                    </td>

                    <td class="px-4 py-3.5 whitespace-nowrap">
                        <span class="block text-[15px] font-bold tabular-nums">
                            {{ priceLabel(date) }}
                        </span>
                        <span class="block text-xs text-muted-foreground">
                            {{ $t('por persona') }}
                        </span>
                    </td>

                    <td class="px-4 py-3.5">
                        <div v-if="date.guide" class="flex items-center gap-2">
                            <InitialsAvatar :name="date.guide.name" size="sm" />
                            <span class="min-w-0">
                                <span
                                    class="block text-[13px] font-medium text-foreground"
                                >
                                    {{ date.guide.name }}
                                </span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ $t('asignado') }}
                                </span>
                            </span>
                        </div>
                        <span
                            v-else
                            class="inline-flex items-center rounded-full bg-brand-drop-50 px-2.5 py-1 text-[11.5px] font-semibold text-brand-drop"
                        >
                            {{ $t('Sin guía') }}
                        </span>
                    </td>

                    <td class="px-4 py-3.5">
                        <div
                            class="flex w-[140px] items-baseline justify-between gap-2"
                        >
                            <span
                                class="text-[13px] font-semibold tabular-nums"
                            >
                                {{ date.booked_count }}/{{ date.capacity }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{
                                    $t(':count libres', {
                                        count: date.available_seats,
                                    })
                                }}
                            </span>
                        </div>
                        <div
                            class="mt-1.5 h-1.5 w-[140px] overflow-hidden rounded-full bg-brand-line-2"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :class="occupancyBarClass(date)"
                                :style="{
                                    width: `${occupancyPercent(date)}%`,
                                }"
                            />
                        </div>
                    </td>

                    <td class="px-4 py-3.5">
                        <div class="flex justify-end">
                            <ActionMenu
                                variant="ghost"
                                :label="
                                    $t('Acciones de :name', {
                                        name: date.tour.name,
                                    })
                                "
                            >
                                <DropdownMenuItem
                                    @select="emit('detail', date)"
                                >
                                    <Eye class="size-4" />
                                    {{ $t('Ver detalle') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="canManage(date) && !isDisabled(date)"
                                    @select="emit('edit', date)"
                                >
                                    <Pencil class="size-4" />
                                    {{ $t('Editar salida') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="canManage(date) && !isDisabled(date)"
                                    @select="emit('assign-guide', date)"
                                >
                                    <UserRoundCog class="size-4" />
                                    {{ $t('Asignar guía') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="isDisabled(date)"
                                    :disabled="props.busyId === date.id"
                                    @select="emit('restore', date)"
                                >
                                    <CheckCircle2 class="size-4" />
                                    {{ $t('Habilitar') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-else-if="canManage(date)"
                                    variant="destructive"
                                    @select="emit('cancel', date)"
                                >
                                    <Ban class="size-4" />
                                    {{ $t('Inhabilitar') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="date.booked_count === 0"
                                    variant="destructive"
                                    @select="emit('remove', date)"
                                >
                                    <Trash2 class="size-4" />
                                    {{ $t('Eliminar salida') }}
                                </DropdownMenuItem>
                            </ActionMenu>
                        </div>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="border-t border-border bg-background/60">
                    <td
                        colspan="6"
                        class="px-4 py-3 text-xs text-muted-foreground"
                    >
                        <!--
                          Tres frases con su propio plural: una sola
                          cadena con tres números daba «1 viajeros».
                        -->
                        {{
                            $tc(
                                ':count salida|:count salidas',
                                props.totals.departures,
                            )
                        }}
                        ·
                        {{
                            $tc(
                                ':count viajero|:count viajeros',
                                props.totals.travellers,
                            )
                        }}
                        ·
                        {{
                            $tc(
                                ':count cupo libre|:count cupos libres',
                                props.totals.seats_left,
                            )
                        }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>
