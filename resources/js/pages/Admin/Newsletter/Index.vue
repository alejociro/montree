<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Loader2, Mail, Send, UserMinus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import KpiCard from '@/components/atoms/KpiCard.vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApi';
import { useTenant } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatNumber } from '@/lib/format';

const { t } = useTranslations();

const api = useApi();
const { displayName: tenantName } = useTenant();

type Subscriber = {
    id: number;
    email: string;
    name: string | null;
    status: 'active' | 'unsubscribed' | string;
    source: string | null;
    subscribed_at: string | null;
    unsubscribed_at: string | null;
};

type Stats = {
    active: number;
    total: number;
    joined_this_month: number;
    left_this_month: number;
};

const subscribers = ref<Subscriber[]>([]);
const stats = ref<Stats | null>(null);
const loading = ref(true);

const subject = ref('');
const bodyHtml = ref('');
const previewText = ref('');
const sending = ref(false);
const testing = ref(false);
const errors = ref<Record<string, string | undefined>>({});
const unsubscribingId = ref<number | null>(null);

/** Longitud que la mayoría de bandejas muestra sin recortar. */
const SUBJECT_HINT = 60;
const PREVIEW_HINT = 90;

const activeCount = computed(() => stats.value?.active ?? 0);
const canSend = computed(
    () =>
        activeCount.value > 0 &&
        subject.value.trim() !== '' &&
        bodyHtml.value.trim() !== '',
);

/**
 * Qué falta para poder enviar, en una frase. El botón deshabilitado decía
 * «Sin suscriptores activos» aunque lo que faltara fuera el asunto.
 */
const blockedReason = computed<string | null>(() => {
    if (activeCount.value === 0) {
        return t(
            'Todavía no hay suscriptores activos a quienes enviar la campaña.',
        );
    }

    if (subject.value.trim() === '') {
        return t('Falta el asunto.');
    }

    if (bodyHtml.value.trim() === '') {
        return t('Falta el cuerpo del correo.');
    }

    return null;
});

const sourceLabels: Record<string, string> = {
    tour: t('Ficha de tour'),
    footer: t('Pie de página'),
    checkout: t('Checkout'),
};

function isActive(subscriber: Subscriber): boolean {
    return subscriber.status === 'active';
}

function statusLabel(subscriber: Subscriber): string {
    return isActive(subscriber) ? t('Activo') : t('De baja');
}

function sourceLabel(subscriber: Subscriber): string {
    if (subscriber.source === null || subscriber.source === '') {
        return t('Sin origen');
    }

    return sourceLabels[subscriber.source] ?? subscriber.source;
}

