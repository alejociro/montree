<script setup lang="ts">
import { computed } from 'vue';
import type { LegalSection } from '@/components/organisms/LegalDocument.vue';
import LegalDocument from '@/components/organisms/LegalDocument.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    session: {
        cookie: string;
        lifetimeMinutes: number;
    };
}>();

const { t } = useTranslations();

const cookies = computed(() => [
    {
        name: props.session.cookie,
        type: t('Necesaria'),
        purpose: t('Mantiene tu sesión iniciada mientras navegas.'),
        duration: t(':minutes minutos de inactividad', {
            minutes: props.session.lifetimeMinutes,
        }),
    },
    {
        name: 'XSRF-TOKEN',
        type: t('Necesaria'),
        purpose: t(
            'Protege los formularios contra solicitudes falsificadas desde otros sitios.',
        ),
        duration: t('Igual que la sesión'),
    },
    {
        name: 'remember_web_*',
        type: t('Necesaria'),
        purpose: t(
            'Solo si marcas «Recordarme» al iniciar sesión: evita que tengas que volver a ingresar.',
        ),
        duration: t('Hasta 400 días o hasta que cierres sesión'),
    },
    {
        name: 'locale',
        type: t('Preferencia'),
        purpose: t('Recuerda el idioma que elegiste.'),
        duration: t('1 año'),
    },
    {
        name: 'appearance',
        type: t('Preferencia'),
        purpose: t('Recuerda si prefieres el tema claro u oscuro.'),
        duration: t('1 año'),
    },
    {
        name: 'sidebar_state',
        type: t('Preferencia'),
        purpose: t(
            'Recuerda si dejaste abierto o cerrado el menú lateral del panel.',
        ),
        duration: t('7 días'),
    },
]);

const sections = computed<LegalSection[]>(() => [
    {
        title: t('3. Lo que no usamos'),
        paragraphs: [
            t(
                'Montree no usa cookies de analítica, publicidad ni seguimiento, ni comparte información de navegación con redes publicitarias.',
            ),
            t(
                'Al pagar una reserva pasas al sitio de PlacetoPay, que puede usar sus propias cookies según su política. Algunas tipografías del sitio se cargan desde Google Fonts, que recibe la dirección IP del navegador pero no instala cookies de Montree.',
            ),
        ],
    },
    {
        title: t('4. ¿Por qué no te pedimos aceptar cookies?'),
        paragraphs: [
            t(
                'Solo usamos cookies necesarias para que el sitio funcione y cookies de preferencia que se crean cuando tú eliges un idioma o un tema. Ninguna sirve para perfilarte ni para publicidad, por eso no mostramos un aviso de aceptación.',
            ),
            t(
                'Si en el futuro agregamos cookies de analítica o publicidad, te pediremos autorización antes de activarlas y actualizaremos esta política.',
            ),
        ],
    },
    {
        title: t('5. Cómo controlarlas'),
        paragraphs: [
            t(
                'Puedes ver y borrar las cookies desde la configuración de tu navegador. Si bloqueas las cookies necesarias no podrás iniciar sesión ni completar formularios en Montree.',
            ),
        ],
    },
]);
</script>

<template>
    <LegalDocument
        :page-title="$t('Política de cookies — Montree')"
        :heading="$t('Política de cookies')"
        :lead="$t('Qué cookies usa Montree, para qué sirven y cuánto duran.')"
        :updated-at="$t('27 de septiembre de 2026')"
        :sections="sections"
    >
        <template #intro>
            <h2>{{ $t('1. Qué son las cookies') }}</h2>
            <p>
                {{
                    $t(
                        'Son pequeños archivos que el sitio guarda en tu navegador para recordar información entre una página y otra, como tu sesión o tu idioma.',
                    )
                }}
            </p>

            <h2>{{ $t('2. Cookies que usamos') }}</h2>
            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">{{ $t('Nombre') }}</th>
                            <th scope="col">{{ $t('Tipo') }}</th>
                            <th scope="col">{{ $t('Para qué sirve') }}</th>
                            <th scope="col">{{ $t('Duración') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="cookie in cookies" :key="cookie.name">
                            <td>
                                <code>{{ cookie.name }}</code>
                            </td>
                            <td>{{ cookie.type }}</td>
                            <td>{{ cookie.purpose }}</td>
                            <td>{{ cookie.duration }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <p>
            {{ $t('Para saber cómo tratamos tus datos personales, lee la') }}
            <a href="/politica-de-privacidad">{{
                $t('política de privacidad')
            }}</a
            >.
        </p>
    </LegalDocument>
</template>
