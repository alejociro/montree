<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { update as updateConfiguration } from '@/actions/App/Http/Controllers/Admin/TenantConfigurationPagesController';
import Heading from '@/components/Heading.vue';
import PreviewPanel from '@/components/molecules/PreviewPanel.vue';
import BrandingAssetsEditor from '@/components/organisms/BrandingAssetsEditor.vue';
import BrandingEditor from '@/components/organisms/BrandingEditor.vue';
import ContactInfoEditor from '@/components/organisms/ContactInfoEditor.vue';
import OperationalSettingsForm from '@/components/organisms/OperationalSettingsForm.vue';
import PaymentGatewayForm from '@/components/organisms/PaymentGatewayForm.vue';
import SocialLinksEditor from '@/components/organisms/SocialLinksEditor.vue';
import TermsEditor from '@/components/organisms/TermsEditor.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import { terms as termsRoute } from '@/routes/policies';
import type { PlaceToPayEnvironment } from '@/types/enums.generated';
import type {
    TenantContactInfo,
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
    booking_advance_hours: number | '';
    social_links: TenantSocialLinks;
    contact_info: TenantContactInfo;
    custom_css: string;
    placetopay_login: string;
    placetopay_tran_key: string;
    placetopay_environment: PlaceToPayEnvironment;
    terms_body: string;
    logo: File | null;
    favicon: File | null;
    hero_image: File | null;
    remove_logo: boolean;
    remove_hero_image: boolean;
};

const { tenant, configuration } = useTenant();
const page = usePage();

/**
 * WHY: sin color configurado el campo arranca vacío, no en el verde de MONTREE.
 * Precargar un default hacía que el primer guardado —aunque no se tocara la
 * paleta— escribiera ese color como si la agencia lo hubiera elegido.
 */
const form = useForm<ConfigurationForm>(() => ({
    primary_color: configuration.value?.primary_color ?? '',
    secondary_color: configuration.value?.secondary_color ?? '',
    tagline: configuration.value?.tagline ?? '',
    description: configuration.value?.description ?? '',
    currency: configuration.value?.currency ?? 'COP',
    timezone: configuration.value?.timezone ?? 'America/Bogota',
    locale: configuration.value?.locale ?? 'es',
    reviews_require_moderation:
        configuration.value?.reviews_require_moderation ?? true,
    require_traveler_details:
        configuration.value?.require_traveler_details ?? true,
    booking_advance_hours: configuration.value?.booking_advance_hours ?? '',
    social_links: { ...(configuration.value?.social_links ?? {}) },
    contact_info: { ...(configuration.value?.contact_info ?? {}) },
    custom_css: configuration.value?.custom_css ?? '',
    placetopay_login: configuration.value?.placetopay?.login ?? '',
    // Nunca se precarga: el servidor no devuelve el tranKey guardado.
    placetopay_tran_key: '',
    placetopay_environment:
        configuration.value?.placetopay?.environment ?? 'test',
    // T13: sin términos propios, el editor arranca precargado con el texto
    // por defecto vigente (no vacío) para que personalizar sea editar, no
    // escribir desde cero.
    terms_body: props.terms.body ?? props.terms.default_body,
    logo: null,
    favicon: null,
    hero_image: null,
    remove_logo: false,
    remove_hero_image: false,
}));

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
        booking_advance_hours: form.booking_advance_hours,
    }),
    set: (value) => {
        form.currency = value.currency;
        form.timezone = value.timezone;
        form.locale = value.locale;
        form.reviews_require_moderation = value.reviews_require_moderation;
        form.require_traveler_details = value.require_traveler_details;
        form.booking_advance_hours = value.booking_advance_hours;
    },
});

const gatewayValues = computed({
    get: () => ({
        placetopay_login: form.placetopay_login,
        placetopay_tran_key: form.placetopay_tran_key,
        placetopay_environment: form.placetopay_environment,
    }),
    set: (value) => {
        form.placetopay_login = value.placetopay_login;
        form.placetopay_tran_key = value.placetopay_tran_key;
        form.placetopay_environment = value.placetopay_environment;
    },
});

const socialValues = computed({
    get: () => form.social_links,
    set: (value) => {
        form.social_links = { ...value };
    },
});

