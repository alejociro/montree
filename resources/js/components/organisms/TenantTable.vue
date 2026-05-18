<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import PlanBadge from '@/components/molecules/PlanBadge.vue';
import TenantStatusBadge from '@/components/molecules/TenantStatusBadge.vue';
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

    return number.toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
}
</script>

<template>
    <div
        class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
    >
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-900/60">
                <tr>
                    <th
                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400"
                    >
                        Tenant
                    </th>
                    <th
                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400"
                    >
                        Status
                    </th>
                    <th
                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400"
                    >
                        Plan
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400"
                    >
                        Usuarios
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400"
                    >
                        Tours
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400"
                    >
                        Bookings (30d)
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400"
                    >
                        Revenue (30d)
                    </th>
                    <th class="px-4 py-3" />
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                <tr v-if="loading">
                    <td colspan="8" class="px-4 py-12">
                        <div class="flex items-center justify-center gap-3 text-sm text-zinc-500">
                            <span
                                class="size-3 animate-pulse rounded-full bg-zinc-400 dark:bg-zinc-600"
                            />
                            Cargando tenants...
                        </div>
                    </td>
                </tr>
                <tr v-else-if="tenants.length === 0">
                    <td
                        colspan="8"
                        class="px-4 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400"
                    >
                        No se encontraron tenants con esos filtros.
                    </td>
                </tr>
                <tr
                    v-for="tenant in tenants"
                    v-else
                    :key="tenant.id"
                    class="hover:bg-zinc-50 dark:hover:bg-zinc-900/40"
                >
                    <td class="px-4 py-3">
                        <div class="flex flex-col">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ tenant.name }}
                            </span>
                            <span class="text-xs text-zinc-500">{{ tenant.domain ?? tenant.slug }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <TenantStatusBadge :status="tenant.status" />
                    </td>
                    <td class="px-4 py-3">
                        <PlanBadge :plan="tenant.plan" />
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-200">
                        {{ tenant.users_count ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-200">
                        {{ tenant.tours_count ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-200">
                        {{ tenant.bookings_count_30d ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-zinc-700 dark:text-zinc-200">
                        {{ formatCurrency(tenant.revenue_30d) }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <Link
                            :href="`/super-admin/tenants/${tenant.id}`"
                            class="inline-flex items-center text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                        >
                            Detalle
                            <ChevronRight class="size-4" />
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