async function load() {
    loading.value = true;

    try {
        const res = await fetch('/api/v1/admin/newsletter/subscribers', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        subscribers.value = json.data ?? [];
        stats.value = json.stats ?? null;
    } finally {
        loading.value = false;
    }
}

function clearError(field: string): void {
    if (errors.value[field] !== undefined) {
        errors.value = { ...errors.value, [field]: undefined };
    }
}

function payload() {
    return {
        subject: subject.value,
        body_html: bodyHtml.value,
        preview_text: previewText.value,
    };
}

function send() {
    sending.value = true;
    errors.value = {};

    void api.post('/api/v1/admin/newsletter/send', payload(), {
        onSuccess: () => {
            toast.success(t('Campaña encolada'));
            subject.value = '';
            bodyHtml.value = '';
            previewText.value = '';
        },
        onError: (received) => {
            errors.value = received;

            if (received._global !== undefined) {
                toast.error(received._global);
            }
        },
        onFinish: () => {
            sending.value = false;
        },
    });
}

function sendTest() {
    testing.value = true;
    errors.value = {};

    void api.post('/api/v1/admin/newsletter/send-test', payload(), {
        onSuccess: () => {
            toast.success(t('Prueba enviada a tu correo'));
        },
        onError: (received) => {
            errors.value = received;

            if (received._global !== undefined) {
                toast.error(received._global);
            }
        },
        onFinish: () => {
            testing.value = false;
        },
    });
}

function unsubscribe(subscriber: Subscriber) {
    if (unsubscribingId.value !== null) {
        return;
    }

    if (
        !window.confirm(
            t('¿Dar de baja a :email del newsletter?', {
                email: subscriber.email,
            }),
        )
    ) {
        return;
    }

    unsubscribingId.value = subscriber.id;

    void api.patch(
        `/api/v1/admin/newsletter/subscribers/${subscriber.id}/unsubscribe`,
        {},
        {
            onSuccess: () => {
                toast.success(t('Suscriptor dado de baja'));
                void load();
            },
            onError: (e) =>
                toast.error(
                    Object.values(e)[0] ?? t('No se pudo dar de baja.'),
                ),
            onFinish: () => {
                unsubscribingId.value = null;
            },
        },
    );
}

onMounted(load);
</script>

<template>
    <Head :title="$t('Newsletter')" />

    <div class="px-4 py-6 md:px-8">
        <Heading
            :title="$t('Newsletter')"
            :description="
                $t('Escribe la campaña, revísala en la vista previa y envíala.')
            "
        />

        <div class="mt-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard
                :label="$t('Suscriptores activos')"
                :value="formatNumber(stats?.active ?? 0)"
                :detail="$t('reciben las campañas')"
                :loading="loading"
            />
            <KpiCard
                :label="$t('Altas este mes')"
                :value="formatNumber(stats?.joined_this_month ?? 0)"
                :detail="$t('nuevos suscriptores')"
                :loading="loading"
            />
            <KpiCard
                :label="$t('Bajas del mes')"
                :value="formatNumber(stats?.left_this_month ?? 0)"
                :detail="$t('se dieron de baja')"
                :alert="(stats?.left_this_month ?? 0) > 0"
                :loading="loading"
            />
            <KpiCard
                :label="$t('Total histórico')"
                :value="formatNumber(stats?.total ?? 0)"
                :detail="$t('altas de todos los tiempos')"
                :loading="loading"
            />
        </div>

        <div
            class="mt-5 grid items-start gap-5 min-[1180px]:grid-cols-[minmax(0,1fr)_380px]"
        >
            <section
                class="rounded-2xl border border-border bg-card p-4 md:p-5"
            >
                <h2 class="text-lg font-semibold">
                    {{ $t('Redactar campaña') }}
                </h2>

                <div class="mt-4 grid content-start gap-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <Label for="subject">{{ $t('Asunto') }}</Label>
                        <span
                            class="text-[11px] tabular-nums"
                            :class="
                                subject.length > SUBJECT_HINT
                                    ? 'text-brand-warn'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ subject.length }}/{{ SUBJECT_HINT }}
                        </span>
                    </div>
                    <Input
                        id="subject"
                        v-model="subject"
                        maxlength="200"
                        :placeholder="$t('Nuevas salidas al Valle de Cocora')"
                        :aria-invalid="
                            errors.subject !== undefined || undefined
                        "
                        @update:model-value="clearError('subject')"
                    />
                    <InputError :message="errors.subject" />
                </div>

                <div class="mt-4 grid content-start gap-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <Label for="preview">
                            {{ $t('Texto de vista previa') }}
                        </Label>
                        <span
                            class="text-[11px] tabular-nums"
                            :class="
                                previewText.length > PREVIEW_HINT
                                    ? 'text-brand-warn'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ previewText.length }}/{{ PREVIEW_HINT }}
                        </span>
                    </div>
                    <Input
                        id="preview"
                        v-model="previewText"
                        maxlength="200"
                        :placeholder="
                            $t(
                                'La línea que se lee bajo el asunto en la bandeja',
                            )
                        "
                        :aria-invalid="
                            errors.preview_text !== undefined || undefined
                        "
                        @update:model-value="clearError('preview_text')"
                    />
                    <InputError :message="errors.preview_text" />
                </div>

                <div class="mt-4 grid content-start gap-2">
                    <Label for="body">{{ $t('Cuerpo') }}</Label>
                    <Textarea
                        id="body"
                        v-model="bodyHtml"
                        rows="12"
                        :placeholder="
                            $t('Puedes usar HTML sencillo: <p>, <a>, <strong>.')
                        "
                        :aria-invalid="
                            errors.body_html !== undefined || undefined
                        "
                        @update:model-value="clearError('body_html')"
                    />
                    <InputError :message="errors.body_html" />
                </div>

                <p
                    v-if="blockedReason"
                    class="mt-4 rounded-xl bg-brand-warn-50 px-3.5 py-2.5 text-sm text-brand-warn"
                >
                    {{ blockedReason }}
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2.5">
                    <Button :disabled="sending || !canSend" @click="send">
                        <Loader2 v-if="sending" class="size-4 animate-spin" />
                        <Send v-else class="size-4" />
                        {{
                            $tc(
                                'Enviar campaña|Enviar campaña a :count suscriptor|Enviar campaña a :count suscriptores',
                                activeCount,
                            )
                        }}
                    </Button>
                    <Button
                        variant="outline"
                        :disabled="
                            testing ||
                            subject.trim() === '' ||
                            bodyHtml.trim() === ''
                        "
                        @click="sendTest"
                    >
                        <Loader2 v-if="testing" class="size-4 animate-spin" />
                        <Mail v-else class="size-4" />
                        {{ $t('Enviarme una prueba') }}
                    </Button>
                </div>
            </section>

            <!--
              Vista previa pegajosa: acompaña al scroll de la redacción para que
              cada cambio se vea sin perder el campo que se está escribiendo.
            -->
            <aside class="min-[1180px]:sticky min-[1180px]:top-20">
                <div
                    class="rounded-2xl border border-border bg-card p-4 md:p-5"
                >
                    <MonoLabel>{{ $t('Vista previa') }}</MonoLabel>

                    <div
                        class="mt-3 rounded-xl border border-border bg-background p-3.5"
                    >
                        <p class="text-[13px] font-semibold text-foreground">
                            {{ tenantName }}
                        </p>
                        <p class="mt-1.5 text-sm font-semibold">
                            {{ subject || $t('(sin asunto)') }}
                        </p>
                        <p
                            class="mt-0.5 truncate text-xs text-muted-foreground"
                        >
                            {{
                                previewText ||
                                $t('El texto de vista previa va acá.')
                            }}
                        </p>
                    </div>

                    <div
                        class="prose-sm mt-3 max-h-[420px] overflow-y-auto rounded-xl border border-border p-3.5 text-sm break-words"
                    >
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <div v-if="bodyHtml" v-html="bodyHtml" />
                        <p v-else class="text-muted-foreground">
                            {{ $t('El cuerpo del correo se verá acá.') }}
                        </p>
                    </div>
                </div>
            </aside>
        </div>

        <section class="mt-5 rounded-2xl border border-border bg-card">
            <div class="border-b border-border px-4 py-3.5">
                <h2 class="text-base font-semibold">
                    {{ $t('Suscriptores') }}
                </h2>
            </div>

            <div v-if="loading" class="space-y-2 p-4">
                <div
                    v-for="n in 4"
                    :key="n"
                    class="h-12 animate-pulse rounded-lg bg-muted"
                />
            </div>

            <div
                v-else-if="subscribers.length === 0"
                class="m-4 rounded-xl border border-dashed border-input p-10 text-center"
            >
                <p class="text-base font-medium">
                    {{ $t('Sin suscriptores todavía') }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        $t(
                            'El formulario del pie de página y la ficha de cada tour alimentan esta lista.',
                        )
                    }}
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr class="border-b border-border text-left">
                            <MonoLabel as="th" class="px-4 py-3">{{
                                $t('Correo')
                            }}</MonoLabel>
                            <MonoLabel as="th" class="px-4 py-3">{{
                                $t('Alta')
                            }}</MonoLabel>
                            <MonoLabel as="th" class="px-4 py-3">{{
                                $t('Origen')
                            }}</MonoLabel>
                            <MonoLabel as="th" class="px-4 py-3">{{
                                $t('Estado')
                            }}</MonoLabel>
                            <MonoLabel as="th" class="px-4 py-3 text-right">{{
                                $t('Acciones')
                            }}</MonoLabel>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="subscriber in subscribers"
                            :key="subscriber.id"
                            class="border-b border-brand-line-2 last:border-0"
                            :class="isActive(subscriber) ? '' : 'opacity-[.62]'"
                        >
                            <td class="px-4 py-3 font-mono text-[13px]">
                                {{ subscriber.email }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{
                                    subscriber.subscribed_at
                                        ? formatDate(subscriber.subscribed_at)
                                        : '—'
                                }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ sourceLabel(subscriber) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-1 text-[11.5px] font-semibold"
                                    :class="
                                        isActive(subscriber)
                                            ? 'bg-primary-soft text-primary-readable'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ statusLabel(subscriber) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end">
                                    <ActionMenu
                                        v-if="isActive(subscriber)"
                                        variant="ghost"
                                        :label="
                                            $t('Acciones de :name', {
                                                name: subscriber.email,
                                            })
                                        "
                                    >
                                        <DropdownMenuItem
                                            variant="destructive"
                                            :disabled="
                                                unsubscribingId ===
                                                subscriber.id
                                            "
                                            @select="unsubscribe(subscriber)"
                                        >
                                            <UserMinus class="size-4" />
                                            {{ $t('Dar de baja') }}
                                        </DropdownMenuItem>
                                    </ActionMenu>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
