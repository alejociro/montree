<script setup lang="ts">
import {
    ExternalLink,
    Globe,
    Image,
    Mail,
    Palette,
    Phone,
    Upload,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PlanBadge from '@/components/molecules/PlanBadge.vue';
import PlanChanger from '@/components/molecules/PlanChanger.vue';
import StatusChanger from '@/components/molecules/StatusChanger.vue';
import TenantStatusBadge from '@/components/molecules/TenantStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type {
    SuperAdminTenantSummary,
    TenantPlan,
    TenantStatus,
} from '@/types';
import type { TenantConfiguration } from '@/types/tenant';

const props = defineProps<{
    tenant: SuperAdminTenantSummary;
    processing?: boolean;
}>();

const emit = defineEmits<{
    'status-change': [next: TenantStatus, reason: string | null];
    'plan-change': [next: TenantPlan];
    'configuration-update': [data: FormData];
}>();

function handleStatusSubmit(next: TenantStatus, reason: string | null): void {
    emit('status-change', next, reason);
}

function handlePlanSubmit(next: TenantPlan): void {
    emit('plan-change', next);
}

function formatCurrency(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return Number(value).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });
}

// Configuration form
const config = computed<TenantConfiguration | null>(
    () => props.tenant.configuration ?? null,
);

const configForm = ref({
    primary_color: '',
    secondary_color: '',
    tagline: '',
    description: '',
    currency: 'COP',
    timezone: 'America/Bogota',
    locale: 'es' as 'es' | 'en',
    reviews_require_moderation: true,
    require_traveler_details: true,
    min_partial_payment_pct: 50,
    social_links: {
        instagram: '',
        facebook: '',
        twitter: '',
        youtube: '',
        tiktok: '',
    },
    contact_info: {
        email: '',
        phone: '',
        address: '',
    },
});

const logoFile = ref<File | null>(null);
const faviconFile = ref<File | null>(null);
const heroImageFile = ref<File | null>(null);

watch(
    () => props.tenant,
    () => {
        if (config.value) {
            configForm.value = {
                primary_color: config.value.primary_color ?? '#16a34a',
                secondary_color: config.value.secondary_color ?? '#0f766e',
                tagline: config.value.tagline ?? '',
                description: config.value.description ?? '',
                currency: config.value.currency ?? 'COP',
                timezone: config.value.timezone ?? 'America/Bogota',
                locale: (config.value.locale ?? 'es') as 'es' | 'en',
                reviews_require_moderation:
                    config.value.reviews_require_moderation,
                require_traveler_details: config.value.require_traveler_details,
                min_partial_payment_pct:
                    config.value.min_partial_payment_pct ?? 50,
                social_links: {
                    instagram: config.value.social_links?.instagram ?? '',
                    facebook: config.value.social_links?.facebook ?? '',
                    twitter: config.value.social_links?.twitter ?? '',
                    youtube: config.value.social_links?.youtube ?? '',
                    tiktok: config.value.social_links?.tiktok ?? '',
                },
                contact_info: {
                    email: config.value.contact_info?.email ?? '',
                    phone: config.value.contact_info?.phone ?? '',
                    address: config.value.contact_info?.address ?? '',
                },
            };
        }
    },
    { immediate: true },
);

function onFileChange(
    event: Event,
    target: 'logo' | 'favicon' | 'hero_image',
): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (target === 'logo') {
        logoFile.value = file;
    } else if (target === 'favicon') {
        faviconFile.value = file;
    } else {
        heroImageFile.value = file;
    }
}

