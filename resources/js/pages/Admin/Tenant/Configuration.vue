<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { update as updateConfigAction } from '@/actions/App/Http/Controllers/Api/V1/Admin/TenantConfigurationController';
import Heading from '@/components/Heading.vue';
import PreviewPanel from '@/components/molecules/PreviewPanel.vue';
import BrandingEditor from '@/components/organisms/BrandingEditor.vue';
import OperationalSettingsForm from '@/components/organisms/OperationalSettingsForm.vue';
import PaymentGatewayForm from '@/components/organisms/PaymentGatewayForm.vue';
import SocialLinksEditor from '@/components/organisms/SocialLinksEditor.vue';
import TermsEditor from '@/components/organisms/TermsEditor.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useApi } from '@/composables/useApi';
import { useTenant } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import { terms as termsRoute } from '@/routes/policies';
import type {
    TenantConfigurationPayload,
    TenantLocale,
    TenantSocialLinks,
    TenantTerms,
} from '@/types/tenant';

const { t } = useTranslations();

type Props = {
    terms: TenantTerms;
};

const props = defineProps<Props>();

type ConfigurationForm = {
    primary_color: string;
    secondary_color: string;
    tagline: string;
    description: string;
    currency: string;
    timezone: string;
    locale: TenantLocale;
    reviews_require_moderation: boolean;
    require_traveler_details: boolean;
    social_links: TenantSocialLinks;
    custom_css: string;
    placetopay_login: string;
    placetopay_tran_key: string;
    placetopay_url: string;
    terms_body: string;
};

type UpdateConfigurationResponse = {
    data: {
        configuration: {
            terms_body: string | null;
            terms_is_default: boolean;
        };
    };
};

const { tenant, configuration } = useTenant();
const api = useApi();

const enterpriseOnlyError = ref<string | null>(null);
const saving = ref(false);
const recentlySaved = ref(false);
// WHY: el cuerpo de los terminos no viaja en la prop compartida
// `tenantConfiguration` (pesa hasta 20.000 caracteres); llega como prop de esta
// pagina y la respuesta del PUT devuelve el estado actualizado.
const termsIsDefault = ref(props.terms.is_default);

const isEnterprise = computed(() => tenant.value?.plan === 'enterprise');

const initialValues: ConfigurationForm = {
    primary_color: configuration.value?.primary_color ?? '#16a34a',
    secondary_color: configuration.value?.secondary_color ?? '#0f766e',
    tagline: configuration.value?.tagline ?? '',
    description: configuration.value?.description ?? '',
    currency: configuration.value?.currency ?? 'COP',
    timezone: configuration.value?.timezone ?? 'America/Bogota',
    locale: configuration.value?.locale ?? 'es',
    reviews_require_moderation:
        configuration.value?.reviews_require_moderation ?? true,
    require_traveler_details:
        configuration.value?.require_traveler_details ?? true,
    social_links: { ...(configuration.value?.social_links ?? {}) },
    custom_css: configuration.value?.custom_css ?? '',
    placetopay_login: configuration.value?.placetopay?.login ?? '',
    // Nunca se precarga: el servidor no devuelve el tranKey guardado.
    placetopay_tran_key: '',
    placetopay_url: configuration.value?.placetopay?.url ?? '',
    terms_body: props.terms.body ?? '',
};

const form = useForm<ConfigurationForm>(() => ({ ...initialValues }));

const brandingValues = computed({
    get: () => ({
        primary_color: form.primary_color,
        secondary_color: form.secondary_color,
        tagline: form.tagline,
        description: form.description,
    }),
    set: (value) => {
        form.primary_color = value.primary_color;
        form.secondary_color = value.secondary_color;
        form.tagline = value.tagline;
        form.description = value.description;
    },
});

const operationalValues = computed({
    get: () => ({
        currency: form.currency,
        timezone: form.timezone,
        locale: form.locale,
        reviews_require_moderation: form.reviews_require_moderation,
        require_traveler_details: form.require_traveler_details,
    }),
    set: (value) => {
        form.currency = value.currency;
        form.timezone = value.timezone;
        form.locale = value.locale;
        form.reviews_require_moderation = value.reviews_require_moderation;
        form.require_traveler_details = value.require_traveler_details;
    },
});

const gatewayValues = computed({
    get: () => ({
        placetopay_login: form.placetopay_login,
        placetopay_tran_key: form.placetopay_tran_key,
        placetopay_url: form.placetopay_url,
    }),
    set: (value) => {
        form.placetopay_login = value.placetopay_login;
        form.placetopay_tran_key = value.placetopay_tran_key;
        form.placetopay_url = value.placetopay_url;
    },
});

const socialValues = computed({
    get: () => form.social_links,
    set: (value) => {
        form.social_links = { ...value };
    },
});

function buildPayload(data: ConfigurationForm): TenantConfigurationPayload {
    const payload: TenantConfigurationPayload = {
        primary_color: data.primary_color || null,
        secondary_color: data.secondary_color || null,
        currency: data.currency || null,
        timezone: data.timezone || null,
        locale: data.locale,
        tagline: data.tagline || null,
        description: data.description || null,
        social_links: Object.keys(data.social_links).length
            ? data.social_links
            : null,
        reviews_require_moderation: data.reviews_require_moderation,
        require_traveler_details: data.require_traveler_details,
        placetopay_login: data.placetopay_login || null,
        placetopay_url: data.placetopay_url || null,
        terms_body: data.terms_body.trim() ? data.terms_body : null,
    };

    // Solo viaja cuando el admin escribió uno nuevo: mandarlo vacío borraria el guardado.
    if (data.placetopay_tran_key) {
        payload.placetopay_tran_key = data.placetopay_tran_key;
    }

    if (isEnterprise.value && data.custom_css) {
        payload.custom_css = data.custom_css;
    }

    return payload;
}

