<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { show as dashboardShow } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/DashboardController';
import Heading from '@/components/Heading.vue';
import PlatformStats from '@/components/organisms/PlatformStats.vue';
import type { PlatformMetrics, TenantPlan } from '@/types';

const http = useHttp();

const metrics = ref<PlatformMetrics | null>(null);
const loading = ref(true);

async function loadMetrics(): Promise<void> {
    loading.value = true;

    try {
        const response = (await http.submit(dashboardShow())) as { data: PlatformMetrics };
        metrics.value = response.data;
    } catch {
        toast.error('No se pudieron cargar las métricas de la plataforma.');
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    void loadMetrics();
});

function planLabel(plan: TenantPlan): string {
    switch (plan) {
        case 'basic':
            return 'Basic';
        case 'professional':
            return 'Professional';
        case 'enterprise':
            return 'Enterprise';
        default:
            return plan;
    }
}
</script>

<template>
    <Head title="Super admin · Dashboard" />

    <div class="space-y-8 px-4 py-6 md:px-8">
        <Heading
            title="Panel de plataforma"
            description="Métricas agregadas de todos los tenants y la plataforma MONTREE."
        />

        <PlatformStats :metrics="metrics" />

        <section
            class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
        >
            <header class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">
                    Distribución por plan
                </h2>
                <span v-if="loading" class="text-xs text-zinc-500">Cargando...</span>
            </header>

            <div
                v-if="metrics"
                class="grid grid-cols-1 gap-4 sm:grid-cols-3"
            >
                <div
                    v-for="(count, plan) in metrics.plan_distribution"
                    :key="plan"
                    class="flex items-center justify-between rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950"
                >
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                        {{ planLabel(plan as TenantPlan) }}
                    </span>
                    <span class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">
                        {{ count }}
                    </span>
                </div>
            </div>

            <div
                v-else-if="loading"
                class="grid grid-cols-1 gap-4 sm:grid-cols-3"
            >
                <div
                    v-for="i in 3"
                    :key="i"
                    class="h-14 animate-pulse rounded-md bg-zinc-100 dark:bg-zinc-800"
                />
            </div>
        </section>
    </div>
</template>
