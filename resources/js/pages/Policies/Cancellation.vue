<script setup lang="ts">
import { useTranslations } from '@/composables/useTranslations';
import PlatformShell from '@/layouts/PlatformShell.vue';

const { t } = useTranslations();

const lastUpdated = t('11 de agosto de 2026');
</script>

<template>
    <PlatformShell :title="$t('Política de cancelación — Montree')">
        <section class="section-cream">
            <div class="container--narrow container">
                <div class="reveal section-header">
                    <span class="eyebrow-pill">{{ $t('Legal') }}</span>
                    <h2 class="section-title">
                        {{ $t('Política de cancelación') }}
                    </h2>
                    <p class="section-sub">
                        {{
                            $t(
                                'Qué ocurre cuando un viajero cancela, cuando la agencia cancela una salida y cómo se calculan los reembolsos.',
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

                    <h3>{{ $t('1. Marco general') }}</h3>
                    <p>
                        {{
                            $t(
                                'Cada agencia define la política de cancelación de sus tours y la publica en la ficha de cada experiencia. Lo que aparece en esa ficha prevalece sobre este documento, que funciona como marco por defecto cuando la agencia no ha definido reglas propias.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('2. Cancelación por parte del viajero') }}</h3>
                    <p>
                        {{
                            $t(
                                'Salvo que la agencia indique lo contrario, aplica esta escala sobre el valor pagado:',
                            )
                        }}
                    </p>
                    <ul>
                        <li>
                            {{ $t('Más de') }}
                            <strong>{{ $t('7 días') }}</strong>
                            {{ $t('antes de la salida: reembolso del 100%.') }}
                        </li>
                        <li>
                            {{ $t('Entre') }}
                            <strong>{{ $t('7 y 2 días') }}</strong>
                            {{ $t('antes de la salida: reembolso del 50%.') }}
                        </li>
                        <li>
                            {{ $t('Menos de') }}
                            <strong>{{ $t('48 horas') }}</strong>
                            {{
                                $t(
                                    'antes de la salida o no presentarse: sin reembolso.',
                                )
                            }}
                        </li>
                    </ul>
                    <p>
                        {{
                            $t(
                                'La solicitud se hace desde «Mis reservas» en el sitio de la agencia o escribiendo directamente a la agencia.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('3. Cancelación por parte de la agencia') }}</h3>
                    <p>
                        {{
                            $t(
                                'Si la agencia cancela una salida — por clima, cupo mínimo no alcanzado, condiciones de seguridad o cualquier otra causa — el viajero puede elegir entre el reembolso del 100% de lo pagado o reprogramar sin costo para otra fecha disponible.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('4. Fuerza mayor') }}</h3>
                    <p>
                        {{
                            $t(
                                'Ante eventos ajenos a las partes (desastres naturales, cierres de vías, disposiciones de autoridad), la agencia ofrecerá reprogramación o bono equivalente. Si ninguna de las dos opciones es viable, se procede al reembolso según la escala del numeral 2.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('5. Cómo y cuándo se reembolsa') }}</h3>
                    <p>
                        {{
                            $t(
                                'El reembolso se devuelve por el mismo medio de pago usado en la compra (Bre-B o PSE). Los tiempos de acreditación dependen de la entidad financiera del viajero. La comisión de Montree no se cobra sobre reservas reembolsadas en su totalidad.',
                            )
                        }}
                    </p>
                    <p class="legal-pending">
                        {{
                            $t(
                                'Pendiente de confirmación: plazo máximo comprometido para la acreditación del reembolso.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('6. Cambios de fecha') }}</h3>
                    <p>
                        {{
                            $t(
                                'Un cambio de fecha solicitado con más de 7 días de anticipación no tiene costo, siempre que haya cupo disponible en la nueva salida. Dentro de los 7 días previos, el cambio queda a criterio de la agencia.',
                            )
                        }}
                    </p>

                    <h3>
                        {{ $t('7. Cancelación de la cuenta de una agencia') }}
                    </h3>
                    <p>
                        {{
                            $t(
                                'Una agencia puede dar de baja su cuenta de Montree cuando quiera, sin penalización ni permanencia mínima, y exportar su información. Las reservas ya confirmadas deben ser honradas o reembolsadas por la agencia antes del cierre.',
                            )
                        }}
                    </p>

                    <h3>{{ $t('8. Contacto') }}</h3>
                    <p>
                        {{
                            $t('Para dudas sobre una cancelación escríbenos a')
                        }}
                        <a href="mailto:hola@montree.co">{{
                            $t('hola@montree.co')
                        }}</a>
                        {{ $t('o revisa la') }}
                        <a href="/politica-de-pago">{{
                            $t('política de pago')
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
</style>
