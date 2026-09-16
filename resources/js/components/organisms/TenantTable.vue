<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LogIn } from 'lucide-vue-next';
import { computed } from 'vue';
import PlanBadge from '@/components/molecules/PlanBadge.vue';
import TenantStatusBadge from '@/components/molecules/TenantStatusBadge.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency } from '@/lib/format';
import { enter as enterTenant } from '@/routes/super-admin/tenants';
import { show as tenantShow } from '@/routes/super-admin/tenants';
import type { SuperAdminTenantSummary } from '@/types';

const { t } = useTranslations();

defineProps<{
    tenants: SuperAdminTenantSummary[];
}>();

const page = usePage();

/**
 * WHY: la fila no navega, hace POST. El panel de la agencia vive en otro host y
 * la sesión es host-only, así que hace falta un handoff — y se abre en pestaña
 * nueva para no perder el listado. Un `<Link>` no puede hacer nada de eso, por
 * eso cada fila es un formulario nativo con su token CSRF.
 */
const csrfToken = computed(() => page.props.csrfToken);

function commissionLabel(tenant: SuperAdminTenantSummary): string {
    if (tenant.commission.type === null || tenant.commission.value === null) {
        return t('Sin cobro');
    }

    return tenant.commission.type === 'percentage'
        ? `${Number(tenant.commission.value)} %`
        : formatCurrency(tenant.commission.value, tenant.commission.currency);
}

function enterTitle(tenant: SuperAdminTenantSummary): string {
    return tenant.can_enter
        ? t('Entrar al panel de :name', { name: tenant.name })
        : t('Solo se puede entrar al panel de una agencia activa.');
}

/**
 * Toda la fila entra al panel (contracts §1). No hay estado que levantar: se
 * envia el mismo `<form>` nativo que ya vive en la celda, porque es el unico que
 * puede hacer POST a otro host y abrirlo en pestana nueva. Una agencia que no
 * esta activa no responde al clic.
 */
function enterFromRow(event: Event, tenant: SuperAdminTenantSummary): void {
    if (!tenant.can_enter) {
        return;
    }

    (event.currentTarget as HTMLElement)
        .querySelector('form')
        ?.requestSubmit();
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
                        {{ $t('Agencia') }}
                    </th>
                    <th
                        class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('Estado') }}
                    </th>
                    <th
                        class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('Plan') }}
                    </th>
                    <th
                        class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('Cobro') }}
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
                        {{ $t('Reservas (30d)') }}
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('Ingresos (30d)') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <tr v-if="tenants.length === 0">
                    <td
                        colspan="8"
                        class="px-4 py-12 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('No se encontraron agencias con esos filtros.') }}
                    </td>
                </tr>
                <tr
                    v-for="tenant in tenants"
                    v-else
                    :key="tenant.id"
                    class="hover:bg-muted"
                    :class="tenant.can_enter ? 'cursor-pointer' : ''"
                    :title="enterTitle(tenant)"
                    :aria-disabled="tenant.can_enter ? undefined : 'true'"
                    @click="enterFromRow($event, tenant)"
                >
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <form
                                :action="enterTenant.url(tenant.id)"
                                method="post"
                                target="_blank"
                            >
                                <input
                                    type="hidden"
                                    name="_token"
                                    :value="csrfToken"
                                />
                                <button
                                    type="submit"
                                    class="inline-flex size-8 items-center justify-center rounded-md border border-border text-muted-foreground transition hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="!tenant.can_enter"
                                    :title="enterTitle(tenant)"
                                    @click.stop
                                >
                                    <LogIn class="size-4" />
                                    <span class="sr-only">{{
                                        $t('Entrar')
                                    }}</span>
                                </button>
                            </form>
                            <div class="flex flex-col">
                                <Link
                                    :href="tenantShow.url(tenant.id)"
                                    class="font-medium text-foreground underline-offset-4 hover:underline"
                                    @click.stop
                                >
                                    {{ tenant.name }}
                                </Link>
                                <span class="text-xs text-muted-foreground">{{
                                    tenant.domain ?? tenant.slug
                                }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <TenantStatusBadge :status="tenant.status" />
                    </td>
                    <td class="px-4 py-3">
                        <PlanBadge :plan="tenant.plan" />
                    </td>
                    <td class="px-4 py-3 text-sm text-foreground">
                        {{ commissionLabel(tenant) }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-foreground">
                        {{ tenant.stats.users_count }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-foreground">
                        {{ tenant.stats.tours_count }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-foreground">
                        {{ tenant.stats.bookings_count_30d }}
                    </td>
                    <td class="px-4 py-3 text-right text-sm text-foreground">
                        {{
                            formatCurrency(
                                tenant.stats.revenue_30d,
                                tenant.commission.currency,
                            )
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
