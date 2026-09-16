<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Globe, Image, LogIn, Mail, Palette, Phone, Upload } from 'lucide-vue-next';
import { computed } from 'vue';
import PlanBadge from '@/components/molecules/PlanBadge.vue';
import PlanChanger from '@/components/molecules/PlanChanger.vue';
import StatusChanger from '@/components/molecules/StatusChanger.vue';
import TenantStatusBadge from '@/components/molecules/TenantStatusBadge.vue';
import AddTenantUserDialog from '@/components/organisms/AddTenantUserDialog.vue';
import ContactInfoEditor from '@/components/organisms/ContactInfoEditor.vue';
import SocialLinksEditor from '@/components/organisms/SocialLinksEditor.vue';
import TenantCommissionForm from '@/components/organisms/TenantCommissionForm.vue';
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
import { formatCurrency } from '@/lib/format';
import { enter as enterTenant } from '@/routes/super-admin/tenants';
import { index as chargesIndex } from '@/routes/super-admin/tenants/charges';
import { update as updateConfiguration } from '@/routes/super-admin/tenants/configuration';
import { update as updatePlan } from '@/routes/super-admin/tenants/plan';
import { update as updateStatus } from '@/routes/super-admin/tenants/status';
import type {
    SuperAdminTenantSummary,
    TenantChargesSummary,
    TenantPlan,
    TenantStatus,
} from '@/types';
import { CURRENCY_VALUES } from '@/types/enums.generated';
import type { TenantConfiguration } from '@/types/tenant';

const CURRENCIES: readonly string[] = CURRENCY_VALUES;