const contactValues = computed({
    get: () => form.contact_info,
    set: (value) => {
        form.contact_info = { ...value };
    },
});

/**
 * Las reglas del servidor son `sometimes`: una clave ausente significa «no
 * tocar». Por eso los colores, el tranKey y los archivos solo viajan cuando
 * tienen valor — mandarlos vacíos borraría lo guardado.
 */
function buildPayload(data: ConfigurationForm): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        tagline: data.tagline,
        description: data.description,
        currency: data.currency,
        timezone: data.timezone,
        locale: data.locale,
        reviews_require_moderation: data.reviews_require_moderation,
        require_traveler_details: data.require_traveler_details,
        booking_advance_hours:
            data.booking_advance_hours === ''
                ? null
                : data.booking_advance_hours,
        social_links: data.social_links,
        contact_info: data.contact_info,
        placetopay_login: data.placetopay_login,
        placetopay_environment: data.placetopay_environment,
        terms_body: data.terms_body,
        remove_logo: data.remove_logo,
        remove_hero_image: data.remove_hero_image,
    };

    if (data.primary_color) {
        payload.primary_color = data.primary_color;
    }

    if (data.secondary_color) {
        payload.secondary_color = data.secondary_color;
    }

    if (data.placetopay_tran_key) {
        payload.placetopay_tran_key = data.placetopay_tran_key;
    }

    if (data.custom_css) {
        payload.custom_css = data.custom_css;
    }

    if (data.logo) {
        payload.logo = data.logo;
    }

    if (data.favicon) {
        payload.favicon = data.favicon;
    }

    if (data.hero_image) {
        payload.hero_image = data.hero_image;
    }

    return payload;
}

function submit(): void {
    form.transform(buildPayload).post(updateConfiguration.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            toast.success(
                page.props.flash.success ?? t('Configuración guardada.'),
            );
            form.reset();
        },
        onError: () => {
            toast.error(
                t(
                    'No se pudieron guardar los cambios. Revisa los campos marcados.',
                ),
            );
        },
    });
}

function resetForm(): void {
    form.reset();
    form.clearErrors();
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
                <Alert v-if="form.errors.custom_css" variant="destructive">
                    <AlertCircle class="size-4" />
                    <AlertTitle>{{ $t('CSS personalizado') }}</AlertTitle>
                    <AlertDescription>
                        {{ form.errors.custom_css }}
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

                <BrandingAssetsEditor
                    v-model:logo="form.logo"
                    v-model:favicon="form.favicon"
                    v-model:hero-image="form.hero_image"
                    v-model:remove-logo="form.remove_logo"
                    v-model:remove-hero-image="form.remove_hero_image"
                    :logo-url="configuration?.logo_url ?? null"
                    :favicon-url="configuration?.favicon_url ?? null"
                    :hero-image-url="configuration?.hero_image_url ?? null"
                    :errors="{
                        logo: form.errors.logo,
                        favicon: form.errors.favicon,
                        hero_image: form.errors.hero_image,
                    }"
                />

                <ContactInfoEditor
                    v-model="contactValues"
                    :errors="{
                        address: form.errors['contact_info.address'],
                        email: form.errors['contact_info.email'],
                        phone: form.errors['contact_info.phone'],
                        whatsapp: form.errors['contact_info.whatsapp'],
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
                        placetopay_environment:
                            form.errors.placetopay_environment,
                    }"
                />

                <TermsEditor
                    v-model="form.terms_body"
                    :is-default="props.terms.is_default"
                    :default-body="props.terms.default_body"
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
                        :disabled="form.processing"
                        data-test="save-tenant-configuration"
                    >
                        {{
                            form.processing
                                ? $t('Guardando…')
                                : $t('Guardar cambios')
                        }}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="form.processing || !form.isDirty"
                        @click="resetForm"
                    >
                        {{ $t('Descartar') }}
                    </Button>

                    <span
                        v-if="form.isDirty && !form.processing"
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
                    :description="form.description"
                    :primary-color="form.primary_color"
                    :secondary-color="form.secondary_color"
                />
            </aside>
        </div>
    </div>
</template>
