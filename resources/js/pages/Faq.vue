<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import PlatformShell from '@/layouts/PlatformShell.vue';

const { t } = useTranslations();

const openFaq = ref<number | null>(0);

const toggleFaq = (index: number) => {
    openFaq.value = openFaq.value === index ? null : index;
};

const faqs = [
    {
        q: t('¿Necesito saber de tecnología para usar Montree?'),
        a: t(
            'No. Si sabes usar WhatsApp y Excel, puedes usar Montree. Está diseñado para operadores de campo, no para programadores.',
        ),
    },
    {
        q: t('¿Cuánto tarda en estar lista mi agencia?'),
        a: t(
            'La mayoría publica su primer tour en menos de 10 minutos. Sin configuraciones complicadas, sin técnicos y sin esperas.',
        ),
    },
    {
        q: t('¿Cómo recibo el dinero de las reservas?'),
        a: t(
            'Tus clientes pagan en línea con Bre-B o PSE y el dinero se transfiere a la cuenta bancaria de tu agencia. Montree descuenta una comisión por reserva confirmada; los detalles están en la política de pago.',
        ),
    },
    {
        q: t('¿Cuánto cobra Montree por reserva?'),
        a: t(
            'Entre 3% y 5% del valor de cada reserva confirmada, según el volumen de tu agencia. No cobramos por reservas canceladas ni por cupos que no se vendieron.',
        ),
    },
    {
        q: t('¿Puedo migrar desde Excel o WhatsApp?'),
        a: t(
            'Sí. Nuestro equipo te ayuda a migrar toda la información sin costo adicional durante los primeros 30 días.',
        ),
    },
    {
        q: t('¿Qué pasa si quiero cancelar?'),
        a: t(
            'Cancelas cuando quieras, sin penalizaciones ni permanencia mínima. Exportas tu información y listo.',
        ),
    },
    {
        q: t('¿Qué pasa si un viajero cancela su reserva?'),
        a: t(
            'Cada agencia define su propia política de cancelación y los reembolsos se procesan según esas reglas. Puedes revisar el marco general en nuestra política de cancelación.',
        ),
    },
];
</script>

<template>
    <PlatformShell :title="$t('Preguntas frecuentes — Montree')">
        <section class="section-pale">
            <div class="container--narrow container">
                <div class="reveal section-header">
                    <span class="eyebrow-pill">{{ $t('FAQ') }}</span>
                    <h2 class="section-title">
                        {{ $t('Preguntas frecuentes') }}
                    </h2>
                    <p class="section-sub">
                        {{
                            $t('Todo lo que necesitas saber antes de empezar.')
                        }}
                    </p>
                </div>
                <div class="faq-list">
                    <div
                        v-for="(faq, i) in faqs"
                        :key="faq.q"
                        class="reveal faq-item"
                        :style="`animation-delay:${i * 55}ms`"
                    >
                        <button class="faq-trigger" @click="toggleFaq(i)">
                            <span>{{ faq.q }}</span>
                            <ChevronDown
                                :class="[
                                    'faq-chevron',
                                    openFaq === i ? 'faq-chevron--open' : '',
                                ]"
                            />
                        </button>
                        <Transition name="faq-slide">
                            <div v-if="openFaq === i" class="faq-answer">
                                {{ faq.a }}
                            </div>
                        </Transition>
                    </div>
                </div>

                <p class="faq-footnote">
                    {{ $t('¿Te quedó una duda que no está aquí?') }}
                    <a href="mailto:hola@montree.co">{{
                        $t('Escríbenos →')
                    }}</a>
                </p>
            </div>
        </section>
    </PlatformShell>
</template>

<style scoped>
/* ════════════════════════════════════════════════════════════
   FAQ
════════════════════════════════════════════════════════════ */
.faq-list {
    display: flex;
    flex-direction: column;
}
.faq-item {
    border-bottom: 1px solid var(--border);
}
.faq-item:first-child {
    border-top: 1px solid var(--border);
}

.faq-trigger {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.375rem 0;
    text-align: left;
    background: transparent;
    border: none;
    cursor: pointer;
    color: var(--text-dark);
    font-size: 0.9375rem;
    font-weight: 600;
    font-family: var(--ff-body);
    gap: 1rem;
    transition: color 0.2s;
}
.faq-trigger:hover {
    color: var(--green-mid);
}

.faq-chevron {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    color: var(--text-muted);
    transition:
        transform 0.3s ease,
        color 0.2s;
}
.faq-chevron--open {
    transform: rotate(180deg);
    color: var(--green-mid);
}

.faq-answer {
    padding-bottom: 1.375rem;
    font-size: 0.9rem;
    line-height: 1.78;
    color: var(--text-muted);
}

.faq-slide-enter-active {
    transition: all 0.28s ease;
    overflow: hidden;
}
.faq-slide-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}
.faq-slide-enter-from,
.faq-slide-leave-to {
    opacity: 0;
    max-height: 0;
    padding-bottom: 0;
}
.faq-slide-enter-to,
.faq-slide-leave-from {
    opacity: 1;
    max-height: 200px;
}

.faq-footnote {
    margin-top: 2.5rem;
    text-align: center;
    font-size: 0.875rem;
    color: var(--text-muted);
}
.faq-footnote a {
    color: var(--green-mid);
    font-weight: 600;
    text-decoration: none;
}
.faq-footnote a:hover {
    text-decoration: underline;
}
</style>
