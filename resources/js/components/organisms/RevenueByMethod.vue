<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency } from '@/lib/format';
import type { RevenueByMethodRow } from '@/types/dashboard';

/**
 * De dónde vino la plata del periodo: pasarela, efectivo y transferencia.
 *
 * WHY el estado vacío: con el bruto en cero el backend manda igual los tres
 * medios (para que el cero se distinga del dato ausente), pero pintar tres
 * barras vacías con «0 %» es ruido, no información.
 */
type Props = {
    rows: RevenueByMethodRow[];
    gross: string;
    currency: string;
};

const props = defineProps<Props>();

const { t } = useTranslations();

const hasRevenue = computed(() => Number.parseFloat(props.gross) > 0);

function shareLabel(row: RevenueByMethodRow): string {
    return t(':percent %', { percent: row.share_pct });
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ $t('De dónde vino') }}</CardTitle>
            <CardDescription>
                {{ $t('Ingreso bruto del periodo por medio de pago.') }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <p
                v-if="!hasRevenue"
                class="py-4 text-center text-sm text-muted-foreground"
            >
                {{ $t('No hubo ingresos en este periodo.') }}
            </p>

            <ul v-else class="space-y-3">
                <li v-for="row in props.rows" :key="row.method">
                    <div
                        class="flex items-baseline justify-between gap-3 text-sm"
                    >
                        <span class="truncate">{{ row.label }}</span>
                        <span class="flex items-baseline gap-2">
                            <span class="font-semibold tabular-nums">
                                {{ formatCurrency(row.amount, props.currency) }}
                            </span>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{ shareLabel(row) }}
                            </span>
                        </span>
                    </div>
                    <div
                        class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        :aria-valuemin="0"
                        :aria-valuemax="100"
                        :aria-valuenow="row.share_pct"
                        :aria-label="row.label"
                        :aria-valuetext="shareLabel(row)"
                    >
                        <div
                            class="h-full rounded-full bg-primary transition-all"
                            :style="{ width: `${row.share_pct}%` }"
                        />
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
