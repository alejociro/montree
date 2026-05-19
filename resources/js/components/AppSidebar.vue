<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarDays,
    Heart,
    Home,
    Map,
    User,
} from 'lucide-vue-next';
import { computed } from 'vue';
import TenantBrandedLogo from '@/components/atoms/TenantBrandedLogo.vue';
import NavMain from '@/components/NavMain.vue';
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
import type { TenantRole } from '@/types/auth';

const customerNavItems: NavItem[] = [
    { title: 'Inicio', href: '/', icon: Home },
    { title: 'Mis Reservas', href: '/account/bookings', icon: CalendarCheck },
    { title: 'Favoritos', href: '/account/favorites', icon: Heart },
    { title: 'Mi Cuenta', href: '/account', icon: User },
];

const guideNavItems: NavItem[] = [
    { title: 'Mi agenda', href: '/guide/schedule', icon: CalendarDays },
    { title: 'Mi Cuenta', href: '/account', icon: User },
];

const operatorNavItems: NavItem[] = [
    { title: 'Tours', href: '/admin/tours', icon: Map },
    { title: 'Mi Cuenta', href: '/account', icon: User },
];

const page = usePage();
const role = computed<TenantRole | null>(
    () => page.props.auth?.user?.tenantRole ?? null,
);

const navItems = computed<NavItem[]>(() => {
    switch (role.value) {
        case 'guide':
            return guideNavItems;
        case 'operator':
            return operatorNavItems;
        default:
            return customerNavItems;
    }
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/">
                            <TenantBrandedLogo size="sm" />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="navItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
