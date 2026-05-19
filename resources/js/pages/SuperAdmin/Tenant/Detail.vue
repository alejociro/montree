<script setup lang="ts">
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { update as updateConfiguration } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantConfigurationController';
import { show as tenantShow } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantController';
import { update as updatePlan } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantPlanController';
import { update as updateStatus } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantStatusController';
import TenantDetailPanel from '@/components/organisms/TenantDetailPanel.vue';
import { Button } from '@/components/ui/button';
import type { SuperAdminTenantSummary, TenantPlan, TenantStatus } from '@/types';

const props = defineProps<{
    tenantId: number;
}>();

const http = useHttp();
const configProcessing = ref(false);

const tenant = ref<SuperAdminTenantSummary | null>(null);
const loading = ref(true);
const processing = ref(false);

async function loadTenant(): Promise<void> {
    loading.value = true;

    try {
        const response = (await http.submit(tenantShow(props.tenantId))) as {
            data: SuperAdminTenantSummary;
        };
        tenant.value = response.data;
    } catch {
        toast.error('No se pudo cargar el tenant.');
    } finally {
        loading.value = false;
    }
}

function handleStatusChange(next: TenantStatus, reason: string | null): void {
    if (tenant.value === null) {
        return;
    }

    processing.value = true;

    const action = updateStatus(tenant.value.id);

    router.visit(action.url, {
        method: action.method,
        data: { status: next, reason },
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.success('Estado actualizado correctamente.');
            void loadTenant();
        },
        onError: (errors) => {
            const message = Object.values(errors)[0] ?? 'No se pudo actualizar el estado.';
            toast.error(String(message));
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

function handlePlanChange(next: TenantPlan): void {
    if (tenant.value === null) {
        return;
    }

    processing.value = true;

    const action = updatePlan(tenant.value.id);

    router.visit(action.url, {
        method: action.method,
        data: { plan: next },
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.success('Plan actualizado correctamente.');
            void loadTenant();
        },
        onError: (errors) => {
            const message = Object.values(errors)[0] ?? 'No se pudo actualizar el plan.';
            toast.error(String(message));
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

async function handleConfigurationUpdate(formData: FormData): Promise<void> {
    if (tenant.value === null) {
        return;
    }

    configProcessing.value = true;

    try {
        const response = await fetch(updateConfiguration(tenant.value.id).url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((row) => row.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] ?? '',
                ),
            },
        });

        if (response.ok) {
            toast.success('Configuración actualizada correctamente.');
            void loadTenant();
        } else {
            const body = await response.json();
            const firstError = Object.values(body.errors ?? {})[0];
            toast.error(
                Array.isArray(firstError)
                    ? (firstError[0] as string)
                    : 'No se pudo guardar la configuración.',
            );
        }
    } catch {
        toast.error('Error de conexión al guardar la configuración.');
    } finally {
        configProcessing.value = false;
    }
}

onMounted(() => {
    void loadTenant();
});
</script>

<template>
    <Head :title="`Super admin · ${tenant?.name ?? 'Tenant'}`" />

    <div class="space-y-6 px-4 py-6 md:px-8">
        <Button as-child variant="ghost" size="sm">
            <Link href="/super-admin/tenants">
                <ArrowLeft class="mr-1 size-4" />
                Volver al listado
            </Link>
        </Button>

        <div
            v-if="loading && !tenant"
            class="h-48 animate-pulse rounded-lg bg-zinc-100 dark:bg-zinc-800"
        />

        <TenantDetailPanel
            v-if="tenant"
            :tenant="tenant"
            :processing="processing || configProcessing"
            @status-change="handleStatusChange"
            @plan-change="handlePlanChange"
            @configuration-update="handleConfigurationUpdate"
        />
    </div>
</template>
