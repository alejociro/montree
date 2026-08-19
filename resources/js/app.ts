import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import { translate, translateChoice } from '@/composables/useTranslations';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) }).use(plugin);

        // WHY: `$t` global en vez de un import por archivo. Son 265 componentes; hacer
        // que cada template dependa de importar el composable correcto es la diferencia
        // entre traducir la app y traducir la mitad. En `<script setup>` sigue usandose
        // `useTranslations()`.
        app.config.globalProperties.$t = translate;
        app.config.globalProperties.$tc = translateChoice;

        app.mount(el!);
    },
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name === 'Landing':
            case name === 'Faq':
                return null;
            case name.startsWith('Policies/'):
                return null;
            case name.startsWith('Errors/'):
                return null;
            case name.startsWith('Onboarding/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('SuperAdmin/'):
                return SuperAdminLayout;
            case name.startsWith('Admin/'):
                return AdminLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
