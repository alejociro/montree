<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlatformLegalEntity from '@/components/molecules/PlatformLegalEntity.vue';
import type { LegalSection } from '@/components/organisms/LegalDocument.vue';
import LegalDocument from '@/components/organisms/LegalDocument.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const contactEmail = computed(
    () => page.props.platform?.legal.email ?? 'it@jae-solutions.com',
);

const sections = computed<LegalSection[]>(() => [
    {
        title: t('2. Qué es Montree'),
        paragraphs: [
            t(
                'Montree es una plataforma de software en línea que permite a agencias y operadores de turismo publicar su catálogo, recibir reservas y pagos en línea y coordinar a su equipo desde un sitio propio.',
            ),
            t(
                'Montree no es una agencia de viajes ni presta servicios turísticos. El contrato del viaje se celebra entre la agencia y su viajero; la agencia es la única responsable de prestar el servicio que vende.',
            ),
        ],
    },
    {
        title: t('3. Aceptación'),
        paragraphs: [
            t(
                'Al crear una cuenta aceptas estos términos. Si registras una agencia, declaras que tienes facultad para obligarla. Si no estás de acuerdo, no uses la plataforma.',
            ),
        ],
    },
    {
        title: t('4. Cuenta y usuarios'),
        items: [
            t('Entrega información veraz y mantenla actualizada.'),
            t(
                'Guarda tus credenciales en reserva. Eres responsable de lo que se haga con tu cuenta y con las cuentas de las personas que invites a tu equipo.',
            ),
            t('Avísanos de inmediato si detectas un acceso no autorizado.'),
        ],
    },
    {
        title: t('5. Obligaciones de la agencia'),
        items: [
            t(
                'Contar con los permisos y registros que exige la ley para operar, incluido el Registro Nacional de Turismo cuando aplique.',
            ),
            t(
                'Publicar información veraz y completa sobre sus tours, precios, condiciones y restricciones.',
            ),
            t(
                'Publicar para sus viajeros sus propios términos, su política de cancelación y reembolsos y su política de tratamiento de datos.',
            ),
            t(
                'Cumplir el Estatuto del Consumidor (Ley 1480 de 2011) frente a sus viajeros y la ley de protección de datos (Ley 1581 de 2012) como responsable de los datos que recoge.',
            ),
            t(
                'Emitir las facturas y cumplir las obligaciones tributarias de los servicios que vende.',
            ),
        ],
    },
    {
        title: t('6. Contenido de la agencia'),
        paragraphs: [
            t(
                'Los textos, fotos, logos y demás contenidos que sube la agencia siguen siendo suyos. La agencia garantiza que tiene los derechos para usarlos y concede a Montree una licencia no exclusiva, gratuita y limitada para alojarlos y mostrarlos con el único fin de prestar el servicio.',
            ),
            t(
                'Montree puede retirar contenido que sea ilegal o que infrinja derechos de terceros, informando a la agencia.',
            ),
        ],
    },
    {
        title: t('7. Uso permitido'),
        paragraphs: [t('No está permitido usar Montree para:')],
        items: [
            t('Actividades ilegales, fraudulentas o engañosas.'),
            t('Enviar mensajes no solicitados o suplantar a otras personas.'),
            t(
                'Intentar acceder a datos de otras agencias, vulnerar la seguridad o sobrecargar la plataforma.',
            ),
            t('Copiar, descompilar o revender el software.'),
        ],
    },
    {
        title: t('8. Tarifas'),
        paragraphs: [
            t(
                'Crear una agencia en Montree no tiene costo. Montree cobra un porcentaje sobre el valor de cada reserva confirmada, según el rango en que esté la reserva; los detalles se encuentran en la política de pago y en el acuerdo comercial con cada agencia.',
            ),
            t(
                'Si cambiamos las tarifas te avisaremos con anticipación razonable. Los cambios no afectan reservas ya confirmadas.',
            ),
        ],
    },
    {
        title: t('9. Disponibilidad del servicio'),
        paragraphs: [
            t(
                'Montree está en fase beta: algunas funciones pueden cambiar o estar incompletas. Hacemos esfuerzos razonables para mantener el servicio disponible, pero puede haber interrupciones por mantenimiento o por causas ajenas a nosotros.',
            ),
        ],
    },
    {
        title: t('10. Propiedad intelectual de Montree'),
        paragraphs: [
            t(
                'El software, la marca Montree y el diseño de la plataforma pertenecen a Montree o a sus licenciantes y están protegidos por las normas de derechos de autor y propiedad industrial. Usar el servicio no transfiere ninguno de esos derechos.',
            ),
        ],
    },
    {
        title: t('11. Datos personales'),
        paragraphs: [
            t(
                'Montree trata los datos personales según su política de privacidad. Sobre los datos de los viajeros de cada agencia, Montree actúa como encargado del tratamiento por cuenta de la agencia.',
            ),
        ],
    },
    {
        title: t('12. Terminación'),
        paragraphs: [
            t(
                'La agencia puede cancelar su cuenta cuando quiera, sin permanencia mínima. Antes del cierre debe cumplir o reembolsar las reservas ya confirmadas. Puede pedir una copia de su información escribiendo a :email.',
                { email: contactEmail.value },
            ),
            t(
                'Montree puede suspender o cerrar una cuenta que incumpla estos términos o la ley, informando el motivo.',
            ),
        ],
    },
    {
        title: t('13. Responsabilidad'),
        paragraphs: [
            t(
                'Montree no responde por la prestación de los servicios turísticos ni por las decisiones comerciales de las agencias. En lo demás, la responsabilidad de Montree se limita en la medida en que lo permita la ley colombiana.',
            ),
        ],
    },
    {
        title: t('14. Cambios a estos términos'),
        paragraphs: [
            t(
                'Si modificamos estos términos te avisaremos por correo o en el sitio antes de que entren en vigor. Seguir usando el servicio después de esa fecha implica aceptar la nueva versión.',
            ),
        ],
    },
    {
        title: t('15. Ley aplicable'),
        paragraphs: [
            t(
                'Estos términos se rigen por las leyes de la República de Colombia. Las diferencias se intentarán resolver primero de forma directa; si no es posible, las resolverán los jueces competentes de Colombia.',
            ),
        ],
    },
]);
</script>

<template>
    <LegalDocument
        :page-title="$t('Términos y condiciones — Montree')"
        :heading="$t('Términos y condiciones')"
        :lead="
            $t(
                'Las reglas para usar Montree como agencia: qué ofrece la plataforma, qué le corresponde a cada parte y cómo se termina la relación.',
            )
        "
        :updated-at="$t('27 de septiembre de 2026')"
        :sections="sections"
    >
        <template #intro>
            <h2>{{ $t('1. Quién presta el servicio') }}</h2>
            <p>
                {{
                    $t(
                        'El servicio lo presta Montree, operador de montree.co. Contacto: :email.',
                        { email: contactEmail },
                    )
                }}
            </p>
            <PlatformLegalEntity />
        </template>

        <p>
            {{ $t('Consulta también la') }}
            <a href="/politica-de-privacidad">{{
                $t('política de privacidad')
            }}</a>
            {{ $t('y la') }}
            <a href="/politica-de-pago">{{ $t('política de pago') }}</a
            >.
        </p>
    </LegalDocument>
</template>
