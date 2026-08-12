<script setup lang="ts">
import PlatformShell from '@/layouts/PlatformShell.vue';

const lastUpdated = '11 de agosto de 2026';
</script>

<template>
    <PlatformShell title="Política de pago — Montree">
        <section class="section-cream">
            <div class="container--narrow container">
                <div class="reveal section-header">
                    <span class="eyebrow-pill">Legal</span>
                    <h2 class="section-title">Política de pago</h2>
                    <p class="section-sub">
                        Cómo se cobran las reservas hechas a través de Montree y
                        cómo recibe su dinero cada agencia.
                    </p>
                </div>

                <article class="reveal legal-doc">
                    <p class="legal-updated">
                        Última actualización: {{ lastUpdated }}
                    </p>

                    <h3>1. Alcance</h3>
                    <p>
                        Esta política aplica a los pagos de reservas realizadas
                        en los sitios de las agencias que operan sobre Montree.
                        Cada agencia es la responsable de prestar el servicio
                        turístico contratado; Montree provee la plataforma
                        tecnológica y el procesamiento de los pagos.
                    </p>

                    <h3>2. Medios de pago</h3>
                    <p>
                        Los viajeros pueden pagar sus reservas mediante:
                    </p>
                    <ul>
                        <li>
                            <strong>Bre-B</strong> — transferencias inmediatas
                            entre entidades financieras colombianas.
                        </li>
                        <li>
                            <strong>PSE</strong> — débito directo desde cuenta
                            de ahorros o corriente.
                        </li>
                    </ul>
                    <p class="legal-pending">
                        Pendiente de confirmación: habilitación de tarjeta de
                        crédito para paquetes de viaje.
                    </p>

                    <h3>3. Momento del cobro</h3>
                    <p>
                        La reserva queda retenida mientras se completa el pago.
                        Cada agencia define si cobra el valor total por
                        anticipado o un porcentaje como depósito de garantía;
                        ese porcentaje se muestra al viajero antes de confirmar.
                        Si el pago no se completa dentro del tiempo de
                        retención, el cupo se libera automáticamente.
                    </p>

                    <h3>4. Comisión de Montree</h3>
                    <p>
                        Montree cobra a la agencia una comisión de entre el
                        <strong>3% y el 5%</strong> sobre el valor de cada
                        reserva confirmada. El porcentaje exacto depende del
                        volumen mensual de la agencia y queda establecido en su
                        contrato. No se cobra comisión por reservas canceladas,
                        expiradas o no pagadas.
                    </p>
                    <p class="legal-pending">
                        Pendiente de confirmación: tarifa mensual de
                        infraestructura para agencias pequeñas.
                    </p>

                    <h3>5. Liquidación a la agencia</h3>
                    <p>
                        El dinero recaudado se transfiere a la cuenta bancaria
                        registrada por la agencia, ya descontada la comisión.
                        Cada liquidación viene acompañada de su detalle de
                        reservas para conciliación.
                    </p>

                    <h3>6. Comprobantes e impuestos</h3>
                    <p>
                        El viajero recibe su comprobante de reserva por correo
                        electrónico al confirmarse el pago. La facturación del
                        servicio turístico y el cumplimiento de las
                        obligaciones tributarias asociadas corresponden a la
                        agencia.
                    </p>

                    <h3>7. Reembolsos</h3>
                    <p>
                        Los reembolsos se rigen por la
                        <a href="/politica-de-cancelacion">
                            política de cancelación
                        </a>
                        y por las condiciones particulares que publique cada
                        agencia en su tour.
                    </p>

                    <h3>8. Contacto</h3>
                    <p>
                        Para dudas sobre un cobro escríbenos a
                        <a href="mailto:hola@montree.co">hola@montree.co</a>.
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
</style>
