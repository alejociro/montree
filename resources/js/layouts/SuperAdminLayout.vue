<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import SuperAdminSidebar from '@/components/SuperAdminSidebar.vue';
import { Toaster } from '@/components/ui/sonner';
import { useTenantBranding } from '@/composables/useTenantBranding';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

// WHY: el host de plataforma no resuelve tenant, así que esto restaura los
// tokens de marca de MONTREE. Sin la llamada, los colores que dejó escritos en
// `:root` la última página de un tenant sobrevivían al cambio de host.
useTenantBranding();
</script>

<template>
    <AppShell variant="sidebar">
        <SuperAdminSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden bg-muted">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
