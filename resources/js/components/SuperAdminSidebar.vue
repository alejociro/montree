<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, Gauge, ShieldCheck } from 'lucide-vue-next';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useTranslations } from '@/composables/useTranslations';
import type { NavItem } from '@/types';

const { t } = useTranslations();

const navItems: NavItem[] = [
    {
        title: t('Dashboard'),
        href: '/super-admin/dashboard',
        icon: Gauge,
    },
    {
        title: t('Tenants'),
        href: '/super-admin/tenants',
        icon: Building2,
    },
];

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/super-admin/dashboard">
                            <div
                                class="flex aspect-square size-9 items-center justify-center rounded-lg bg-zinc-900 text-zinc-50"
                            >
                                <ShieldCheck class="size-5" />
                            </div>
                            <div
                                class="grid flex-1 text-left text-sm leading-tight"
                            >
                                <span
                                    class="truncate font-semibold text-zinc-900 dark:text-zinc-100"
                                >
                                    {{ $t('MONTREE Platform') }}
                                </span>
                                <span class="truncate text-xs text-zinc-500">{{
                                    $t('Super Admin')
                                }}</span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel>{{ $t('Plataforma') }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in navItems" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl(item.href)"
                            :tooltip="$t(item.title)"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ $t(item.title) }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
