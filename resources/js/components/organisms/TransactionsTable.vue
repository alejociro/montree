<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import TransactionStatusChip from '@/components/molecules/TransactionStatusChip.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { show as transactionShow } from '@/routes/admin/transactions';
import type { TransactionRow } from '@/types/transaction';

const { t } = useTranslations();

type Props = {
    transactions: TransactionRow[];
};

const props = defineProps<Props>();

function detailUrl(transaction: TransactionRow): string {
    return transactionShow(transaction.id).url;
}

function open(transaction: TransactionRow): void {
    router.visit(detailUrl(transaction));
}

/**
 * Un pago colgado no tiene fecha del autorizador; la de creación es lo único
 * que ubica la transacción en el tiempo.
 */
function occurredAt(transaction: TransactionRow): string {
    const moment = transaction.processed_at ?? transaction.created_at;

    return moment === null ? t('Sin dato') : formatDateTime(moment);
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[52rem] text-sm">
            <thead>
                <tr
                    class="border-b border-border text-left text-xs font-medium text-muted-foreground"
                >
                    <th scope="col" class="px-4 py-3">
                        {{ $t('Referencia') }}
                    </th>
                    <th scope="col" class="w-40 px-4 py-3">
                        {{ $t('Reserva') }}
                    </th>
                    <th scope="col" class="px-4 py-3">
                        {{ $t('Medio de pago') }}
                    </th>
                    <th scope="col" class="px-4 py-3">
                        {{ $t('Estado') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-right">
                        {{ $t('Monto') }}
                    </th>
                    <th scope="col" class="px-4 py-3">
                        {{ $t('Fecha') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="transaction in props.transactions"
                    :key="transaction.id"
                    class="cursor-pointer border-b border-border transition last:border-0 hover:bg-muted/40"
                    @click="open(transaction)"
                >
                    <td class="px-4 py-3">
                        <Link
                            :href="detailUrl(transaction)"
                            class="font-mono text-sm font-medium text-foreground underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            @click.stop
                        >
                            {{ transaction.reference ?? $t('Sin dato') }}
                        </Link>
                        <p
                            v-if="transaction.request_id"
                            class="mt-0.5 font-mono text-xs text-muted-foreground"
                        >
                            {{ transaction.request_id }}
                        </p>
                    </td>
                    <td class="w-40 max-w-40 px-4 py-3">
                        <p
                            class="truncate font-medium text-foreground"
                            :title="
                                transaction.booking?.booking_number ?? undefined
                            "
                        >
                            {{
                                transaction.booking?.booking_number ??
                                $t('Sin dato')
                            }}
                        </p>
                        <p
                            class="truncate text-xs text-muted-foreground"
                            :title="
                                transaction.booking?.holder_name ??
                                transaction.booking?.tour_name ??
                                undefined
                            "
                        >
                            {{
                                transaction.booking?.holder_name ??
                                transaction.booking?.tour_name ??
                                ''
                            }}
                        </p>
                    </td>
                    <td class="px-4 py-3">
                        {{ transaction.gateway_label }}
                    </td>
                    <td class="px-4 py-3">
                        <TransactionStatusChip
                            :status="transaction.status"
                            :label="transaction.status_label"
                            size="sm"
                        />
                    </td>
                    <td
                        class="px-4 py-3 text-right font-semibold whitespace-nowrap tabular-nums"
                    >
                        {{
                            formatCurrency(
                                transaction.amount,
                                transaction.currency,
                            )
                        }}
                    </td>
                    <td
                        class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                    >
                        {{ occurredAt(transaction) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
