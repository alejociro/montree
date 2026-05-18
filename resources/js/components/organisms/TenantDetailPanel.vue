<script setup lang="ts">
import { Mail, Phone } from 'lucide-vue-next';
import PlanBadge from '@/components/molecules/PlanBadge.vue';
import PlanChanger from '@/components/molecules/PlanChanger.vue';
import StatusChanger from '@/components/molecules/StatusChanger.vue';
import TenantStatusBadge from '@/components/molecules/TenantStatusBadge.vue';
import type { SuperAdminTenantSummary, TenantPlan, TenantStatus } from '@/types';

defineProps<{
    tenant: SuperAdminTenantSummary;
    processing?: boolean;
}>();

const emit = defineEmits<{
    'status-change': [next: TenantStatus, reason: string | null];
    'plan-change': [next: TenantPlan];
}>();

function handleStatusSubmit(next: TenantStatus, reason: string | null): void {
    emit('status-change', next, reason);
}

function handlePlanSubmit(next: TenantPlan): void {
    emit('plan-change', next);
}

function formatCurrency(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return Number(value).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });
}
</script>

<template>
    <div class="space-y-6">
        <header
            class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 md:flex-row md:items-start md:justify-between"
        >
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ tenant.name }}
                    </h1>
                    <TenantStatusBadge :status="tenant.status" />
                    <PlanBadge :plan="tenant.plan" />
                </div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ tenant.domain ?? tenant.slug }}.montree.app
                </p>
                <div class="flex flex-wrap gap-4 text-sm text-zinc-600 dark:text-zinc-300">
                    <span v-if="tenant.contact_email" class="inline-flex items-center gap-1.5">
                        <Mail class="size-4" />
                        {{ tenant.contact_email }}
                    </span>
                    <span v-if="tenant.contact_phone" class="inline-flex items-center gap-1.5">
                        <Phone class="size-4" />
                        {{ tenant.contact_phone }}
                    </span>
                </div>
            </div>
        </header>

        <section
            class="grid gap-4 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 md:grid-cols-2"
        >
            <div class="space-y-2">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">
                    Estado del tenant
                </h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    Suspender bloquea el acceso a todos los usuarios. Restablecer reactiva el
                    servicio.
                </p>
                <StatusChanger
                    :current-status="tenant.status"
                    :processing="processing"
                    @submit="handleStatusSubmit"
                />
            </div>

            <div class="space-y-2">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">
                    Plan asignado
                </h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    Los nuevos límites aplican inmediatamente. Downgrades que excedan límites se
                    permiten con un soft warning.
                </p>
                <PlanChanger
                    :current-plan="tenant.plan"
                    :processing="processing"
                    @submit="handlePlanSubmit"
                />
            </div>
        </section>

        <section class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div
                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <p class="text-xs uppercase tracking-wider text-zinc-500">Usuarios</p>
                <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ tenant.users_count ?? '—' }}
                </p>
            </div>
            <div
                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <p class="text-xs uppercase tracking-wider text-zinc-500">Tours</p>
                <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ tenant.tours_count ?? '—' }}
                </p>
            </div>
            <div
                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <p class="text-xs uppercase tracking-wider text-zinc-500">Bookings (30d)</p>
                <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ tenant.bookings_count_30d ?? '—' }}
                </p>
            </div>
            <div
                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <p class="text-xs uppercase tracking-wider text-zinc-500">Revenue (30d)</p>
                <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ formatCurrency(tenant.revenue_30d) }}
                </p>
            </div>
        </section>
    </div>
</template>
