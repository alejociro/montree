<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import PlatformShell from '@/layouts/PlatformShell.vue';
import { formatCurrency } from '@/lib/format';

const { t } = useTranslations();
const page = usePage();

const lastUpdated = t('29 de septiembre de 2026');

const contactEmail = computed(
    () => page.props.platform?.legal.email ?? 'it@jae-solutions.com',
);

const commissionSchedule = computed(
    () => page.props.platform?.commissionSchedule ?? null,
);

function formatTierRange(from: string, to: string | null): string {
    const currency = commissionSchedule.value?.currency ?? 'COP';
    const fromLabel = formatCurrency(from, currency);

    if (to === null) {
        return t('Desde :from', { from: fromLabel });
    }

    return t(':from – :to', {
        from: fromLabel,
        to: formatCurrency(to, currency),
    });
}

const maxChargeLabel = computed(() => {
    const maxCharge = commissionSchedule.value?.max_charge ?? null;

    if (maxCharge === null) {
        return null;
    }

    const currency = commissionSchedule.value?.currency ?? 'COP';

    return formatCurrency(maxCharge, currency);
});
</script>

<template>
    <PlatformShell :title="$t('Política de pago — Montree')">
        <section class="section-cream">
            <div class="container--narrow container">
                <div class="reveal section-header">
                    <span class="eyebrow-pill">{{ $t('Legal') }}</span>
                    <h1 class="section-title">{{ $t('Política de pago') }}</h1>
                    <p class="section-sub">
                        {{
                            $t(
                                'Cómo se cobran las reservas hechas a través de Montree y cómo recibe su dinero cada agencia.',
                            )
                        }}
                    </p>
                </div>

                <article class="reveal legal-doc">
                    <p class="legal-updated">
                        {{
                            $t('Última actualización: :date', {
                                date: lastUpdated,
                            })
                        }}
                    </p>

                    <h3>{{ $t('1. Alcance') }}</h3>
                    <p>
                        {{
                            $t(
                                'Esta política aplica a los pagos de reservas realizadas en los sitios de las agencias que operan sobre Montree. Cada agencia es la responsable de prestar el servicio turístico contratado; Montree provee la plataforma tecnológica y el procesamiento de los pagos.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('2. Medios de pago') }}</h3>
                    <p>
                        {{
                            $t(
                                'Los viajeros pueden pagar sus reservas mediante:',
                            )
                        }}
                    </p>
                    <ul>
                        <li>
                            <strong>{{ $t('PSE') }}</strong>
                            {{
                                $t(
                                    '— débito directo desde cuenta de ahorros o corriente, procesado por la pasarela PlacetoPay.',
                                )
                            }}
                        </li>
                    </ul>
                    <p>
                        {{
                            $t(
                                'La agencia también puede registrar en el panel pagos recibidos por transferencia o en efectivo.',
                            )
                        }}
                    </p>
                    <p class="legal-pending">
                        {{
                            $t(
                                'Pendiente de confirmación: habilitación de tarjeta de crédito para paquetes de viaje.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('3. Momento del cobro') }}</h3>
                    <p>
                        {{
                            $t(
                                'La reserva queda retenida mientras se completa el pago. Cada agencia define si cobra el valor total por anticipado o un porcentaje como depósito de garantía; ese porcentaje se muestra al viajero antes de confirmar. Si el pago no se completa dentro del tiempo de retención, el cupo se libera automáticamente.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('4. Comisión de Montree') }}</h3>
                    <p>
                        {{
                            $t(
                                'Montree cobra un porcentaje sobre el valor total de cada reserva confirmada, según el rango en que esté la reserva. Si hay un tope máximo por reserva y el porcentaje lo supera, se cobra solo el tope. El porcentaje de tu agencia puede estar definido en tu acuerdo comercial. No se cobra comisión por reservas canceladas, expiradas o no pagadas.',
                            )
                        }}
                    </p>
                    <table
                        v-if="
                            commissionSchedule &&
                            commissionSchedule.tiers.length > 0
                        "
                        class="legal-table"
                    >
                        <thead>
                            <tr>
                                <th>{{ $t('Valor de la reserva') }}</th>
                                <th>{{ $t('Comisión') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="tier in commissionSchedule.tiers"
                                :key="`${tier.from}-${tier.to ?? 'inf'}`"
                            >
                                <td>
                                    {{ formatTierRange(tier.from, tier.to) }}
                                </td>
                                <td>{{ tier.rate }}%</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="maxChargeLabel">
                        {{
                            $t('Tope máximo por reserva: :amount', {
                                amount: maxChargeLabel,
                            })
                        }}
                    </p>

                    <h3>{{ $t('5. Liquidación a la agencia') }}</h3>
                    <p>
                        {{
                            $t(
                                'El dinero recaudado se transfiere a la cuenta bancaria registrada por la agencia, ya descontada la comisión. Cada liquidación viene acompañada de su detalle de reservas para conciliación.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('6. Comprobantes e impuestos') }}</h3>
                    <p>
                        {{
                            $t(
                                'El viajero recibe su comprobante de reserva por correo electrónico al confirmarse el pago. La facturación del servicio turístico y el cumplimiento de las obligaciones tributarias asociadas corresponden a la agencia.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('7. Reembolsos') }}</h3>
                    <p>
                        {{
                            $t(
                                'Los reembolsos a los viajeros se rigen por los términos y la política de cancelación que cada agencia publica en su sitio, y que el viajero acepta al reservar.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('8. Contacto') }}</h3>
                    <p>
                        {{ $t('Para dudas sobre un cobro escríbenos a') }}
                        <a :href="`mailto:${contactEmail}`">{{
                            contactEmail
                        }}</a
                        >.
                    </p>
                </article>
            </div>
        </section>
    </PlatformShell>
</template>

<style scoped>
.legal-doc {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 2.5rem 2rem;
    font-size: 0.9375rem;
    line-height: 1.8;
    color: var(--text-muted);
}
.legal-updated {
    font-size: 0.8125rem;
    color: var(--text-muted);
    opacity: 0.75;
    margin-bottom: 2rem;
}
.legal-doc h3 {
    font-family: var(--ff-display);
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-top: 2rem;
    margin-bottom: 0.6rem;
}
.legal-doc h3:first-of-type {
    margin-top: 0;
}
.legal-doc p + p {
    margin-top: 0.9rem;
}
.legal-doc ul {
    margin: 0.75rem 0 0;
    padding-left: 1.1rem;
    list-style: disc;
}
.legal-doc li + li {
    margin-top: 0.4rem;
}
.legal-doc strong {
    color: var(--text-dark);
    font-weight: 600;
}
.legal-doc a {
    color: var(--green-mid);
    font-weight: 600;
    text-decoration: none;
}
.legal-doc a:hover {
    text-decoration: underline;
}
.legal-pending {
    margin-top: 0.9rem;
    padding: 0.7rem 0.9rem;
    border-left: 3px solid var(--green-light);
    background: var(--green-pale);
    border-radius: 0 8px 8px 0;
    font-size: 0.875rem;
}
.legal-table {
    width: 100%;
    margin-top: 1.1rem;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.legal-table th,
.legal-table td {
    padding: 0.6rem 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
}
.legal-table th {
    color: var(--text-dark);
    font-weight: 600;
}
</style>
