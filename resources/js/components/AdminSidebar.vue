<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    Mountain,
    Megaphone,
    Mail,
    Star,
    Users,
    Settings,
} from 'lucide-vue-next';
import AdminNavMain from '@/components/AdminNavMain.vue';
import TenantBrandedLogo from '@/components/atoms/TenantBrandedLogo.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
        icon: LayoutDashboard,
    },
    {
        title: 'Tours',
        href: '/admin/tours',
        icon: Mountain,
    },
    {
        title: 'Promociones',
        href: '/admin/promotions',
        icon: Megaphone,
    },
    {
        title: 'Newsletter',
        href: '/admin/newsletter',
        icon: Mail,
    },
    {
        title: 'Reseñas',
        href: '/admin/reviews',
        icon: Star,
    },
    {
        title: 'Equipo',
        href: '/admin/team',
        icon: Users,
    },
    {
        title: 'Configuración',
        href: '/admin/tenant/configuration',
        icon: Settings,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/admin/dashboard">
                            <TenantBrandedLogo size="sm" />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <AdminNavMain :items="navItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
