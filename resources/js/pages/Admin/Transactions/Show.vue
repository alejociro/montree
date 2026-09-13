<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import CopyableValue from '@/components/molecules/CopyableValue.vue';
import TransactionStatusChip from '@/components/molecules/TransactionStatusChip.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    formatCurrency,
    formatDateTime,
    formatBookingStatus,
} from '@/lib/format';
import {
    index as transactionsIndex,
    query as queryTransaction,
} from '@/routes/admin/transactions';
import type {
    TransactionAbilities,
    TransactionDetail,
} from '@/types/transaction';

const { t } = useTranslations();

type Props = {
    transaction: TransactionDetail;
    can: TransactionAbilities;
};

const props = defineProps<Props>();

const querying = ref(false);

/** Un pago manual no tiene sesión de pasarela: no se pintan campos vacíos. */
const isManual = computed(() => props.transaction.gateway === 'manual');

/**
 * La regla de si una transacción se puede reconsultar la decide el backend
 * (`is_queryable`); acá solo se obedece, junto al permiso.
 */
const canQuery = computed(
    () => props.can.query && props.transaction.is_queryable,
);

const departureLink = computed(() => {
    const tourDateId = props.transaction.booking?.tour_date_id;

    return tourDateId === null || tourDateId === undefined
        ? null
        : transactionsIndex.url({ query: { tour_date_id: tourDateId } });
});

function moment(value: string | null): string {
    return value === null ? t('Sin dato') : formatDateTime(value);
}

function run(): void {
    if (querying.value || !canQuery.value) {
        return;
    }

    router.post(
        queryTransaction(props.transaction.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                querying.value = true;
            },
            onFinish: () => {
                querying.value = false;
            },
            // El backend responde `back()` con flash (`success` / `error`) y
            // nadie lo levanta de forma global: acá se convierte en toast.
            onSuccess: (visit) => {
                const flash = visit.props.flash;

                if (flash.error) {
                    toast.error(flash.error);

                    return;
                }

                if (flash.success) {
                    toast.success(flash.success);
                }
            },
            onError: () => {
                toast.error(t('No pudimos consultar el pago en la pasarela.'));
            },
        },
    );
}
</script>

