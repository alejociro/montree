<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import TenantActivityCharts from '@/components/organisms/TenantActivityCharts.vue';
import TenantDetailPanel from '@/components/organisms/TenantDetailPanel.vue';
import { Button } from '@/components/ui/button';
import { index as tenantsIndex } from '@/routes/super-admin/tenants';
import type {
    CommissionSchedule,
    CommissionScope,
    SuperAdminTenantSummary,
    TenantChargesSummary,
    TenantMonthlySeries,
} from '@/types';

const props = defineProps<{
    tenant: SuperAdminTenantSummary;
    charges_summary: TenantChargesSummary;
    monthly: TenantMonthlySeries;
    roles: string[];
    commissionSchedule: CommissionSchedule & { scope: CommissionScope };
}>();
</script>

<template>
    <Head :title="`Super admin · ${props.tenant.name}`" />

    <div class="space-y-6 px-4 py-6 md:px-8">
        <Button as-child variant="ghost" size="sm">
            <Link :href="tenantsIndex.url()">
                <ArrowLeft class="mr-1 size-4" />
                {{ $t('Volver al listado') }}
            </Link>
        </Button>

        <TenantDetailPanel
            :tenant="tenant"
            :charges-summary="charges_summary"
            :roles="roles"
            :commission-schedule="commissionSchedule"
        />

        <TenantActivityCharts
            :monthly="monthly"
            :currency="charges_summary.currency"
        />
    </div>
</template>