function submitConfiguration(): void {
    const formData = new FormData();

    formData.append('primary_color', configForm.value.primary_color);
    formData.append('secondary_color', configForm.value.secondary_color);
    formData.append('tagline', configForm.value.tagline);
    formData.append('description', configForm.value.description);
    formData.append('currency', configForm.value.currency);
    formData.append('timezone', configForm.value.timezone);
    formData.append('locale', configForm.value.locale);
    formData.append(
        'reviews_require_moderation',
        configForm.value.reviews_require_moderation ? '1' : '0',
    );
    formData.append(
        'require_traveler_details',
        configForm.value.require_traveler_details ? '1' : '0',
    );
    formData.append(
        'min_partial_payment_pct',
        String(configForm.value.min_partial_payment_pct),
    );

    const socialLinks = Object.fromEntries(
        Object.entries(configForm.value.social_links).filter(
            ([, v]) => v !== '',
        ),
    );

    if (Object.keys(socialLinks).length > 0) {
        for (const [key, value] of Object.entries(socialLinks)) {
            formData.append(`social_links[${key}]`, value);
        }
    }

    const contactInfo = Object.fromEntries(
        Object.entries(configForm.value.contact_info).filter(
            ([, v]) => v !== '',
        ),
    );

    if (Object.keys(contactInfo).length > 0) {
        for (const [key, value] of Object.entries(contactInfo)) {
            formData.append(`contact_info[${key}]`, value);
        }
    }

    if (logoFile.value) {
        formData.append('logo', logoFile.value);
    }

    if (faviconFile.value) {
        formData.append('favicon', faviconFile.value);
    }

    if (heroImageFile.value) {
        formData.append('hero_image', heroImageFile.value);
    }

    emit('configuration-update', formData);
}

const currencies = [
    { value: 'COP', label: 'COP - Peso colombiano' },
    { value: 'USD', label: 'USD - Dólar americano' },
    { value: 'EUR', label: 'EUR - Euro' },
    { value: 'MXN', label: 'MXN - Peso mexicano' },
    { value: 'ARS', label: 'ARS - Peso argentino' },
    { value: 'PEN', label: 'PEN - Sol peruano' },
    { value: 'CLP', label: 'CLP - Peso chileno' },
    { value: 'BRL', label: 'BRL - Real brasileño' },
];

