<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency, formatDate } from '@/lib/format';
import { show as tenantShow } from '@/routes/super-admin/tenants';
import { index as chargesIndex } from '@/routes/super-admin/tenants/charges';
import type {
    PlatformChargeFilters,
    PlatformChargesPaginated,
    PlatformChargeTotals,
} from '@/types';

const props = defineProps<{
    tenant: { id: number; name: string; slug: string };
    charges: PlatformChargesPaginated;
    filters: PlatformChargeFilters;
    totals: PlatformChargeTotals;
}>();

const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

function reload(page?: number): void {
    const query: Record<string, string | number> = {};

    if (from.value !== '') {
        query.from = from.value;
    }

    if (to.value !== '') {
        query.to = to.value;
    }

    if (page !== undefined && page > 1) {
        query.page = page;
    }

    router.get(chargesIndex.url(props.tenant.id, { query }), undefined, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['charges', 'filters', 'totals'],
    });
}

watch([from, to], () => reload());
</script>

<template>
    <Head :title="`Super admin · ${props.tenant.name}`" />

    <div class="space-y-6 px-4 py-6 md:px-8">
        <Button as-child variant="ghost" size="sm">
            <Link :href="tenantShow.url(tenant.id)">
                <ArrowLeft class="mr-1 size-4" />
                {{ $t('Volver a la agencia') }}
            </Link>
        </Button>

        <Heading
            :title="$t('Cargos de plataforma')"
            :description="
                $t('Registro contable de lo que :name le debe a MONTREE.', {
                    name: tenant.name,
                })
            "
        />

        <div
            class="flex flex-col gap-4 rounded-lg border border-border bg-card p-4 shadow-sm md:flex-row md:items-end md:justify-between"
        >
            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="space-y-1">
                    <Label for="charges-from">{{ $t('Desde') }}</Label>
                    <Input id="charges-from" v-model="from" type="date" />
                </div>
                <div class="space-y-1">
                    <Label for="charges-to">{{ $t('Hasta') }}</Label>
                    <Input id="charges-to" v-model="to" type="date" />
                </div>
            </div>

            <dl class="flex gap-6">
                <div>
                    <dt class="text-xs tracking-wider text-muted-foreground uppercase">
                        {{ $t('Cargos') }}
                    </dt>
                    <dd class="text-xl font-semibold text-foreground">
                        {{ totals.count }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wider text-muted-foreground uppercase">
                        {{ $t('Total') }}
                    </dt>
                    <dd class="text-xl font-semibold text-foreground">
                        {{ formatCurrency(totals.amount, totals.currency) }}
                    </dd>
                </div>
            </dl>
        </div>

        <div
            class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm"
        >
            <table class="min-w-full divide-y divide-border">
                <thead class="bg-muted">
                    <tr>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            {{ $t('Fecha') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            {{ $t('Reserva') }}
                        </th>
                        <th
                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            {{ $t('Base') }}
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            {{ $t('Tipo') }}
                        </th>
                        <th
                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            {{ $t('Valor aplicado') }}
                        </th>
                        <th
                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            {{ $t('Cobrado') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="charges.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-12 text-center text-sm text-muted-foreground"
                        >
                            {{ $t('No hay cargos en el período elegido.') }}
                        </td>
                    </tr>
                    <tr
                        v-for="charge in charges.data"
                        v-else
                        :key="charge.id"
                        class="hover:bg-muted"
                    >
                        <td class="px-4 py-3 text-sm text-foreground">
                            {{ formatDate(charge.charged_at) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-foreground">
                            {{ charge.booking?.booking_number ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-foreground">
                            {{
                                formatCurrency(
                                    charge.base_amount,
                                    charge.booking?.currency ?? charge.currency,
                                )
                            }}
                        </td>
                        <td class="px-4 py-3 text-sm text-foreground">
                            {{
                                charge.type === 'percentage'
                                    ? $t('Porcentaje por reserva')
                                    : $t('Monto fijo por reserva')
                            }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-foreground">
                            {{
                                charge.type === 'percentage'
                                    ? `${Number(charge.applied_value)} %`
                                    : formatCurrency(
                                          charge.applied_value,
                                          charge.currency,
                                      )
                            }}
                        </td>
                        <td
                            class="px-4 py-3 text-right text-sm font-medium text-foreground"
                        >
                            {{ formatCurrency(charge.amount, charge.currency) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="charges.meta && charges.meta.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                {{
                    $t('Mostrando :from–:to de :total', {
                        from: charges.meta.from ?? 0,
                        to: charges.meta.to ?? 0,
                        total: charges.meta.total,
                    })
                }}
            </span>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="charges.meta.current_page === 1"
                    @click="reload(charges.meta.current_page - 1)"
                >
                    {{ $t('Anterior') }}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        charges.meta.current_page === charges.meta.last_page
                    "
                    @click="reload(charges.meta.current_page + 1)"
                >
                    {{ $t('Siguiente') }}
                </Button>
            </div>
        </div>
    </div>
</template>
