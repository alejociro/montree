<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
import { useNavigation } from '@/composables/useNavigation';

// A1/A4: este sidebar acompaña a la zona de viajero (`/account/*`, `/settings/*`,
// `/guide/*`). Antes armaba el menu con un `switch` por rol que mandaba a
// cualquiera que no fuera guia u operador al menu de cliente — el admin perdia
// la administracion al abrir "Mis reservas". Ahora recibe las mismas secciones
// filtradas por permiso que el panel, asi que el staff conserva su menu.
const { sections, homeUrl } = useNavigation();
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeUrl">
                            <TenantBrandedLogo size="sm" />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain
                v-for="section in sections"
                :key="section.id"
                :label="section.label"
                :items="section.items"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
