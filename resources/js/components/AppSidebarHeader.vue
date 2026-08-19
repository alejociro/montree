<script setup lang="ts">
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useNavigation } from '@/composables/useNavigation';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { breadcrumbs: menuBreadcrumbs } = useNavigation();

// A9: la pagina manda si declara sus breadcrumbs; si no, se deducen del menu
// (home del rol + seccion actual) en vez de dejar la franja superior vacia.
const trail = computed<BreadcrumbItem[]>(() =>
    props.breadcrumbs.length > 0 ? props.breadcrumbs : menuBreadcrumbs.value,
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <Breadcrumbs v-if="trail.length > 0" :breadcrumbs="trail" />
        </div>
    </header>
</template>