function submit(): void {
    enterpriseOnlyError.value = null;
    recentlySaved.value = false;
    form.clearErrors();
    saving.value = true;

    void api.put<UpdateConfigurationResponse>(
        updateConfigAction().url,
        buildPayload(form.data()),
        {
            onSuccess: (response) => {
                toast.success(t('Configuración guardada.'));
                recentlySaved.value = true;
                termsIsDefault.value =
                    response?.data.configuration.terms_is_default ??
                    termsIsDefault.value;
                // WHY: la marca del tenant sale de `tenantConfiguration`, no de
                // `tenant`. Recargar solo `tenant` dejaba los colores viejos en las
                // variables CSS hasta el siguiente refresco completo.
                router.reload({ only: ['tenant', 'tenantConfiguration'] });
            },
            onError: (errors) => {
                const cssError = errors.custom_css ?? errors.error_code ?? '';

                if (cssError.toLowerCase().includes('enterprise')) {
                    enterpriseOnlyError.value = t(
                        'El CSS personalizado solo está disponible en el plan Enterprise.',
                    );

                    return;
                }

                form.setError(errors);
                toast.error(
                    t(
                        'No se pudieron guardar los cambios. Revisa los campos marcados.',
                    ),
                );
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function resetForm(): void {
    form.reset();
    enterpriseOnlyError.value = null;
    recentlySaved.value = false;
}
</script>

<template>
    <Head :title="$t('Configuración del tenant')" />

    <div class="px-4 py-6 md:px-8">
        <Heading
            :title="$t('Configuración de la agencia')"
            :description="
                $t(
                    'Personaliza la identidad visual, configuración operativa y enlaces de tu agencia.',
                )
            "
        />

        <div v-if="!tenant" class="mt-6">
            <Alert variant="destructive">
                <AlertCircle class="size-4" />
                <AlertTitle>{{ $t('No hay tenant resuelto') }}</AlertTitle>
                <AlertDescription>
                    {{
                        $t(
                            'No se pudo identificar la agencia. Verifica que estás accediendo desde el subdominio correcto.',
                        )
                    }}
                </AlertDescription>
            </Alert>
        </div>

        <div v-else class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
            <form class="space-y-10" @submit.prevent="submit">
                <Alert
                    v-if="recentlySaved"
                    class="border-primary/30 bg-primary/5 text-primary-readable"
                >
                    <CheckCircle2 class="size-4" />
                    <AlertTitle>{{ $t('Cambios guardados') }}</AlertTitle>
                    <AlertDescription>
                        {{ $t('Tu nueva configuración ya está activa.') }}
                    </AlertDescription>
                </Alert>

                <Alert v-if="enterpriseOnlyError" variant="destructive">
                    <AlertCircle class="size-4" />
                    <AlertTitle>{{ $t('Función Enterprise') }}</AlertTitle>
                    <AlertDescription>
                        {{ enterpriseOnlyError }}
                    </AlertDescription>
                </Alert>

                <BrandingEditor
                    v-model="brandingValues"
                    :errors="{
                        primary_color: form.errors.primary_color,
                        secondary_color: form.errors.secondary_color,
                        tagline: form.errors.tagline,
                        description: form.errors.description,
                    }"
                />

                <OperationalSettingsForm
                    v-model="operationalValues"
                    :errors="{
                        currency: form.errors.currency,
                        timezone: form.errors.timezone,
                        locale: form.errors.locale,
                    }"
                />

                <PaymentGatewayForm
                    v-model="gatewayValues"
                    :tran-key-set="
                        configuration?.placetopay?.tran_key_set ?? false
                    "
                    :errors="{
                        placetopay_login: form.errors.placetopay_login,
                        placetopay_tran_key: form.errors.placetopay_tran_key,
                        placetopay_url: form.errors.placetopay_url,
                    }"
                />

                <TermsEditor
                    v-model="form.terms_body"
                    :is-default="termsIsDefault"
                    :public-url="termsRoute.url()"
                    :error="form.errors.terms_body"
                />

                <SocialLinksEditor
                    v-model="socialValues"
                    :errors="{
                        instagram: form.errors['social_links.instagram'],
                        facebook: form.errors['social_links.facebook'],
                        twitter: form.errors['social_links.twitter'],
                        youtube: form.errors['social_links.youtube'],
                        tiktok: form.errors['social_links.tiktok'],
                    }"
                />

                <div class="flex items-center gap-3 border-t border-input pt-6">
                    <Button
                        type="submit"
                        :disabled="saving"
                        data-test="save-tenant-configuration"
                    >
                        {{ saving ? $t('Guardando…') : $t('Guardar cambios') }}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="saving || !form.isDirty"
                        @click="resetForm"
                    >
                        {{ $t('Descartar') }}
                    </Button>

                    <span
                        v-if="form.isDirty && !saving"
                        class="text-xs text-muted-foreground"
                    >
                        {{ $t('Tienes cambios sin guardar.') }}
                    </span>
                </div>
            </form>

            <aside>
                <PreviewPanel
                    :tenant-name="tenant.name"
                    :tagline="form.tagline"
                    :primary-color="form.primary_color"
                    :secondary-color="form.secondary_color"
                />
            </aside>
        </div>
    </div>
</template>
