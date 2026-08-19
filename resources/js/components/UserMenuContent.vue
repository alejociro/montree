<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Bell, Heart, LogOut, Settings, Ticket } from 'lucide-vue-next';
import LocaleSwitcher from '@/components/molecules/LocaleSwitcher.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useNavigation } from '@/composables/useNavigation';
import { logout } from '@/routes';
import {
    bookings as accountBookings,
    favorites as accountFavorites,
    notifications as accountNotifications,
} from '@/routes/account';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();

// WHY: `/settings/*`, el sitio publico y el panel comparten este menu. Sin la
// entrada al puesto de trabajo, un admin que abre "Configuración" se queda sin
// vuelta al panel y parece degradado a cliente. El destino se resuelve por
// permiso en `@/config/navigation`, no con un `switch` por rol propio.
//
// `isStaffMember` oculta la zona de viajero a quien el middleware
// `traveler.only` devolveria a su home: enlaces que rebotan, no se muestran.
const { workspace, isStaffMember } = useNavigation();
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
                <Link
                    class="block w-full cursor-pointer"
                    :href="workspace.href"
                >
                    <component :is="workspace.icon" class="mr-2 h-4 w-4" />
                    {{ $t(workspace.label) }}
                </Link>
            </DropdownMenuItem>
        </DropdownMenuGroup>
        <DropdownMenuSeparator />
    </template>
    <DropdownMenuGroup>
        <template v-if="!isStaffMember">
            <DropdownMenuItem :as-child="true">
                <Link
                    class="block w-full cursor-pointer"
                    :href="accountBookings()"
                >
                    <Ticket class="mr-2 h-4 w-4" />
                    {{ $t('Mis reservas') }}
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem :as-child="true">
                <Link
                    class="block w-full cursor-pointer"
                    :href="accountFavorites()"
                >
                    <Heart class="mr-2 h-4 w-4" />
                    {{ $t('Favoritos') }}
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem :as-child="true">
                <Link
                    class="block w-full cursor-pointer"
                    :href="accountNotifications()"
                >
                    <Bell class="mr-2 h-4 w-4" />
                    {{ $t('Notificaciones') }}
                </Link>
            </DropdownMenuItem>
        </template>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ $t('Configuración') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <div class="px-2 py-1.5">
        <p class="mb-1.5 text-xs text-muted-foreground">{{ $t('Idioma') }}</p>
        <LocaleSwitcher variant="tabs" />
    </div>
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
            {{ $t('Cerrar sesión') }}
        </Link>
    </DropdownMenuItem>
</template>
