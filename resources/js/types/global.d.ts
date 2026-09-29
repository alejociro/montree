import type { ModuleFlags } from '@/composables/useModules';
import type { Auth } from '@/types/auth';
import type {
    LocaleOption,
    PluralTranslator,
    Translator,
} from '@/types/locale';
import type { PlatformInfo } from '@/types/platform';
import type { Tenant, TenantConfiguration } from '@/types/tenant';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            locale: string;
            locales: LocaleOption[];
            translations: Record<string, string>;
            modules: ModuleFlags;
            sidebarOpen: boolean;
            csrfToken: string;
            tenant: Tenant | null;
            tenantConfiguration: TenantConfiguration | null;
            platform: PlatformInfo | null;
            flash: {
                success?: string;
                error?: string;
            };
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $t: Translator;
        $tc: PluralTranslator;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
