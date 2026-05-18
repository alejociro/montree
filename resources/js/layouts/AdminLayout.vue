<script setup lang="ts">
import { useTenantBranding } from '@/composables/useTenantBranding';
import AdminSidebarLayout from '@/layouts/app/AdminSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

useTenantBranding();
</script>

<template>
    <AdminSidebarLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AdminSidebarLayout>
</template>
