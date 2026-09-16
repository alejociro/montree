import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { Currency } from '@/types/enums.generated';
import type { Tenant, TenantConfiguration, TenantLocale } from '@/types/tenant';

/**
 * Moneda de la plataforma cuando el host no resuelve a ningún tenant. Espeja
 * `App\Enums\Currency::FALLBACK`: nada del lado del tenant formatea importes
 * con una moneda inventada.
 */
export const FALLBACK_CURRENCY: Currency = 'COP';

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

/**
 * La moneda con la que se formatea TODO importe vivo del tenant (catálogo,
 * salidas, tarifas). Las reservas y los pagos históricos usan la suya, que
 * viaja en el propio recurso.
 *
 * WHY: hay funciones puras de `lib/` que formatean importes fuera de un
 * `setup()`. `usePage()` es un accessor de módulo, así que la versión llana
 * también sirve ahí y las dos leen la misma prop compartida.
 */
export function currentTenantCurrency(): Currency {
    const configuration = usePage().props
        .tenantConfiguration as TenantConfiguration | null;

    return (configuration?.currency as Currency) ?? FALLBACK_CURRENCY;
}

export function useTenantCurrency(): ComputedRef<Currency> {
    return computed(currentTenantCurrency);
}