const TIMEZONES = [
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

const props = defineProps<{
    tenant: SuperAdminTenantSummary;
    chargesSummary: TenantChargesSummary;
    roles: string[];
}>();

const page = usePage();

const configuration = computed<TenantConfiguration | null>(
    () => props.tenant.configuration ?? null,
);

const statusForm = useForm<{ status: TenantStatus | null; reason: string | null }>({
    status: null,
    reason: null,
});

const planForm = useForm<{ plan: TenantPlan }>({ plan: props.tenant.plan });

/**
 * WHY: las reglas del servidor no son `sometimes` acá, pero los archivos y los
 * colores solo viajan cuando tienen valor: mandarlos vacíos borraría lo guardado.
 */
const configForm = useForm(() => ({
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
    min_partial_payment_pct: configuration.value?.min_partial_payment_pct ?? 50,
    social_links: { ...(configuration.value?.social_links ?? {}) },
    contact_info: { ...(configuration.value?.contact_info ?? {}) },
    logo: null as File | null,
    favicon: null as File | null,
    hero_image: null as File | null,
}));

function changeStatus(next: TenantStatus, reason: string | null): void {
    statusForm.status = next;
    statusForm.reason = reason;
    statusForm.patch(updateStatus.url(props.tenant.id), { preserveScroll: true });
}

function changePlan(next: TenantPlan): void {
    planForm.plan = next;
    planForm.patch(updatePlan.url(props.tenant.id), { preserveScroll: true });
}

function pickFile(event: Event, target: 'logo' | 'favicon' | 'hero_image'): void {
    const input = event.target as HTMLInputElement;
    configForm[target] = input.files?.[0] ?? null;
}

function submitConfiguration(): void {
    configForm
        .transform((data) => {
            const payload: Record<string, unknown> = {
                tagline: data.tagline,
                description: data.description,
                currency: data.currency,
                timezone: data.timezone,
                locale: data.locale,
                reviews_require_moderation: data.reviews_require_moderation,
                require_traveler_details: data.require_traveler_details,
                min_partial_payment_pct: data.min_partial_payment_pct,
                social_links: data.social_links,
                contact_info: data.contact_info,
            };

            if (data.primary_color) {
                payload.primary_color = data.primary_color;
            }

            if (data.secondary_color) {
                payload.secondary_color = data.secondary_color;
            }

            for (const asset of ['logo', 'favicon', 'hero_image'] as const) {
                if (data[asset]) {
                    payload[asset] = data[asset];
                }
            }

            return payload;
        })
        .post(updateConfiguration.url(props.tenant.id), {
            forceFormData: true,
            preserveScroll: true,
        });
}

const processing = computed(
    () => statusForm.processing || planForm.processing,
);
</script>

<template>
    <div class="space-y-6">
        <header
            class="flex flex-col gap-4 rounded-lg border border-border bg-card p-6 shadow-sm md:flex-row md:items-start md:justify-between"
        >
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-foreground">
                        {{ tenant.name }}
                    </h1>
                    <TenantStatusBadge :status="tenant.status" />
                    <PlanBadge :plan="tenant.plan" />
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ tenant.domain ?? tenant.slug }}
                </p>
                <div class="flex flex-wrap gap-4 text-sm text-muted-foreground">
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

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <form
                    :action="enterTenant.url(tenant.id)"
                    method="post"
                    target="_blank"
                >
                    <input
                        type="hidden"
                        name="_token"
                        :value="page.props.csrfToken"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="!tenant.can_enter"
                    >
                        <LogIn class="mr-1 size-4" />
                        {{ $t('Entrar al panel') }}
                    </Button>
                </form>
                <Button as-child variant="outline" size="sm">
                    <Link :href="chargesIndex.url(tenant.id)">
                        {{ $t('Ver cargos') }}
                    </Link>
                </Button>
                <AddTenantUserDialog
                    :tenant-id="tenant.id"
                    :roles="roles"
                />
            </div>
        </header>

        <section
            class="grid gap-4 rounded-lg border border-border bg-card p-6 shadow-sm md:grid-cols-2"
        >
            <div class="space-y-2">
                <h2
                    class="text-sm font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    {{ $t('Estado de la agencia') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Suspender bloquea el acceso a todos los usuarios. Restablecer reactiva el servicio.',
                        )
                    }}
                </p>
                <StatusChanger
                    :current-status="tenant.status"
                    :processing="processing"
                    @submit="changeStatus"
                />
                <p
                    v-if="statusForm.errors.status"
                    class="text-xs text-destructive"
                >
                    {{ statusForm.errors.status }}
                </p>
            </div>

            <div class="space-y-2">
                <h2
                    class="text-sm font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    {{ $t('Plan asignado') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ $t('Los nuevos límites aplican inmediatamente.') }}
                </p>
                <PlanChanger
                    :current-plan="tenant.plan"
                    :processing="processing"
                    @submit="changePlan"
                />
            </div>
        </section>

        <section class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <p class="text-xs tracking-wider text-muted-foreground uppercase">
                    {{ $t('Usuarios') }}
                </p>
                <p class="text-xl font-semibold text-foreground">
                    {{ tenant.stats.users_count }}
                </p>
            </div>
            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <p class="text-xs tracking-wider text-muted-foreground uppercase">
                    {{ $t('Tours') }}
                </p>
                <p class="text-xl font-semibold text-foreground">
                    {{ tenant.stats.tours_count }}
                </p>
            </div>
            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <p class="text-xs tracking-wider text-muted-foreground uppercase">
                    {{ $t('Reservas (30d)') }}
                </p>
                <p class="text-xl font-semibold text-foreground">
                    {{ tenant.stats.bookings_count_30d }}
                </p>
            </div>
            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <p class="text-xs tracking-wider text-muted-foreground uppercase">
                    {{ $t('Ingresos (30d)') }}
                </p>
                <p class="text-xl font-semibold text-foreground">
                    {{
                        formatCurrency(
                            tenant.stats.revenue_30d,
                            chargesSummary.currency,
                        )
                    }}
                </p>
            </div>
            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <p class="text-xs tracking-wider text-muted-foreground uppercase">
                    {{ $t('Cargos acumulados') }}
                </p>
                <p class="text-xl font-semibold text-foreground">
                    {{
                        formatCurrency(
                            chargesSummary.total_amount,
                            chargesSummary.currency,
                        )
                    }}
                </p>
            </div>
        </section>

        <TenantCommissionForm
            :tenant-id="tenant.id"
            :commission="tenant.commission"
        />

        <section class="rounded-lg border border-border bg-card shadow-sm">
            <div class="border-b border-border px-6 py-4">
                <h2
                    class="flex items-center gap-2 text-lg font-semibold text-foreground"
                >
                    <Palette class="size-5" />
                    {{ $t('Personalización y configuración') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        $t(
                            'Configura la identidad visual, ajustes operativos y contacto de la agencia.',
                        )
                    }}
                </p>
            </div>

            <form class="space-y-8 p-6" @submit.prevent="submitConfiguration">
                <div class="space-y-4">
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <Palette class="size-4" />
                        {{ $t('Identidad visual') }}
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>{{ $t('Color primario') }}</Label>
                            <Input
                                v-model="configForm.primary_color"
                                placeholder="#16a34a"
                                class="font-mono"
                            />
                            <p
                                v-if="configForm.errors.primary_color"
                                class="text-xs text-destructive"
                            >
                                {{ configForm.errors.primary_color }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>{{ $t('Color secundario') }}</Label>
                            <Input
                                v-model="configForm.secondary_color"
                                placeholder="#0f766e"
                                class="font-mono"
                            />
                            <p
                                v-if="configForm.errors.secondary_color"
                                class="text-xs text-destructive"
                            >
                                {{ configForm.errors.secondary_color }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>{{ $t('Eslogan') }}</Label>
                        <Input
                            v-model="configForm.tagline"
                            :placeholder="
                                $t('Ej: Descubre la naturaleza con nosotros')
                            "
                            maxlength="160"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label>{{ $t('Descripción') }}</Label>
                        <Textarea
                            v-model="configForm.description"
                            :placeholder="$t('Descripción de la agencia...')"
                            rows="3"
                            maxlength="2000"
                        />
                    </div>
                </div>

                <div class="space-y-4">
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <Image class="size-4" />
                        {{ $t('Imágenes') }}
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="space-y-2">
                            <Label>{{ $t('Logo') }}</Label>
                            <img
                                v-if="configuration?.logo_url"
                                :src="configuration.logo_url"
                                :alt="tenant.name"
                                class="h-12 w-auto rounded border bg-muted object-contain p-1"
                            />
                            <label
                                class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground transition hover:border-ring hover:text-foreground"
                            >
                                <Upload class="size-4" />
                                {{
                                    configForm.logo
                                        ? configForm.logo.name
                                        : $t('Subir logo')
                                }}
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="(e) => pickFile(e, 'logo')"
                                />
                            </label>
                        </div>

                        <div class="space-y-2">
                            <Label>{{ $t('Favicon') }}</Label>
                            <img
                                v-if="configuration?.favicon_url"
                                :src="configuration.favicon_url"
                                :alt="tenant.name"
                                class="h-8 w-auto rounded border bg-muted object-contain p-1"
                            />
                            <label
                                class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground transition hover:border-ring hover:text-foreground"
                            >
                                <Upload class="size-4" />
                                {{
                                    configForm.favicon
                                        ? configForm.favicon.name
                                        : $t('Subir favicon')
                                }}
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="(e) => pickFile(e, 'favicon')"
                                />
                            </label>
                        </div>

                        <div class="space-y-2">
                            <Label>{{ $t('Imagen principal') }}</Label>
                            <img
                                v-if="configuration?.hero_image_url"
                                :src="configuration.hero_image_url"
                                :alt="tenant.name"
                                class="h-20 w-full rounded border bg-muted object-cover"
                            />
                            <label
                                class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground transition hover:border-ring hover:text-foreground"
                            >
                                <Upload class="size-4" />
                                {{
                                    configForm.hero_image
                                        ? configForm.hero_image.name
                                        : $t('Subir imagen principal')
                                }}
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="(e) => pickFile(e, 'hero_image')"
                                />
                            </label>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3
                        class="flex items-center gap-2 text-sm font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        <Globe class="size-4" />
                        {{ $t('Configuración operativa') }}
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="space-y-2">
                            <Label>{{ $t('Moneda') }}</Label>
                            <Select v-model="configForm.currency">
                                <SelectTrigger>
                                    <SelectValue
                                        :placeholder="$t('Seleccionar moneda')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="code in CURRENCIES"
                                        :key="code"
                                        :value="code"
                                    >
                                        {{ code }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label>{{ $t('Zona horaria') }}</Label>
                            <Select v-model="configForm.timezone">
                                <SelectTrigger>
                                    <SelectValue
                                        :placeholder="$t('Seleccionar zona')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="zone in TIMEZONES"
                                        :key="zone"
                                        :value="zone"
                                    >
                                        {{ zone }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <Label>{{ $t('Idioma') }}</Label>
                            <Select v-model="configForm.locale">
                                <SelectTrigger>
                                    <SelectValue
                                        :placeholder="$t('Seleccionar idioma')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="es">{{
                                        $t('Español')
                                    }}</SelectItem>
                                    <SelectItem value="en">{{
                                        $t('English')
                                    }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>{{ $t('Pago parcial mínimo (%)') }}</Label>
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
                                    {{ $t('Reseñas requieren moderación') }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        $t(
                                            'Las reseñas no se publican hasta que un admin las apruebe.',
                                        )
                                    }}
                                </p>
                            </div>
                            <Switch
                                v-model="
                                    configForm.reviews_require_moderation
                                "
                            />
                        </div>

                        <div
                            class="flex items-center justify-between rounded-md border px-4 py-3"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    {{ $t('Requerir datos de viajeros') }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        $t(
                                            'Solicita nombre y documento de cada viajero al reservar.',
                                        )
                                    }}
                                </p>
                            </div>
                            <Switch
                                v-model="configForm.require_traveler_details"
                            />
                        </div>
                    </div>
                </div>

                <SocialLinksEditor v-model="configForm.social_links" />

                <ContactInfoEditor v-model="configForm.contact_info" />

                <div class="flex justify-end">
                    <Button type="submit" :disabled="configForm.processing">
                        {{
                            configForm.processing
                                ? $t('Guardando…')
                                : $t('Guardar configuración')
                        }}
                    </Button>
                </div>
            </form>
        </section>
    </div>
</template>
