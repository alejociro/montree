<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
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
import { index as tenantsIndex } from '@/routes/super-admin/tenants';
import type {
    TenantPlan,
    TenantsListFilters,
    TenantsListPaginated,
    TenantStatus,
} from '@/types';

const props = defineProps<{
    tenants: TenantsListPaginated;
    filters: TenantsListFilters;
}>();

const ALL = 'all';

const search = ref(props.filters.search ?? '');
const status = ref<TenantStatus | typeof ALL>(props.filters.status ?? ALL);
const plan = ref<TenantPlan | typeof ALL>(props.filters.plan ?? ALL);

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

function reload(page?: number): void {
    const query: Record<string, string | number> = {};

    if (search.value.trim() !== '') {
        query.search = search.value.trim();
    }

    if (status.value !== ALL) {
        query.status = status.value;
    }

    if (plan.value !== ALL) {
        query.plan = plan.value;
    }

    if (page !== undefined && page > 1) {
        query.page = page;
    }

    router.get(tenantsIndex.url({ query }), undefined, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['tenants', 'filters'],
    });
}

watch(search, () => {
    if (searchDebounce !== null) {
        clearTimeout(searchDebounce);
    }

    searchDebounce = setTimeout(() => reload(), 350);
});

watch([status, plan], () => reload());
</script>

<template>
    <Head :title="$t('Super admin · Agencias')" />

    <div class="space-y-6 px-4 py-6 md:px-8">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="$t('Agencias de la plataforma')"
                :description="
                    $t(
                        'Busca, filtra y administra todas las agencias registradas.',
                    )
                "
            />
            <CreateTenantDialog />
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

        <TenantTable :tenants="tenants.data" />

        <div
            v-if="tenants.meta && tenants.meta.last_page > 1"
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <span>
                {{
                    $t('Mostrando :from–:to de :total', {
                        from: tenants.meta.from ?? 0,
                        to: tenants.meta.to ?? 0,
                        total: tenants.meta.total,
                    })
                }}
            </span>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="tenants.meta.current_page === 1"
                    @click="reload(tenants.meta.current_page - 1)"
                >
                    {{ $t('Anterior') }}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        tenants.meta.current_page === tenants.meta.last_page
                    "
                    @click="reload(tenants.meta.current_page + 1)"
                >
                    {{ $t('Siguiente') }}
                </Button>
            </div>
        </div>
    </div>
</template>