<template>
    <div class="space-y-6">
        <Head
            :title="
                $t('Transacción :reference', {
                    reference:
                        props.transaction.reference ?? props.transaction.id,
                })
            "
        />

        <div class="space-y-3">
            <Link
                :href="transactionsIndex().url"
                class="inline-flex items-center gap-1.5 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ $t('Volver a transacciones') }}
            </Link>

            <header class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <h1
                        class="font-mono text-2xl font-semibold text-foreground"
                    >
                        {{
                            props.transaction.reference ?? $t('Sin referencia')
                        }}
                    </h1>
                    <div class="flex flex-wrap items-center gap-2">
                        <TransactionStatusChip
                            :status="props.transaction.status"
                            :label="props.transaction.status_label"
                        />
                        <span class="text-sm text-muted-foreground">
                            {{ props.transaction.gateway_label }} ·
                            {{ props.transaction.type_label }}
                        </span>
                    </div>
                </div>

                <Button v-if="canQuery" :disabled="querying" @click="run">
                    <RefreshCw
                        class="size-4"
                        :class="querying ? 'animate-spin' : ''"
                        aria-hidden="true"
                    />
                    {{
                        querying
                            ? $t('Consultando…')
                            : $t('Consultar en la pasarela')
                    }}
                </Button>
            </header>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <section
                class="space-y-4 rounded-xl border border-border bg-card p-5 lg:col-span-2"
            >
                <MonoLabel>{{ $t('Transacción') }}</MonoLabel>

                <dl class="grid gap-3 sm:grid-cols-2">
                    <CopyableValue
                        :label="$t('Referencia')"
                        :value="props.transaction.reference"
                    />
                    <CopyableValue
                        :label="$t('requestId')"
                        :value="props.transaction.request_id"
                    />
                    <CopyableValue
                        :label="$t('Referencia interna')"
                        :value="props.transaction.internal_reference"
                    />
                    <CopyableValue
                        :label="$t('Autorización')"
                        :value="props.transaction.authorization"
                    />
                    <CopyableValue
                        :label="$t('Recibo')"
                        :value="props.transaction.receipt"
                    />
                </dl>

                <dl
                    class="grid gap-3 border-t border-brand-line-2 pt-4 sm:grid-cols-2"
                >
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-sm text-muted-foreground">
                            {{ $t('Monto') }}
                        </dt>
                        <dd class="text-sm font-semibold tabular-nums">
                            {{
                                formatCurrency(
                                    props.transaction.amount,
                                    props.transaction.currency,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-sm text-muted-foreground">
                            {{ $t('Fecha del autorizador') }}
                        </dt>
                        <dd class="text-sm">
                            {{ moment(props.transaction.processed_at) }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-sm text-muted-foreground">
                            {{ $t('Creada') }}
                        </dt>
                        <dd class="text-sm">
                            {{ moment(props.transaction.created_at) }}
                        </dd>
                    </div>
                    <div
                        v-if="props.transaction.session_expires_at"
                        class="flex items-start justify-between gap-3"
                    >
                        <dt class="text-sm text-muted-foreground">
                            {{ $t('La sesión vence') }}
                        </dt>
                        <dd class="text-sm">
                            {{ moment(props.transaction.session_expires_at) }}
                        </dd>
                    </div>
                </dl>

                <!-- Pasarela: un pago manual no tiene nada de esto (contracts.md). -->
                <div
                    v-if="!isManual"
                    class="grid gap-3 border-t border-brand-line-2 pt-4 sm:grid-cols-2"
                >
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-sm text-muted-foreground">
                            {{ $t('Estado en la pasarela') }}
                        </dt>
                        <dd class="font-mono text-sm">
                            {{
                                props.transaction.gateway_status ??
                                $t('Sin dato')
                            }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-sm text-muted-foreground">
                            {{ $t('Respuesta') }}
                        </dt>
                        <dd class="text-right text-sm">
                            {{
                                props.transaction.status_message ??
                                $t('Sin dato')
                            }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-sm text-muted-foreground">
                            {{ $t('Franquicia') }}
                        </dt>
                        <dd class="text-sm">
                            {{ props.transaction.franchise ?? $t('Sin dato') }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-sm text-muted-foreground">
                            {{ $t('Medio') }}
                        </dt>
                        <dd class="text-sm">
                            {{
                                props.transaction.payment_method_name ??
                                props.transaction.payment_method ??
                                $t('Sin dato')
                            }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-sm text-muted-foreground">
                            {{ $t('Emisor') }}
                        </dt>
                        <dd class="text-sm">
                            {{
                                props.transaction.issuer_name ?? $t('Sin dato')
                            }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-sm text-muted-foreground">
                            {{ $t('Últimos dígitos') }}
                        </dt>
                        <dd class="font-mono text-sm">
                            {{
                                props.transaction.last_digits ?? $t('Sin dato')
                            }}
                        </dd>
                    </div>
                </div>

                <p v-else class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Pago registrado a mano: la referencia es la que escribió quien lo cargó.',
                        )
                    }}
                </p>
            </section>

            <section
                class="space-y-4 rounded-xl border border-border bg-card p-5"
            >
                <MonoLabel>{{ $t('Reserva y salida') }}</MonoLabel>

                <template v-if="props.transaction.booking">
                    <dl class="grid gap-3">
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-sm text-muted-foreground">
                                {{ $t('Reserva') }}
                            </dt>
                            <dd class="font-mono text-sm">
                                {{
                                    props.transaction.booking.booking_number ??
                                    $t('Sin dato')
                                }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-sm text-muted-foreground">
                                {{ $t('Titular') }}
                            </dt>
                            <dd class="text-right text-sm">
                                {{
                                    props.transaction.booking.holder_name ??
                                    $t('Sin dato')
                                }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-sm text-muted-foreground">
                                {{ $t('Correo electrónico') }}
                            </dt>
                            <dd class="text-right text-sm break-all">
                                {{
                                    props.transaction.booking.holder_email ??
                                    $t('Sin dato')
                                }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-sm text-muted-foreground">
                                {{ $t('Estado de la reserva') }}
                            </dt>
                            <dd class="text-sm">
                                {{
                                    formatBookingStatus(
                                        props.transaction.booking.status,
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-sm text-muted-foreground">
                                {{ $t('Tour') }}
                            </dt>
                            <dd class="text-right text-sm">
                                {{
                                    props.transaction.booking.tour_name ??
                                    $t('Sin dato')
                                }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-sm text-muted-foreground">
                                {{ $t('Salida') }}
                            </dt>
                            <dd class="text-right text-sm">
                                {{
                                    moment(props.transaction.booking.starts_at)
                                }}
                            </dd>
                        </div>
                    </dl>

                    <dl class="grid gap-3 border-t border-brand-line-2 pt-4">
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-sm text-muted-foreground">
                                {{ $t('Total de la reserva') }}
                            </dt>
                            <dd class="text-sm tabular-nums">
                                {{
                                    formatCurrency(
                                        props.transaction.booking.total_amount,
                                        props.transaction.currency,
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-sm text-muted-foreground">
                                {{ $t('Abonado') }}
                            </dt>
                            <dd class="text-sm tabular-nums">
                                {{
                                    formatCurrency(
                                        props.transaction.booking.paid_amount,
                                        props.transaction.currency,
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-sm text-muted-foreground">
                                {{ $t('Saldo') }}
                            </dt>
                            <dd class="text-sm font-semibold tabular-nums">
                                {{
                                    formatCurrency(
                                        props.transaction.booking.due_amount,
                                        props.transaction.currency,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>

                    <Link
                        v-if="departureLink"
                        :href="departureLink"
                        class="inline-flex text-sm text-primary underline-offset-4 hover:underline"
                    >
                        {{ $t('Ver transacciones de esta salida') }}
                    </Link>
                </template>

                <p v-else class="text-sm text-muted-foreground">
                    {{ $t('Esta transacción no tiene una reserva asociada.') }}
                </p>
            </section>
        </div>
    </div>
</template>
