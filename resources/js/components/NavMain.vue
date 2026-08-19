<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

type Props = {
    items: NavItem[];
    label?: string;
};

withDefaults(defineProps<Props>(), {
    label: 'Menú',
});

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

// A5: en `/admin/tours/create` no quedaba ningun item activo porque se comparaba
// por igualdad exacta. Ahora una subruta marca a su padre, salvo los items
// declarados `exact` — `/` y `/account` son prefijo de medio menu.
function isActive(item: NavItem): boolean {
    return item.exact === true
        ? isCurrentUrl(item.href)
        : isCurrentOrParentUrl(item.href);
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="toUrl(item.href)">
                <SidebarMenuButton
                    as-child
                    :is-active="isActive(item)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
