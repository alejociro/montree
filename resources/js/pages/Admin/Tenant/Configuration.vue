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
import SocialLinksEditor from '@/components/organisms/SocialLinksEditor.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useApi } from '@/composables/useApi';
import { useTenant } from '@/composables/useTenant';
import type {
    TenantConfigurationPayload,
    TenantLocale,
    TenantSocialLinks,
} from '@/types/tenant';

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
};

const { tenant, configuration } = useTenant();
const api = useApi();

const enterpriseOnlyError = ref<string | null>(null);
const saving = ref(false);
const recentlySaved = ref(false);

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
    };

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

    void api.put(updateConfigAction().url, buildPayload(form.data()), {
        onSuccess: () => {
            toast.success('Configuración guardada.');
            recentlySaved.value = true;
            router.reload({ only: ['tenant'] });
        },
        onError: (errors) => {
            const cssError = errors.custom_css ?? errors.error_code ?? '';

            if (cssError.toLowerCase().includes('enterprise')) {
                enterpriseOnlyError.value =
                    'El CSS personalizado solo está disponible en el plan Enterprise.';

                return;
            }

            form.setError(errors);
            toast.error(
                'No se pudieron guardar los cambios. Revisá los campos marcados.',
            );
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

function resetForm(): void {
    form.reset();
    enterpriseOnlyError.value = null;
    recentlySaved.value = false;
}
</script>

<template>
    <Head title="Configuración del tenant" />

    <div class="px-4 py-6 md:px-8">
        <Heading
            title="Configuración de la agencia"
            description="Personalizá la identidad visual, configuración operativa y enlaces de tu agencia."
        />

        <div v-if="!tenant" class="mt-6">
            <Alert variant="destructive">
                <AlertCircle class="size-4" />
                <AlertTitle>No hay tenant resuelto</AlertTitle>
                <AlertDescription>
                    No se pudo identificar la agencia. Verificá que estás
                    accediendo desde el subdominio correcto.
                </AlertDescription>
            </Alert>
        </div>

        <div v-else class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
            <form class="space-y-10" @submit.prevent="submit">
                <Alert
                    v-if="recentlySaved"
                    class="border-primary/30 bg-primary/5 text-primary"
                >
                    <CheckCircle2 class="size-4" />
                    <AlertTitle>Cambios guardados</AlertTitle>
                    <AlertDescription>
                        Tu nueva configuración ya está activa.
                    </AlertDescription>
                </Alert>

                <Alert v-if="enterpriseOnlyError" variant="destructive">
                    <AlertCircle class="size-4" />
                    <AlertTitle>Función Enterprise</AlertTitle>
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
                        {{ saving ? 'Guardando…' : 'Guardar cambios' }}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="saving || !form.isDirty"
                        @click="resetForm"
                    >
                        Descartar
                    </Button>

                    <span
                        v-if="form.isDirty && !saving"
                        class="text-xs text-muted-foreground"
                    >
                        Tenés cambios sin guardar.
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
