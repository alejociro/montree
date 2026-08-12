<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    Heart,
    LayoutDashboard,
    LogOut,
    Settings,
    Ticket,
} from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { schedule as guideSchedule } from '@/routes/guide';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

const props = defineProps<Props>();

// WHY: the traveller area (`/account/*`) and the agency panel share this menu.
// Without this entry a tenant admin who opens "Mis reservas" has no way back to
// their panel and looks demoted to a plain customer.
const workspace = computed(() => {
    if (props.user.tenantRole === 'admin' || props.user.tenantRole === 'operator') {
        return {
            href: adminDashboard().url,
            label: 'Panel de la agencia',
            icon: LayoutDashboard,
        };
    }

    if (props.user.tenantRole === 'guide') {
        return {
            href: guideSchedule().url,
            label: 'Mi agenda de salidas',
            icon: CalendarDays,
        };
    }

    return null;
});
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <template v-if="workspace">
        <DropdownMenuGroup>
            <DropdownMenuItem :as-child="true">
                <Link class="block w-full cursor-pointer" :href="workspace.href">
                    <component :is="workspace.icon" class="mr-2 h-4 w-4" />
                    {{ workspace.label }}
                </Link>
            </DropdownMenuItem>
        </DropdownMenuGroup>
        <DropdownMenuSeparator />
    </template>
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" href="/account/bookings">
                <Ticket class="mr-2 h-4 w-4" />
                Mis reservas
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" href="/account/favorites">
                <Heart class="mr-2 h-4 w-4" />
                Favoritos
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                href="/account/notifications"
            >
                <Bell class="mr-2 h-4 w-4" />
                Notificaciones
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Configuración
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Cerrar sesión
        </Link>
    </DropdownMenuItem>
</template>
