<script setup lang="ts">
import { Head, Link, useHttp } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { update as updateConfiguration } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantConfigurationController';
import { show as tenantShow } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantController';
import { update as updatePlan } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantPlanController';
import { update as updateStatus } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantStatusController';
import TenantDetailPanel from '@/components/organisms/TenantDetailPanel.vue';
import { Button } from '@/components/ui/button';
import { useApi } from '@/composables/useApi';
import type {
    SuperAdminTenantSummary,
    TenantPlan,
    TenantStatus,
} from '@/types';

const props = defineProps<{
    tenantId: number;
}>();

const http = useHttp();
const api = useApi();

const tenant = ref<SuperAdminTenantSummary | null>(null);
const loading = ref(true);
const processing = ref(false);
const configProcessing = ref(false);

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

function reportError(message: string): void {
    toast.error(message);
}

function firstErrorMessage(
    errors: Record<string, string>,
    fallback: string,
): string {
    const first = Object.values(errors)[0];

    return first && first.length > 0 ? first : fallback;
}

function handleStatusChange(next: TenantStatus, reason: string | null): void {
    if (tenant.value === null) {
        return;
    }

    processing.value = true;

    void api.patch(
        updateStatus(tenant.value.id).url,
        { status: next, reason },
        {
            onSuccess: () => {
                toast.success('Estado actualizado correctamente.');
                void loadTenant();
            },
            onError: (errors) => {
                reportError(
                    firstErrorMessage(
                        errors,
                        'No se pudo actualizar el estado.',
                    ),
                );
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function handlePlanChange(next: TenantPlan): void {
    if (tenant.value === null) {
        return;
    }

    processing.value = true;

    void api.patch(
        updatePlan(tenant.value.id).url,
        { plan: next },
        {
            onSuccess: () => {
                toast.success('Plan actualizado correctamente.');
                void loadTenant();
            },
            onError: (errors) => {
                reportError(
                    firstErrorMessage(errors, 'No se pudo actualizar el plan.'),
                );
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function handleConfigurationUpdate(formData: FormData): void {
    if (tenant.value === null) {
        return;
    }

    configProcessing.value = true;

    void api.post(updateConfiguration(tenant.value.id).url, formData, {
        onSuccess: () => {
            toast.success('Configuración actualizada correctamente.');
            void loadTenant();
        },
        onError: (errors) => {
            reportError(
                firstErrorMessage(
                    errors,
                    'No se pudo guardar la configuración.',
                ),
            );
        },
        onFinish: () => {
            configProcessing.value = false;
        },
    });
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
