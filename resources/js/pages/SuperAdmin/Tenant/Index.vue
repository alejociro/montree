<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { index as tenantsIndex } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantController';
import Heading from '@/components/Heading.vue';
import CreateTenantDialog from '@/components/organisms/CreateTenantDialog.vue';
import TenantTable from '@/components/organisms/TenantTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import type {
    SuperAdminTenantSummary,
    TenantPlan,
    TenantsListPaginated,
    TenantStatus,
} from '@/types';

const { t } = useTranslations();

const http = useHttp();

const tenants = ref<SuperAdminTenantSummary[]>([]);
const meta = ref<TenantsListPaginated['meta'] | null>(null);
const loading = ref(true);

const search = ref('');
const status = ref<TenantStatus | 'all'>('all');
const plan = ref<TenantPlan | 'all'>('all');

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

async function loadTenants(page: number = 1): Promise<void> {
    loading.value = true;

    try {
        const params: Record<string, string | number> = { page };

        if (search.value.trim() !== '') {
            params.search = search.value.trim();
        }

        if (status.value !== 'all') {
            params.status = status.value;
        }

        if (plan.value !== 'all') {
            params.plan = plan.value;
        }

        const response = (await http.submit(
            tenantsIndex({ query: params }),
        )) as TenantsListPaginated;
        tenants.value = response.data;
        meta.value = response.meta;
    } catch {
        toast.error(t('No se pudo cargar el listado de tenants.'));
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    void loadTenants();
});

watch(search, () => {
    if (searchDebounce !== null) {
        clearTimeout(searchDebounce);
    }

    searchDebounce = setTimeout(() => {
        void loadTenants();
    }, 350);
});

watch([status, plan], () => {
    void loadTenants();
});

function handleCreated(tenantId: number): void {
    router.visit(`/super-admin/tenants/${tenantId}`);
}
</script>

<template>
    <Head :title="$t('Super admin · Tenants')" />

    <div class="space-y-6 px-4 py-6 md:px-8">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="$t('Tenants de la plataforma')"
                :description="
                    $t(
                        'Busca, filtra y administra todos los tenants registrados.',
                    )
                "
            />
            <CreateTenantDialog @created="handleCreated" />
        </div>

        <div
            class="flex flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-sm md:flex-row md:items-center"
        >
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    :placeholder="$t('Buscar por nombre o slug...')"
                    class="pl-9"
                />
            </div>

            <Select v-model="status">
                <SelectTrigger class="w-full md:w-44">
                    <SelectValue :placeholder="$t('Estado')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        $t('Todos los estados')
                    }}</SelectItem>
                    <SelectItem value="active">{{ $t('Activos') }}</SelectItem>
                    <SelectItem value="suspended">{{
                        $t('Suspendidos')
                    }}</SelectItem>
                    <SelectItem value="pending">{{
                        $t('Pendientes')
                    }}</SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="plan">
                <SelectTrigger class="w-full md:w-44">
                    <SelectValue :placeholder="$t('Plan')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        $t('Todos los planes')
                    }}</SelectItem>
                    <SelectItem value="basic">{{ $t('Basic') }}</SelectItem>
                    <SelectItem value="professional">{{
                        $t('Professional')
                    }}</SelectItem>
                    <SelectItem value="enterprise">{{
                        $t('Enterprise')
                    }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <TenantTable :tenants="tenants" :loading="loading" />

        <div
            v-if="meta && meta.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                {{
                    $t('Mostrando :from–:to de :total', {
                        from: meta.from ?? 0,
                        to: meta.to ?? 0,
                        total: meta.total,
                    })
                }}
            </span>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="meta.current_page === 1 || loading"
                    @click="loadTenants(meta.current_page - 1)"
                >
                    {{ $t('Anterior') }}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="meta.current_page === meta.last_page || loading"
                    @click="loadTenants(meta.current_page + 1)"
                >
                    {{ $t('Siguiente') }}
                </Button>
            </div>
        </div>
    </div>
</template>
