<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlatformLegalEntity from '@/components/molecules/PlatformLegalEntity.vue';
import type { LegalSection } from '@/components/organisms/LegalDocument.vue';
import LegalDocument from '@/components/organisms/LegalDocument.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const privacyEmail = computed(
    () => page.props.platform?.legal.privacy_email ?? 'hola@montree.co',
);

const sections = computed<LegalSection[]>(() => [
    {
        title: t('2. A quién aplica'),
        paragraphs: [
            t(
                'Montree es responsable de los datos de quienes visitan montree.co, registran una agencia o usan el panel como parte del equipo de una agencia (administradores, vendedores, operadores y guías).',
            ),
            t(
                'Los datos que cada agencia recoge de sus viajeros en su propio sitio son responsabilidad de esa agencia. Sobre ellos Montree actúa como encargado: los trata por cuenta de la agencia, solo para prestarle el servicio y según sus instrucciones. Para ejercer tus derechos sobre una reserva, dirígete a la agencia con la que reservaste.',
            ),
        ],
    },
    {
        title: t('3. Datos que tratamos'),
        items: [
            t(
                'Registro de la agencia: nombre de la agencia, subdominio, nombre de quien la registra, correo electrónico y contraseña. La contraseña se guarda cifrada con un algoritmo de un solo sentido; nadie en Montree puede leerla.',
            ),
            t(
                'Uso del servicio: la información que la agencia carga en el panel (tours, salidas, reservas, pagos y equipo).',
            ),
            t(
                'Datos técnicos: dirección IP, tipo de navegador, fecha del último ingreso y registros de seguridad necesarios para operar y proteger la plataforma.',
            ),
            t(
                'Preferencias: idioma y apariencia, guardadas en cookies (ver la política de cookies).',
            ),
            t(
                'Comunicaciones: lo que nos escribas por correo para soporte o consultas.',
            ),
        ],
        after: [
            t(
                'Montree no pide datos sensibles para crear la cuenta de una agencia, y el servicio no está dirigido a menores de edad.',
            ),
        ],
    },
    {
        title: t('4. Para qué los usamos'),
        items: [
            t(
                'Crear, verificar y administrar la cuenta y el sitio de la agencia.',
            ),
            t(
                'Autenticar a los usuarios y proteger las cuentas, incluida la verificación del correo y la verificación en dos pasos.',
            ),
            t(
                'Prestar el servicio: catálogo, reservas, pagos en línea y liquidaciones a la agencia.',
            ),
            t(
                'Enviar mensajes necesarios para el servicio, como verificación de correo, recuperación de contraseña y avisos sobre la cuenta.',
            ),
            t('Atender solicitudes de soporte, consultas y reclamos.'),
            t('Cumplir obligaciones legales, contables y tributarias.'),
        ],
        after: [
            t(
                'No vendemos tus datos. Solo te enviaremos mensajes comerciales si nos das una autorización aparte, que puedes retirar cuando quieras.',
            ),
        ],
    },
    {
        title: t('5. Con quién los compartimos'),
        paragraphs: [
            t(
                'Usamos proveedores que tratan datos por cuenta nuestra y únicamente para prestar el servicio:',
            ),
        ],
        items: [
            t(
                'Infraestructura en la nube para alojar la aplicación, la base de datos y los archivos.',
            ),
            t(
                'PlacetoPay, pasarela que procesa los pagos en línea de las reservas.',
            ),
            t('Proveedor de envío de correo electrónico transaccional.'),
            t(
                'OpenStreetMap y Nominatim, para mostrar mapas y ubicar direcciones de los tours.',
            ),
            t(
                'Google Fonts, que entrega algunas tipografías del sitio y recibe la dirección IP del navegador al cargarlas.',
            ),
        ],
        after: [
            t(
                'Algunos de estos proveedores tienen servidores fuera de Colombia. Al aceptar esta política autorizas de forma expresa que tus datos se transmitan y almacenen en esos servidores para las finalidades descritas.',
            ),
            t(
                'También entregaremos datos a una autoridad cuando una ley o una orden judicial lo exija.',
            ),
        ],
    },
    {
        title: t('6. Tus derechos'),
        paragraphs: [
            t(
                'Como titular de los datos tienes derecho, de forma gratuita, a:',
            ),
        ],
        items: [
            t('Conocer, actualizar y rectificar tus datos.'),
            t('Pedir prueba de la autorización que nos diste.'),
            t('Ser informado sobre el uso que les damos.'),
            t(
                'Revocar la autorización o pedir que suprimamos tus datos, salvo cuando la ley o un contrato vigente nos obliguen a conservarlos.',
            ),
            t(
                'Presentar quejas ante la Superintendencia de Industria y Comercio, después de haber hecho tu consulta o reclamo ante Montree.',
            ),
        ],
    },
    {
        title: t('7. Cómo ejercerlos'),
        paragraphs: [
            t(
                'Escríbenos a :email indicando tu nombre, tu número de identificación, el correo con el que usas Montree, lo que solicitas y cómo contactarte.',
                { email: privacyEmail.value },
            ),
        ],
        items: [
            t(
                'Consultas: respondemos en máximo 10 días hábiles. Si no es posible, te explicamos el motivo y respondemos dentro de los 5 días hábiles siguientes.',
            ),
            t(
                'Reclamos (corrección, actualización, supresión o incumplimiento): respondemos en máximo 15 días hábiles, prorrogables por 8 días hábiles más con aviso previo. Si el reclamo está incompleto te pediremos completarlo dentro de los 5 días siguientes; si pasan 2 meses sin respuesta, se entiende desistido.',
            ),
        ],
    },
    {
        title: t('8. Seguridad'),
        paragraphs: [
            t(
                'Aplicamos medidas técnicas y administrativas razonables: conexiones cifradas, contraseñas cifradas, separación de los datos de cada agencia, permisos por rol y verificación en dos pasos opcional. Ningún sistema es infalible; si detectamos un incidente que afecte tus datos, te avisaremos y lo reportaremos a la autoridad cuando corresponda.',
            ),
        ],
    },
    {
        title: t('9. Cuánto tiempo los conservamos'),
        paragraphs: [
            t(
                'Mientras la cuenta esté activa y, después, durante el tiempo necesario para cumplir obligaciones legales, contables o tributarias y atender reclamaciones. Luego los eliminamos o anonimizamos.',
            ),
        ],
    },
    {
        title: t('10. Cambios y vigencia'),
        paragraphs: [
            t(
                'Esta política rige desde su fecha de publicación y las bases de datos estarán vigentes mientras se preste el servicio. Si hacemos cambios importantes, te avisaremos por correo o en el sitio antes de aplicarlos.',
            ),
        ],
    },
]);
</script>

<template>
    <LegalDocument
        :page-title="$t('Política de privacidad — Montree')"
        :heading="$t('Política de privacidad y tratamiento de datos')"
        :lead="
            $t(
                'Qué datos personales trata Montree, para qué, con quién los comparte y cómo puedes ejercer tus derechos.',
            )
        "
        :updated-at="$t('27 de septiembre de 2026')"
        :sections="sections"
    >
        <template #intro>
            <h2>{{ $t('1. Responsable del tratamiento') }}</h2>
            <p>
                {{
                    $t(
                        'Esta política cumple la Ley 1581 de 2012 y el Decreto 1377 de 2013 (compilado en el Decreto 1074 de 2015). El responsable del tratamiento es:',
                    )
                }}
            </p>
            <PlatformLegalEntity />
        </template>

        <p>
            {{ $t('Sobre las cookies que usa el sitio, revisa la') }}
            <a href="/politica-de-cookies">{{ $t('política de cookies') }}</a
            >.
        </p>
    </LegalDocument>
</template>
