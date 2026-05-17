import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { Tenant, TenantConfiguration, TenantLocale } from '@/types/tenant';

type UseTenantReturn = {
    tenant: ComputedRef<Tenant | null>;
    configuration: ComputedRef<TenantConfiguration | null>;
    isResolved: ComputedRef<boolean>;
    primaryColor: ComputedRef<string | null>;
    secondaryColor: ComputedRef<string | null>;
    currency: ComputedRef<string | null>;
    locale: ComputedRef<TenantLocale | null>;
    displayName: ComputedRef<string>;
};

/**
 * Access the current tenant + configuration injected as shared Inertia props.
 *
 * When the host does not resolve to any tenant, `tenant` and `configuration`
 * are both `null` and `isResolved` returns `false`.
 */
export function useTenant(): UseTenantReturn {
    const page = usePage();

    const tenant = computed<Tenant | null>(
        () => (page.props.tenant as Tenant | null) ?? null,
    );

    const configuration = computed<TenantConfiguration | null>(
        () =>
            (page.props.tenantConfiguration as TenantConfiguration | null) ??
            null,
    );

    const isResolved = computed(() => tenant.value !== null);

    const primaryColor = computed(
        () => configuration.value?.primary_color ?? null,
    );
    const secondaryColor = computed(
        () => configuration.value?.secondary_color ?? null,
    );
    const currency = computed(() => configuration.value?.currency ?? null);
    const locale = computed<TenantLocale | null>(
        () => configuration.value?.locale ?? null,
    );

    const displayName = computed(() => tenant.value?.name ?? 'MONTREE');

    return {
        tenant,
        configuration,
        isResolved,
        primaryColor,
        secondaryColor,
        currency,
        locale,
        displayName,
    };
}