const timezones = [
    'America/Bogota',
    'America/Mexico_City',
    'America/Buenos_Aires',
    'America/Lima',
    'America/Santiago',
    'America/Sao_Paulo',
    'America/New_York',
    'America/Los_Angeles',
    'Europe/Madrid',
    'UTC',
];
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <header
            class="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:flex-row md:items-start md:justify-between dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <h1
                        class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50"
                    >
                        {{ tenant.name }}
                    </h1>
                    <TenantStatusBadge :status="tenant.status" />
                    <PlanBadge :plan="tenant.plan" />
                </div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ tenant.domain ?? `${tenant.slug}.montree.app` }}
                </p>
                <div
                    class="flex flex-wrap gap-4 text-sm text-zinc-600 dark:text-zinc-300"
                >
                    <span
                        v-if="tenant.contact_email"
                        class="inline-flex items-center gap-1.5"
                    >
                        <Mail class="size-4" />
                        {{ tenant.contact_email }}
                    </span>
                    <span
                        v-if="tenant.contact_phone"
                        class="inline-flex items-center gap-1.5"
                    >
                        <Phone class="size-4" />
                        {{ tenant.contact_phone }}
                    </span>
                </div>
            </div>
        </header>

        <!-- Status & Plan -->
        <section
            class="grid gap-4 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:grid-cols-2 dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="space-y-2">
                <h2
                    class="text-sm font-semibold tracking-wider text-zinc-500 uppercase"
                >
                    Estado del tenant
                </h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    Suspender bloquea el acceso a todos los usuarios.
                    Restablecer reactiva el servicio.
                </p>
                <StatusChanger
                    :current-status="tenant.status"
                    :processing="processing"
                    @submit="handleStatusSubmit"
                />
            </div>

            <div class="space-y-2">
                <h2
                    class="text-sm font-semibold tracking-wider text-zinc-500 uppercase"
                >
                    Plan asignado
                </h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    Los nuevos límites aplican inmediatamente.
                </p>
                <PlanChanger
                    :current-plan="tenant.plan"
                    :processing="processing"
                    @submit="handlePlanSubmit"
                />
            </div>
        </section>

        <!-- Metrics -->
        <section class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div
                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <p class="text-xs tracking-wider text-zinc-500 uppercase">
                    Usuarios
                </p>
                <p
                    class="text-xl font-semibold text-zinc-900 dark:text-zinc-50"
                >
                    {{ tenant.users_count ?? '—' }}
                </p>
            </div>
            <div
                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <p class="text-xs tracking-wider text-zinc-500 uppercase">
                    Tours
                </p>
                <p
                    class="text-xl font-semibold text-zinc-900 dark:text-zinc-50"
                >
                    {{ tenant.tours_count ?? '—' }}
                </p>
            </div>
            <div
                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <p class="text-xs tracking-wider text-zinc-500 uppercase">
                    Reservas (30d)
                </p>
                <p
                    class="text-xl font-semibold text-zinc-900 dark:text-zinc-50"
                >
                    {{ tenant.bookings_count_30d ?? '—' }}
                </p>
            </div>
            <div
                class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <p class="text-xs tracking-wider text-zinc-500 uppercase">
                    Ingresos (30d)
                </p>
                <p
                    class="text-xl font-semibold text-zinc-900 dark:text-zinc-50"
                >
                    {{ formatCurrency(tenant.revenue_30d) }}
                </p>
            </div>
        </section>

        <!-- Configuration / Customization -->
        <section
            class="rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div
                class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800"
            >
                <h2
                    class="flex items-center gap-2 text-lg font-semibold text-zinc-900 dark:text-zinc-50"
                >
                    <Palette class="size-5" />
                    Personalización y configuración
                </h2>
                <p class="mt-1 text-sm text-zinc-500">
                    Configura la identidad visual, ajustes operativos y redes
                    sociales del tenant.
                </p>
            </div>

            <form class="space-y-8 p-6" @submit.prevent="submitConfiguration">
                <!-- Branding -->
                <div class="space-y-4">
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold tracking-wider text-zinc-500 uppercase"
                    >
                        <Palette class="size-4" />
                        Identidad visual
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Color primario</Label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="color"
                                    v-model="configForm.primary_color"
                                    class="h-10 w-14 cursor-pointer rounded border"
                                />
                                <Input
                                    v-model="configForm.primary_color"
                                    placeholder="#16a34a"
                                    class="font-mono"
                                />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Color secundario</Label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="color"
                                    v-model="configForm.secondary_color"
                                    class="h-10 w-14 cursor-pointer rounded border"
                                />
                                <Input
                                    v-model="configForm.secondary_color"
                                    placeholder="#0f766e"
                                    class="font-mono"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>Eslogan</Label>
                        <Input
                            v-model="configForm.tagline"
                            placeholder="Ej: Descubre la naturaleza con nosotros"
                            maxlength="160"
                        />
                        <p class="text-xs text-zinc-400">
                            {{ configForm.tagline.length }}/160 caracteres
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Descripción</Label>
                        <Textarea
                            v-model="configForm.description"
                            placeholder="Descripción de la agencia..."
                            rows="3"
                            maxlength="2000"
                        />
                    </div>
                </div>

                <!-- Images -->
                <div class="space-y-4">
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold tracking-wider text-zinc-500 uppercase"
                    >
                        <Image class="size-4" />
                        Imágenes
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="space-y-2">
                            <Label>Logo</Label>
                            <div class="space-y-2">
                                <img
                                    v-if="config?.logo_url"
                                    :src="config.logo_url"
                                    alt="Logo actual"
                                    class="h-12 w-auto rounded border bg-zinc-50 object-contain p-1"
                                />
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm text-zinc-500 transition hover:border-zinc-400 hover:text-zinc-700"
                                >
                                    <Upload class="size-4" />
                                    {{
                                        logoFile ? logoFile.name : 'Subir logo'
                                    }}
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="(e) => onFileChange(e, 'logo')"
                                    />
                                </label>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>Favicon</Label>
                            <div class="space-y-2">
                                <img
                                    v-if="config?.favicon_url"
                                    :src="config.favicon_url"
                                    alt="Favicon actual"
                                    class="h-8 w-auto rounded border bg-zinc-50 object-contain p-1"
                                />
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm text-zinc-500 transition hover:border-zinc-400 hover:text-zinc-700"
                                >
                                    <Upload class="size-4" />
                                    {{
                                        faviconFile
                                            ? faviconFile.name
                                            : 'Subir favicon'
                                    }}
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="
                                            (e) => onFileChange(e, 'favicon')
                                        "
                                    />
                                </label>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>Imagen principal (Hero)</Label>
                            <div class="space-y-2">
                                <img
                                    v-if="config?.hero_image_url"
                                    :src="config.hero_image_url"
                                    alt="Hero actual"
                                    class="h-20 w-full rounded border bg-zinc-50 object-cover"
                                />
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm text-zinc-500 transition hover:border-zinc-400 hover:text-zinc-700"
                                >
                                    <Upload class="size-4" />
                                    {{
                                        heroImageFile
                                            ? heroImageFile.name
                                            : 'Subir imagen hero'
                                    }}
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="
                                            (e) => onFileChange(e, 'hero_image')
                                        "
                                    />
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Operational settings -->
                <div class="space-y-4">
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold tracking-wider text-zinc-500 uppercase"
                    >
                        <Globe class="size-4" />
                        Configuración operativa
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="space-y-2">
                            <Label>Moneda</Label>
                            <Select v-model="configForm.currency">
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Seleccionar moneda"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="c in currencies"
                                        :key="c.value"
                                        :value="c.value"
                                    >
                                        {{ c.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label>Zona horaria</Label>
                            <Select v-model="configForm.timezone">
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Seleccionar zona"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="tz in timezones"
                                        :key="tz"
                                        :value="tz"
                                    >
                                        {{ tz }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label>Idioma</Label>
                            <Select v-model="configForm.locale">
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Seleccionar idioma"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="es">Español</SelectItem>
                                    <SelectItem value="en">English</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Pago parcial mínimo (%)</Label>
                            <Input
                                v-model.number="
                                    configForm.min_partial_payment_pct
                                "
                                type="number"
                                min="10"
                                max="100"
                                step="5"
                            />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div
                            class="flex items-center justify-between rounded-md border px-4 py-3"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    Reseñas requieren moderación
                                </p>
                                <p class="text-xs text-zinc-500">
                                    Las reseñas no se publican hasta que un
                                    admin las apruebe.
                                </p>
                            </div>
                            <Switch
                                :checked="configForm.reviews_require_moderation"
                                @update:checked="
                                    configForm.reviews_require_moderation =
                                        $event
                                "
                            />
                        </div>

                        <div
                            class="flex items-center justify-between rounded-md border px-4 py-3"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    Requerir datos de viajeros
                                </p>
                                <p class="text-xs text-zinc-500">
                                    Solicita nombre y documento de cada viajero
                                    al reservar.
                                </p>
                            </div>
                            <Switch
                                :checked="configForm.require_traveler_details"
                                @update:checked="
                                    configForm.require_traveler_details = $event
                                "
                            />
                        </div>
                    </div>
                </div>

                <!-- Contact info -->
                <div class="space-y-4">
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold tracking-wider text-zinc-500 uppercase"
                    >
                        <Mail class="size-4" />
                        Información de contacto
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="space-y-2">
                            <Label>Email de contacto</Label>
                            <Input
                                v-model="configForm.contact_info.email"
                                type="email"
                                placeholder="contacto@agencia.com"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Teléfono</Label>
                            <Input
                                v-model="configForm.contact_info.phone"
                                type="tel"
                                placeholder="+57 300 123 4567"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Dirección</Label>
                            <Input
                                v-model="configForm.contact_info.address"
                                placeholder="Calle 123 #45-67, Ciudad"
                            />
                        </div>
                    </div>
                </div>

                <!-- Social links -->
                <div class="space-y-4">
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold tracking-wider text-zinc-500 uppercase"
                    >
                        <ExternalLink class="size-4" />
                        Redes sociales
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Instagram</Label>
                            <Input
                                v-model="configForm.social_links.instagram"
                                placeholder="https://instagram.com/..."
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Facebook</Label>
                            <Input
                                v-model="configForm.social_links.facebook"
                                placeholder="https://facebook.com/..."
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Twitter / X</Label>
                            <Input
                                v-model="configForm.social_links.twitter"
                                placeholder="https://twitter.com/..."
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>YouTube</Label>
                            <Input
                                v-model="configForm.social_links.youtube"
                                placeholder="https://youtube.com/..."
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>TikTok</Label>
                            <Input
                                v-model="configForm.social_links.tiktok"
                                placeholder="https://tiktok.com/..."
                            />
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div
                    class="flex items-center gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800"
                >
                    <Button type="submit" :disabled="processing">
                        {{
                            processing ? 'Guardando…' : 'Guardar configuración'
                        }}
                    </Button>
                </div>
            </form>
        </section>
    </div>
</template>
