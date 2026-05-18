<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { index as tenantsIndex } from '@/actions/App/Http/Controllers/Api/V1/SuperAdmin/TenantController';
import Heading from '@/components/Heading.vue';
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
import type {
    SuperAdminTenantSummary,
    TenantPlan,
    TenantsListPaginated,
    TenantStatus,
} from '@/types';

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

        const response = (await http.submit(tenantsIndex({ query: params }))) as TenantsListPaginated;
        tenants.value = response.data;
        meta.value = response.meta;
    } catch {
        toast.error('No se pudo cargar el listado de tenants.');
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
</script>

<template>
    <Head title="Super admin · Tenants" />

    <div class="space-y-6 px-4 py-6 md:px-8">
        <Heading
            title="Tenants de la plataforma"
            description="Buscá, filtrá y administrá todos los tenants registrados."
        />

        <div
            class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 md:flex-row md:items-center"
        >
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Buscar por nombre o slug..."
                    class="pl-9"
                />
            </div>

            <Select v-model="status">
                <SelectTrigger class="w-full md:w-44">
                    <SelectValue placeholder="Estado" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Todos los estados</SelectItem>
                    <SelectItem value="active">Activos</SelectItem>
                    <SelectItem value="suspended">Suspendidos</SelectItem>
                    <SelectItem value="pending">Pendientes</SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="plan">
                <SelectTrigger class="w-full md:w-44">
                    <SelectValue placeholder="Plan" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Todos los planes</SelectItem>
                    <SelectItem value="basic">Basic</SelectItem>
                    <SelectItem value="professional">Professional</SelectItem>
                    <SelectItem value="enterprise">Enterprise</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <TenantTable :tenants="tenants" :loading="loading" />

        <div
            v-if="meta && meta.last_page > 1"
            class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-400"
        >
            <span>
                Mostrando {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} de {{ meta.total }}
            </span>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="meta.current_page === 1 || loading"
                    @click="loadTenants(meta.current_page - 1)"
                >
                    Anterior
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="meta.current_page === meta.last_page || loading"
                    @click="loadTenants(meta.current_page + 1)"
                >
                    Siguiente
                </Button>
            </div>
        </div>
    </div>
</template>
