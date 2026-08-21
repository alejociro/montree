<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import PlanBadge from '@/components/molecules/PlanBadge.vue';
import TenantStatusBadge from '@/components/molecules/TenantStatusBadge.vue';
import { intlLocale } from '@/lib/format';
import type { SuperAdminTenantSummary } from '@/types';

defineProps<{
    tenants: SuperAdminTenantSummary[];
    loading?: boolean;
}>();

function formatCurrency(value: string | null): string {
    if (value === null) {
        return '—';
    }

    const number = Number(value);

    if (Number.isNaN(number)) {
        return value;
    }

    return number.toLocaleString(intlLocale(), {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });
}
</script>

<template>
    <div
        class="overflow-x-auto rounded-lg border border-border bg-card shadow-sm"
    >
        <table class="min-w-full divide-y divide-border">
            <thead class="bg-muted">
                <tr>
                    <th
                        class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('Tenant') }}
                    </th>
                    <th
                        class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('Status') }}
                    </th>
                    <th
                        class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('Plan') }}
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('Usuarios') }}
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('Tours') }}
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('Bookings (30d)') }}
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('Revenue (30d)') }}
                    </th>
                    <th class="px-4 py-3" />
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr v-if="loading">
                    <td colspan="8" class="px-4 py-12">
                        <div
                            class="flex items-center justify-center gap-3 text-sm text-muted-foreground"
                        >
                            <span
                                class="size-3 animate-pulse rounded-full bg-border"
                            />
                            {{ $t('Cargando tenants...') }}
                        </div>
                    </td>
                </tr>
                <tr v-else-if="tenants.length === 0">
                    <td
                        colspan="8"
                        class="px-4 py-12 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('No se encontraron tenants con esos filtros.') }}
                    </td>
                </tr>
                <tr
                    v-for="tenant in tenants"
                    v-else
                    :key="tenant.id"
                    class="hover:bg-muted"
                >
                    <td class="px-4 py-3">
                        <div class="flex flex-col">
                            <span class="font-medium text-foreground">
                                {{ tenant.name }}
                            </span>
                            <span class="text-xs text-muted-foreground">{{
                                tenant.domain ?? tenant.slug
                            }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <TenantStatusBadge :status="tenant.status" />
                    </td>
                    <td class="px-4 py-3">
                        <PlanBadge :plan="tenant.plan" />
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-foreground">
                        {{ tenant.users_count ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-foreground">
                        {{ tenant.tours_count ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-foreground">
                        {{ tenant.bookings_count_30d ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-foreground">
                        {{ formatCurrency(tenant.revenue_30d) }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <Link
                            :href="`/super-admin/tenants/${tenant.id}`"
                            class="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
                        >
                            {{ $t('Detalle') }}
                            <ChevronRight class="size-4" />
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
