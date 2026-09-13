<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Menu, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

interface Props {
    registerUrl: string;
    loginUrl: string;
    contactUrl: string;
    demoUrl: string;
}

const props = defineProps<Props>();
const { t } = useTranslations();

const nav = [
    { label: t('Funciones'), href: '#funciones' },
    { label: t('Tu sitio'), href: '#sitio' },
    { label: t('Planes'), href: '#planes' },
    { label: t('Agencias'), href: '#reviews' },
];

const mobileNav = ref(false);

const heroCards = [
    {
        kicker: t('TU SITIO'),
        title: 'sierranevada',
        image: 'card-sierra-nevada',
        alt: t('Panel de administración mostrando el tour Travesía Sierra Nevada'),
        pos: 'left-0 bottom-[6%] h-[clamp(146px,18vw,204px)] w-[clamp(118px,14.5vw,164px)] -rotate-[4deg] rounded-[22px]',
    },
    {
        kicker: t('RESERVA'),
        title: t('Pago confirmado'),
        image: 'card-pago-confirmado',
        alt: t('Planilla de pasajeros del panel con pagos marcados como confirmados'),
        pos: 'left-[31%] bottom-[22%] z-20 h-[clamp(156px,19.5vw,218px)] w-[clamp(128px,15.5vw,176px)] rounded-[24px]',
    },
    {
        kicker: t('CUPOS HOY'),
        title: '18 / 24',
        image: 'card-cupos',
        alt: t('Listado de salidas del panel con cupos disponibles por tour'),
        pos: 'right-0 bottom-[2%] h-[clamp(146px,18vw,204px)] w-[clamp(118px,14.5vw,164px)] rotate-[5deg] rounded-[22px]',
    },
];

const stats = [
    { label: t('MENOS ADMINISTRACIÓN'), value: '85%' },
    { label: t('MÁS RESERVAS'), value: '3×' },
    { label: t('PUBLICAR TU PRIMER TOUR'), value: '<10min' },
];

const screens = [
    {
        url: t('panel de reservas'),
        label: '',
        pos: 'left-0 top-[14%] h-[68%] w-[56%] -rotate-[3deg] rounded-[22px]',
    },
    {
        url: t('sitio del tenant'),
        label: t('captura: página pública de la agencia'),
        pos: 'left-[24%] top-0 z-30 h-[74%] w-[58%] rounded-[24px]',
    },
    {
        url: t('agenda del guía'),
        label: '',
        pos: 'right-0 bottom-0 z-20 h-[56%] w-[42%] rotate-[4deg] rounded-[22px]',
    },
];

const pillars = [
    { icon: '24/7', label: t('Reservas automáticas') },
    { icon: 'ES', label: t('Soporte en español') },
    { icon: 'CO', label: t('Datos en Colombia') },
];

const textures = {
    a: 'bg-[repeating-linear-gradient(135deg,#E4EDE0_0_10px,#D8E4D4_10px_20px)]',
    b: 'bg-[repeating-linear-gradient(135deg,#DCE8D8_0_9px,#CFDECB_9px_18px)]',
    c: 'bg-[repeating-linear-gradient(135deg,#E8EFE2_0_12px,#DDE7D8_12px_24px)]',
};

const features = [
    {
        title: t('Catálogo'),
        up: t('EL CATÁLOGO'),
        body: t(
            'Publica tours con fotos en alta, itinerario día a día, dificultad, qué incluye y qué no. Editable desde el celular en medio del campo.',
        ),
        blocks: [
            {
                title: t('Cupos en vivo'),
                description: t(
                    'cuando se llena, la salida se cierra sola. Sin sobreventas los sábados.',
                ),
            },
            {
                title: t('Fichas completas'),
                description: t(
                    'punto de encuentro, qué llevar, nivel de exigencia y política de cancelación.',
                ),
            },
            {
                title: t('Todo desde el móvil'),
                description: t(
                    'cambias precio o cupo desde el celular y el sitio se actualiza al instante.',
                ),
            },
        ],
        shots: [t('editor de tour'), t('galería'), t('ficha pública')],
    },
    {
        title: t('Reservas 24/7'),
        up: t('LAS RESERVAS'),
        body: t(
            'El turista reserva y paga solo, a cualquier hora, sin que nadie responda un WhatsApp de madrugada.',
        ),
        blocks: [
            {
                title: t('Confirmación automática'),
                description: t(
                    'comprobante por correo y WhatsApp, y el guía queda agendado.',
                ),
            },
            {
                title: t('Reglas por salida'),
                description: t(
                    'anticipación mínima, mínimo de personas y máximo de cupos.',
                ),
            },
            {
                title: t('Lista de espera'),
                description: t(
                    'cuando el cupo se llena, Montree guarda al interesado para la próxima salida.',
                ),
            },
        ],
        shots: [t('flujo de reserva'), t('confirmación'), t('lista de espera')],
    },
    {
        title: t('Dashboard'),
        up: t('EL DASHBOARD'),
        body: t(
            'Ocupación por salida, caja del día, tours más vendidos y comisiones por vendedor. Sin cuadrar Excel a las 11 de la noche.',
        ),
        blocks: [
            {
                title: t('Caja del día'),
                description: t(
                    'lo que entró, lo que falta por cobrar y lo que se devolvió.',
                ),
            },
            {
                title: t('Alertas de riesgo'),
                description: t(
                    'te avisa qué salida va a quedar por debajo del mínimo.',
                ),
            },
            {
                title: t('Reportes'),
                description: t('exportas el mes en un clic para tu contador.'),
            },
        ],
        shots: [t('métricas'), t('reporte mensual'), t('ocupación')],
    },
    {
        title: t('Equipo'),
        up: t('EL EQUIPO'),
        body: t(
            'Cuatro roles con permisos reales: cada persona ve exactamente lo que necesita para su trabajo.',
        ),
        blocks: [
            {
                title: t('Agenda por guía'),
                description: t(
                    'asignación con notificación y lista de pasajeros en el celular.',
                ),
            },
            {
                title: t('Vendedores'),
                description: t('enlace propio y comisión calculada sola.'),
            },
            {
                title: t('Operadores'),
                description: t('checklist de salida y control de asistencia.'),
            },
        ],
        shots: [t('agenda de guías'), t('comisiones'), t('checklist')],
    },
    {
        title: t('Pagos'),
        up: t('LOS PAGOS'),
        body: t(
            'Cobra anticipo o el 100% en línea con los medios de pago que se usan en Colombia. Cada reserva conciliada con su pago.',
        ),
        blocks: [
            {
                title: t('PSE y tarjetas'),
                description: t(
                    'más efectivo registrado en caja para las ventas en el mostrador.',
                ),
            },
            {
                title: t('Anticipo flexible'),
                description: t(
                    'define el porcentaje por tour o por temporada.',
                ),
            },
            {
                title: t('Devoluciones'),
                description: t(
                    'aplicadas según tu propia política de cancelación.',
                ),
            },
        ],
        shots: [t('pasarela de pago'), t('conciliación'), t('devoluciones')],
    },
];

const featureIdx = ref(0);
const activeFeature = computed(() => features[featureIdx.value]);
const clusterCaption = computed(
    () => `CAPTURAS: ${activeFeature.value.shots.join(' · ').toUpperCase()}`,
);
const featureCluster = [
    { texture: textures.c, pos: 'left-[2%] top-[6%] z-10 w-[26%]' },
    { texture: textures.a, pos: 'left-[16%] top-[24%] z-30 w-[34%]' },
    { texture: textures.b, pos: 'left-[38%] top-[2%] z-20 w-[27%]' },
    { texture: textures.a, pos: 'left-[45%] top-[26%] z-40 w-[36%]' },
    { texture: textures.c, pos: 'left-[26%] top-[56%] z-20 w-[26%]' },
    { texture: textures.b, pos: 'right-[2%] top-[56%] z-30 w-[30%]' },
];

const tenantDiamonds = [
    { texture: textures.c, pos: 'left-[8%] top-[16%] z-10 w-[23%]' },
    { texture: textures.a, pos: 'left-[19%] top-[31%] z-30 w-[29%]' },
    { texture: textures.b, pos: 'left-[31%] top-[8%] z-20 w-[25%]' },
    { texture: textures.a, pos: 'left-[40%] top-[27%] z-40 w-[31%]' },
    { texture: textures.b, pos: 'right-[9%] top-[14%] z-20 w-[25%]' },
    { texture: textures.c, pos: 'left-[28%] bottom-[8%] z-20 w-[23%]' },
    { texture: textures.a, pos: 'right-[13%] bottom-[10%] z-30 w-[26%]' },
];

const tenantList = [
    t('Página pública propia con tu logo, tus colores y tus fotos'),
    t('Empieza en tuagencia.montree.co y conecta tu dominio cuando quieras'),
    t('Catálogo, precios y políticas independientes de cualquier otra agencia'),
    t('Datos aislados: ningún tenant ve la información de otro'),
    t('Editable sin código desde el panel, sin depender de un desarrollador'),
];

const roles = [
    { name: t('Admin'), description: t('todo el negocio') },
    { name: t('Guía'), description: t('su agenda') },
    { name: t('Vendedor'), description: t('ventas y comisión') },
    { name: t('Operador'), description: t('salidas del día') },
];

const plans = [
    {
        number: '01',
        name: t('Semilla'),
        price: t('Gratis'),
        unit: t('· gratis para siempre'),
        cta: t('Crear mi agencia'),
        description: t(
            'Para la agencia que está empezando o quiere probar Montree con su operación real, sin compromiso.',
        ),
        items: [
            t('Hasta 25 reservas al mes'),
            t('1 usuario administrador'),
            t('Página pública en tuagencia.montree.co'),
            t('Catálogo de tours ilimitado'),
            t('Pagos en línea con comisión por transacción'),
        ],
        specs: [
            { key: t('RESERVAS'), value: t('25 / mes') },
            { key: t('USUARIOS'), value: t('1 admin') },
            { key: t('SITIO'), value: t('subdominio') },
            { key: t('SOPORTE'), value: t('correo') },
        ],
    },
    {
        number: '02',
        name: t('Sendero'),
        price: '$149.000',
        unit: t('· COP / mes'),
        cta: t('Empezar 30 días gratis'),
        description: t(
            'Para la agencia con operación constante, guías en campo y vendedores. El plan que usa la mayoría.',
        ),
        items: [
            t('Reservas ilimitadas'),
            t('Hasta 8 usuarios con roles (Admin, Guía, Vendedor, Operador)'),
            t('Dominio propio: www.tuagencia.com'),
            t('Comisiones por vendedor y reportes exportables'),
            t('Soporte prioritario en español'),
        ],
        specs: [
            { key: t('RESERVAS'), value: t('ilimitadas') },
            { key: t('USUARIOS'), value: t('hasta 8') },
            { key: t('SITIO'), value: t('dominio propio') },
            { key: t('SOPORTE'), value: t('prioritario') },
        ],
    },
    {
        number: '03',
        name: t('Expedición'),
        price: t('A medida'),
        unit: t('· hablemos'),
        cta: t('Contactar al equipo'),
        description: t(
            'Para operadores con varias sedes o marcas, integraciones con su contabilidad y necesidades especiales.',
        ),
        items: [
            t('Varias sedes o marcas en una sola cuenta'),
            t('Usuarios ilimitados'),
            t('API y conexión con tu contabilidad'),
            t('Onboarding y migración asistida de datos'),
            t('Acuerdo de nivel de servicio (SLA)'),
        ],
        specs: [
            { key: t('RESERVAS'), value: t('ilimitadas') },
            { key: t('USUARIOS'), value: t('ilimitados') },
            { key: t('SITIO'), value: t('multi-marca') },
            { key: t('SOPORTE'), value: t('dedicado') },
        ],
    },
];

const planIdx = ref(1);
const activePlan = computed(() => plans[planIdx.value]);

const reviews = [
    {
        quote: t(
            'Pasamos del cuaderno a tener la agenda de los guías cuadrada desde el domingo anterior.',
        ),
        name: 'Laura Restrepo',
        role: t('Sierra Nevada Tours · Santa Marta'),
        photo: t('foto: trekking Cerro Kennedy'),
    },
    {
        quote: t(
            'Los turistas reservan de madrugada desde Europa y a mí me llega todo confirmado y pagado.',
        ),
        name: 'Andrés Muñoz',
        role: t('Ruta Verde · Salento'),
        photo: t('foto: valle de Cocora'),
    },
    {
        quote: t(
            'Mis tres vendedores ya no me llaman a preguntar si hay cupo: lo ven en su celular.',
        ),
        name: 'Camila Ortiz',
        role: t('Amazonía Viva · Leticia'),
        photo: t('foto: río Amazonas'),
    },
    {
        quote: t(
            'Dejamos de perder reservas en WhatsApp. Eso solo ya pagó el plan del año.',
        ),
        name: 'Julián Pardo',
        role: t('Ecorutas · Villa de Leyva'),
        photo: t('foto: desierto de la Candelaria'),
    },
];

const reviewIdx = ref(0);
const visibleReviews = computed(() =>
    [0, 1, 2].map(
        (offset) => reviews[(reviewIdx.value + offset) % reviews.length],
    ),
);
const nextReview = () => {
    reviewIdx.value = (reviewIdx.value + 1) % reviews.length;
};
const prevReview = () => {
    reviewIdx.value = (reviewIdx.value + reviews.length - 1) % reviews.length;
};

const footerColumns = [
    {
        title: t('PRODUCTO'),
        links: [
            [t('Funciones'), '#funciones'],
            [t('Tu sitio propio'), '#sitio'],
            [t('Planes'), '#planes'],
            [t('Preguntas frecuentes'), '#'],
        ],
    },
    {
        title: t('AGENCIAS'),
        links: [
            [t('Casos de éxito'), '#'],
            [t('Guía de migración'), '#'],
            [t('Soporte'), '#'],
            [t('Estado del servicio'), '#'],
        ],
    },
    {
        title: t('LEGAL'),
        links: [
            [t('Política de pago'), '#'],
            [t('Política de cancelación'), '#'],
            [t('Tratamiento de datos'), '#'],
            [t('Términos'), '#'],
        ],
    },
];
</script>

<template>
    <Head :title="$t('Montree — Software para agencias de ecoturismo')" />

    <div
        class="min-h-screen overflow-x-hidden bg-[#F6EFE1] font-['IBM_Plex_Sans',system-ui,sans-serif] text-[#123524] antialiased"
    >
        <section
            class="relative isolate flex min-h-[clamp(560px,80vh,820px)] flex-col bg-[#123524]"
        >
            <picture>
                <source
                    type="image/avif"
                    media="(orientation: portrait) and (max-width: 639px)"
                    sizes="100vw"
                    srcset="
                        /landing/cocora-portrait-540.avif   540w,
                        /landing/cocora-portrait-720.avif   720w,
                        /landing/cocora-portrait-1080.avif 1080w
                    "
                />
                <source
                    type="image/webp"
                    media="(orientation: portrait) and (max-width: 639px)"
                    sizes="100vw"
                    srcset="
                        /landing/cocora-portrait-540.webp   540w,
                        /landing/cocora-portrait-720.webp   720w,
                        /landing/cocora-portrait-1080.webp 1080w
                    "
                />
                <source
                    type="image/avif"
                    sizes="100vw"
                    srcset="
                        /landing/cocora-wide-640.avif   640w,
                        /landing/cocora-wide-960.avif   960w,
                        /landing/cocora-wide-1280.avif 1280w,
                        /landing/cocora-wide-1600.avif 1600w,
                        /landing/cocora-wide-1920.avif 1920w,
                        /landing/cocora-wide-2560.avif 2560w,
                        /landing/cocora-wide-3200.avif 3200w,
                        /landing/cocora-wide-3840.avif 3840w,
                        /landing/cocora-wide-4480.avif 4480w,
                        /landing/cocora-wide-5120.avif 5120w
                    "
                />
                <source
                    type="image/webp"
                    sizes="100vw"
                    srcset="
                        /landing/cocora-wide-640.webp   640w,
                        /landing/cocora-wide-960.webp   960w,
                        /landing/cocora-wide-1280.webp 1280w,
                        /landing/cocora-wide-1600.webp 1600w,
                        /landing/cocora-wide-1920.webp 1920w,
                        /landing/cocora-wide-2560.webp 2560w,
                        /landing/cocora-wide-3200.webp 3200w,
                        /landing/cocora-wide-3840.webp 3840w,
                        /landing/cocora-wide-4480.webp 4480w,
                        /landing/cocora-wide-5120.webp 5120w
                    "
                />
                <img
                    src="/landing/cocora-wide-1600.webp"
                    :alt="
                        $t(
                            'Palmas de cera del Valle de Cocora entre montañas al amanecer',
                        )
                    "
                    fetchpriority="high"
                    decoding="async"
                    class="absolute inset-0 -z-10 size-full object-cover"
                />
            </picture>
            <div
                class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgb(10_32_20/52%)_0%,rgb(10_32_20/20%)_38%,rgb(10_32_20/72%)_100%)]"
            ></div>
            <header
                class="relative z-20 mx-auto flex w-full max-w-[1320px] items-center gap-[18px] px-[clamp(16px,4vw,44px)] py-[22px] text-[#FBF6EC]"
            >
                <Link :href="home().url" class="flex items-center gap-2.5">
                    <span
                        class="grid size-8 place-items-center rounded-full bg-[#FBF6EC] font-['Newsreader',serif] text-base font-semibold text-[#123524]"
                        >M</span
                    >
                    <span
                        class="font-['Newsreader',serif] text-[21px] font-medium tracking-[0.01em]"
                        >Montree</span
                    >
                </Link>

                <nav
                    class="ml-auto hidden items-center gap-[clamp(10px,1.6vw,22px)] rounded-full border border-[#FBF6EC]/28 bg-[#FBF6EC]/16 px-[clamp(14px,2vw,22px)] py-[9px] text-[13px] backdrop-blur-[8px] lg:flex"
                >
                    <a
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="text-[#FBF6EC] transition-colors hover:text-[#E9D9B4]"
                        >{{ item.label }}</a
                    >
                </nav>
                <Link
                    :href="props.loginUrl"
                    class="ml-auto hidden text-[13px] text-[#FBF6EC]/85 transition-colors hover:text-[#E9D9B4] sm:block lg:ml-0"
                    >{{ $t('Iniciar sesión') }}</Link
                >
                <Link
                    :href="props.registerUrl"
                    class="rounded-full bg-[#FBF6EC] px-[22px] py-[11px] text-[13px] font-medium text-[#123524] transition-colors hover:bg-white"
                    >{{ $t('Comenzar gratis') }}</Link
                >
                <button
                    type="button"
                    class="grid size-10 place-items-center rounded-full border border-[#FBF6EC]/40 text-[#FBF6EC] lg:hidden"
                    :aria-expanded="mobileNav"
                    aria-controls="mobile-navigation"
                    :aria-label="$t('Abrir menú')"
                    @click="mobileNav = !mobileNav"
                >
                    <X v-if="mobileNav" class="size-4" />
                    <Menu v-else class="size-4" />
                </button>
            </header>

            <nav
                v-if="mobileNav"
                id="mobile-navigation"
                class="relative z-20 mx-auto w-full max-w-[1320px] px-[clamp(16px,4vw,44px)] lg:hidden"
            >
                <div
                    class="rounded-3xl border border-[#FBF6EC]/25 bg-[#0A2014]/70 px-5 py-3 backdrop-blur-[8px]"
                >
                    <a
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="block py-2 text-sm text-[#FBF6EC]"
                        @click="mobileNav = false"
                        >{{ item.label }}</a
                    >
                    <Link
                        :href="props.loginUrl"
                        class="block py-2 text-sm text-[#FBF6EC]"
                        @click="mobileNav = false"
                        >{{ $t('Iniciar sesión') }}</Link
                    >
                </div>
            </nav>

            <div
                class="relative z-10 mx-auto mt-auto grid w-full max-w-[1220px] items-end gap-[clamp(24px,4vw,48px)] px-[clamp(16px,4vw,44px)] pb-[clamp(120px,15vw,220px)] text-[#FBF6EC] lg:grid-cols-2"
            >
                <div>
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-[#FBF6EC]/30 bg-[#FBF6EC]/16 px-[15px] py-[7px] text-xs backdrop-blur-[6px]"
                    >
                        <span class="size-1.5 rounded-full bg-[#E9D9B4]" />
                        {{ $t('Plataforma para ecoturismo colombiano') }}
                    </span>
                    <h1
                        class="mt-5 font-['Newsreader',serif] text-[clamp(42px,6.4vw,84px)] leading-[1.04] font-semibold italic drop-shadow-[0_8px_34px_rgba(10,32,20,0.5)]"
                    >
                        {{ $t('Digitaliza tu agencia') }}<br />{{
                            $t('de ecoturismo')
                        }}
                    </h1>
                    <p
                        class="mt-[18px] max-w-[44ch] text-[15.5px] leading-[1.65] text-[#FBF6EC]/90"
                    >
                        {{
                            $t(
                                'Reservas automáticas, pagos en línea, equipo coordinado y tu propia página pública con tu marca. Todo en un solo lugar.',
                            )
                        }}
                    </p>
                    <div class="mt-[26px] flex flex-wrap gap-3">
                        <Link
                            :href="props.registerUrl"
                            class="rounded-full bg-[#FBF6EC] px-7 py-3.5 text-[13.5px] font-medium text-[#123524] transition-colors hover:bg-white"
                            >{{ $t('Crear mi agencia') }}</Link
                        >
                        <a
                            :href="props.demoUrl"
                            class="rounded-full border border-[#FBF6EC]/55 px-[26px] py-3.5 text-[13.5px] text-[#FBF6EC] transition-colors hover:bg-[#FBF6EC]/16"
                            >{{ $t('Ver demo') }}</a
                        >
                    </div>
                </div>

                <div
                    class="relative hidden h-[clamp(190px,23vw,290px)] md:block"
                >
                    <article
                        v-for="card in heroCards"
                        :key="card.kicker"
                        class="absolute overflow-hidden border border-[#FBF6EC]/35 text-[#FBF6EC] shadow-[0_24px_44px_-26px_rgba(10,32,20,0.8)]"
                        :class="card.pos"
                    >
                        <div class="relative h-full bg-[#1B4632]">
                            <picture>
                                <source
                                    type="image/avif"
                                    sizes="220px"
                                    :srcset="`
                                        /landing/${card.image}-320.avif   320w,
                                        /landing/${card.image}-480.avif   480w,
                                        /landing/${card.image}-640.avif   640w,
                                        /landing/${card.image}-960.avif   960w,
                                        /landing/${card.image}-1280.avif 1280w
                                    `"
                                />
                                <source
                                    type="image/webp"
                                    sizes="220px"
                                    :srcset="`
                                        /landing/${card.image}-320.webp   320w,
                                        /landing/${card.image}-480.webp   480w,
                                        /landing/${card.image}-640.webp   640w,
                                        /landing/${card.image}-960.webp   960w,
                                        /landing/${card.image}-1280.webp 1280w
                                    `"
                                />
                                <img
                                    :src="`/landing/${card.image}-640.webp`"
                                    :alt="card.alt"
                                    loading="lazy"
                                    decoding="async"
                                    class="absolute inset-0 size-full object-cover"
                                />
                            </picture>
                            <div
                                class="absolute inset-0 bg-[linear-gradient(180deg,rgba(10,32,20,0)_40%,rgba(10,32,20,.85)_100%)]"
                            />
                            <div class="absolute inset-x-[13px] bottom-3">
                                <p
                                    class="font-['IBM_Plex_Mono',monospace] text-[8.5px] tracking-[0.14em] text-[#E9D9B4]"
                                >
                                    {{ card.kicker }}
                                </p>
                                <p
                                    class="mt-[3px] font-['Newsreader',serif] text-[15px] leading-[1.15] font-medium [overflow-wrap:anywhere]"
                                >
                                    {{ card.title }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <svg
                viewBox="0 0 1440 150"
                preserveAspectRatio="none"
                class="absolute -bottom-px left-0 block h-[clamp(70px,10vw,150px)] w-full"
                aria-hidden="true"
            >
                <path
                    d="M0,52 C210,132 400,4 700,58 C980,108 1180,18 1440,66 L1440,150 L0,150 Z"
                    fill="#F6EFE1"
                />
            </svg>
        </section>

        <section
            class="mx-auto max-w-[1220px] px-[clamp(16px,4vw,44px)] pt-[clamp(20px,3vw,44px)] pb-[clamp(48px,6vw,84px)]"
        >
            <div
                class="grid items-center gap-[clamp(30px,5vw,60px)] lg:grid-cols-2"
            >
                <div>
                    <dl class="flex items-end gap-[clamp(14px,2vw,26px)]">
                        <div
                            v-for="(stat, index) in stats"
                            :key="stat.value"
                            :class="
                                index < stats.length - 1
                                    ? 'border-r border-[#123524]/12 pr-[clamp(14px,2vw,26px)]'
                                    : ''
                            "
                        >
                            <dd
                                class="font-['Newsreader',serif] text-[clamp(24px,2.6vw,32px)] leading-none font-semibold whitespace-nowrap"
                            >
                                {{ stat.value }}
                            </dd>
                            <dt
                                class="mt-[5px] font-['IBM_Plex_Mono',monospace] text-[8px] leading-[1.3] tracking-[0.08em] text-[#7A8B7E]"
                            >
                                {{ stat.label }}
                            </dt>
                        </div>
                    </dl>
                    <p
                        class="mt-6 max-w-[52ch] text-[15px] leading-[1.75] text-[#4A5C4E]"
                    >
                        {{
                            $t(
                                'Montree se construyó con operadores de la Sierra Nevada, el Eje Cafetero y la Amazonía. Cada agencia entra con su propio espacio, su catálogo y su equipo — y publica su primer tour el mismo día.',
                            )
                        }}
                    </p>
                    <div class="mt-6 flex flex-wrap items-center gap-4">
                        <a
                            href="#funciones"
                            class="rounded-full bg-[#E4EDE0] px-[22px] py-[11px] text-[13px] transition-colors hover:bg-[#D8E4D4]"
                            >{{ $t('Conocer más →') }}</a
                        >
                        <div class="flex gap-2">
                            <span
                                v-for="initial in ['E', 'S', 'N', 'P']"
                                :key="initial"
                                class="grid size-8 place-items-center rounded-full border border-[#123524]/12 bg-[#FBF6EC] font-['Newsreader',serif] text-[13px] font-medium text-[#4A5C4E]"
                                >{{ initial }}</span
                            >
                        </div>
                    </div>
                </div>

                <div class="relative h-[clamp(250px,29vw,350px)]">
                    <article
                        v-for="screen in screens"
                        :key="screen.url"
                        class="absolute overflow-hidden border border-[#123524]/10 bg-[#FBF6EC] shadow-[0_26px_50px_-32px_rgba(10,32,20,0.5)]"
                        :class="screen.pos"
                    >
                        <div
                            class="flex items-center gap-[5px] bg-[#EFE7D7] px-[11px] py-2"
                        >
                            <span
                                class="size-[5px] rounded-full bg-[#123524]/25"
                            />
                            <span
                                class="size-[5px] rounded-full bg-[#123524]/25"
                            />
                            <span
                                class="ml-[5px] font-['IBM_Plex_Mono',monospace] text-[8px] text-[#7A8B7E]"
                                >{{ screen.url }}</span
                            >
                        </div>
                        <div
                            class="h-[calc(100%-27px)] bg-[repeating-linear-gradient(135deg,#E4EDE0_0_9px,#D8E4D4_9px_18px)]"
                        />
                        <span
                            v-if="screen.label"
                            class="absolute bottom-2.5 left-2.5 rounded-full bg-[#FBF6EC]/90 px-[9px] py-[5px] font-['IBM_Plex_Mono',monospace] text-[9px] text-[#3C5445]"
                            >{{ screen.label }}</span
                        >
                    </article>
                </div>
            </div>
        </section>

        <section class="relative bg-[#123524] text-[#FBF6EC]">
            <svg
                viewBox="0 0 1440 130"
                preserveAspectRatio="none"
                class="absolute -top-px left-0 z-20 block h-[clamp(60px,8vw,130px)] w-full"
                aria-hidden="true"
            >
                <path
                    d="M0,0 L1440,0 L1440,58 C1200,10 1010,120 720,66 C420,10 220,128 0,52 Z"
                    fill="#F6EFE1"
                />
            </svg>

            <div
                class="relative z-10 mx-auto grid max-w-[1220px] items-center gap-[clamp(26px,4vw,56px)] px-[clamp(16px,4vw,44px)] py-[clamp(90px,13vw,190px)] lg:grid-cols-2"
            >
                <div
                    class="grid h-[clamp(240px,28vw,330px)] place-items-center overflow-hidden rounded-[28px] bg-[repeating-linear-gradient(135deg,#255036_0_12px,#2E6242_12px_24px)]"
                >
                    <span
                        class="rounded-full bg-[#0A2014]/55 px-[14px] py-2 text-center font-['IBM_Plex_Mono',monospace] text-[10.5px]"
                        >{{
                            $t(
                                'foto: amanecer en un sendero de la Sierra Nevada',
                            )
                        }}</span
                    >
                </div>
                <div>
                    <h2
                        class="font-['Newsreader',serif] text-[clamp(34px,4.8vw,58px)] leading-[1.05] font-semibold italic"
                    >
                        {{ $t('¿Te suena familiar?') }}
                    </h2>
                    <p
                        class="mt-[18px] max-w-[54ch] text-[15px] leading-[1.75] text-[#FBF6EC]/84"
                    >
                        {{
                            $t(
                                'Seis chats de WhatsApp por la misma reserva. Un Excel que solo entiende una persona. Dos vendedores prometiendo el mismo cupo del sábado. Y al final del mes, ninguna certeza de qué tour deja plata.',
                            )
                        }}
                    </p>
                    <p
                        class="mt-3.5 max-w-[54ch] text-[15px] leading-[1.75] text-[#FBF6EC]/84"
                    >
                        {{
                            $t(
                                'Montree ordena esa operación: cada reserva con su pago, su guía y su estado, visible para todo el equipo en tiempo real.',
                            )
                        }}
                    </p>
                    <div
                        class="mt-7 grid grid-cols-[repeat(auto-fit,minmax(120px,1fr))] gap-3.5"
                    >
                        <div
                            v-for="pillar in pillars"
                            :key="pillar.label"
                            class="rounded-[22px] bg-[#FBF6EC]/9 p-4 text-center"
                        >
                            <span
                                class="inline-block rounded-full bg-[#E9D9B4]/16 px-3 py-1.5 font-['IBM_Plex_Mono',monospace] text-[12px] tracking-[0.08em] text-[#E9D9B4]"
                                >{{ pillar.icon }}</span
                            >
                            <p class="mt-2.5 text-[12.5px] text-[#FBF6EC]/90">
                                {{ pillar.label }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <svg
                viewBox="0 0 1440 130"
                preserveAspectRatio="none"
                class="absolute -bottom-px left-0 z-20 block h-[clamp(60px,8vw,130px)] w-full"
                aria-hidden="true"
            >
                <path
                    d="M0,46 C230,120 420,8 720,56 C1010,102 1210,14 1440,58 L1440,130 L0,130 Z"
                    fill="#F6EFE1"
                />
            </svg>
        </section>

        <section
            id="funciones"
            class="mx-auto max-w-[1220px] px-[clamp(16px,4vw,44px)] py-[clamp(40px,6vw,80px)]"
        >
            <h2
                class="text-center font-['Newsreader',serif] text-[clamp(36px,5.2vw,62px)] leading-[1.05] font-semibold italic"
            >
                {{ $t('Funciones') }}
            </h2>
            <div
                class="mt-[clamp(22px,3vw,34px)] flex flex-wrap justify-center gap-2"
                role="tablist"
                :aria-label="$t('Funciones de Montree')"
            >
                <button
                    v-for="(feature, index) in features"
                    :key="feature.title"
                    type="button"
                    role="tab"
                    class="rounded-full px-5 py-[11px] text-sm transition-colors duration-150"
                    :class="
                        index === featureIdx
                            ? 'bg-[#123524] text-[#F6EFE1]'
                            : 'border border-[#123524]/10 bg-[#FBF6EC] text-[#3C5445] hover:bg-[#E4EDE0]'
                    "
                    :aria-selected="index === featureIdx"
                    @click="featureIdx = index"
                >
                    {{ feature.title }}
                </button>
            </div>

            <p
                class="mt-[26px] text-center font-['IBM_Plex_Mono',monospace] text-[11px] tracking-[0.14em] text-[#7A8B7E]"
            >
                {{
                    $t('TRES RAZONES PARA USAR :feature', {
                        feature: activeFeature.up,
                    })
                }}
            </p>

            <div
                class="mt-[clamp(22px,3vw,34px)] grid items-center gap-[clamp(24px,3.4vw,48px)] lg:grid-cols-2"
            >
                <div>
                    <div class="relative h-[clamp(340px,38vw,460px)]">
                        <div
                            v-for="(diamond, index) in featureCluster"
                            :key="index"
                            class="absolute aspect-square"
                            :class="diamond.pos"
                        >
                            <div
                                class="absolute inset-0 rotate-45 overflow-hidden rounded-[clamp(14px,1.6vw,24px)] border-2 border-[#F6EFE1] shadow-[0_22px_44px_-26px_rgba(10,32,20,0.55)]"
                            >
                                <div
                                    class="absolute -inset-[42%] -rotate-45"
                                    :class="diamond.texture"
                                />
                            </div>
                        </div>
                    </div>
                    <p
                        class="mt-2.5 text-center font-['IBM_Plex_Mono',monospace] text-[9.5px] tracking-[0.1em] text-[#8A9A8E]"
                    >
                        {{ clusterCaption }}
                    </p>
                </div>

                <div class="flex flex-col gap-4">
                    <p
                        v-for="block in activeFeature.blocks"
                        :key="block.title"
                        class="rounded-[24px] border border-[#123524]/9 bg-[#FBF6EC] px-[22px] py-[18px] text-[14.5px] leading-[1.65] text-[#4A5C4E]"
                    >
                        <span
                            class="font-['Newsreader',serif] text-[20px] font-semibold text-[#2C5C3C] italic"
                            >{{ block.title }}</span
                        >
                        — {{ block.description }}
                    </p>
                    <p class="px-1 text-[14.5px] leading-[1.7] text-[#6A7C6E]">
                        {{ activeFeature.body }}
                    </p>
                </div>
            </div>
        </section>

        <section id="sitio" class="relative bg-[#EFE7D7]">
            <svg
                viewBox="0 0 1440 130"
                preserveAspectRatio="none"
                class="absolute -top-px left-0 z-20 block h-[clamp(60px,8vw,130px)] w-full"
                aria-hidden="true"
            >
                <path
                    d="M0,0 L1440,0 L1440,54 C1210,6 1000,118 700,62 C420,10 220,124 0,50 Z"
                    fill="#F6EFE1"
                />
            </svg>
            <div
                class="relative z-10 mx-auto max-w-[1320px] px-[clamp(16px,4vw,44px)] py-[clamp(90px,12vw,180px)]"
            >
                <h2
                    class="mb-[clamp(24px,3vw,40px)] max-w-[22ch] font-['Newsreader',serif] text-[clamp(34px,4.8vw,58px)] leading-[1.05] font-semibold italic"
                >
                    {{ $t('Tu propio sitio, tu propia marca') }}
                </h2>
                <div
                    class="grid items-center gap-[clamp(26px,4vw,52px)] lg:grid-cols-2"
                >
                    <div>
                        <div class="relative h-[clamp(360px,40vw,480px)]">
                            <div
                                v-for="(diamond, index) in tenantDiamonds"
                                :key="index"
                                class="absolute aspect-square"
                                :class="diamond.pos"
                            >
                                <div
                                    class="absolute inset-0 rotate-45 overflow-hidden rounded-[clamp(14px,1.6vw,26px)] border-2 border-[#EFE7D7] shadow-[0_24px_46px_-28px_rgba(10,32,20,0.55)]"
                                >
                                    <div
                                        class="absolute -inset-[42%] -rotate-45"
                                        :class="diamond.texture"
                                    />
                                </div>
                            </div>
                        </div>
                        <p
                            class="mt-2.5 text-center font-['IBM_Plex_Mono',monospace] text-[9.5px] tracking-[0.1em] text-[#93998A]"
                        >
                            {{
                                $t(
                                    'PORTADAS REALES DE SITIOS HECHOS CON MONTREE',
                                )
                            }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[15px] leading-[1.75] text-[#4A5C4E]">
                            {{
                                $t(
                                    'Montree es multi-tenant: al registrarte se crea un espacio aislado para tu agencia, con su página pública, su catálogo, sus precios y sus usuarios. Tus turistas ven tu marca — nunca la nuestra. Esta página es de Montree; la de tu agencia es tuya.',
                                )
                            }}
                        </p>
                        <ul class="mt-[22px] flex flex-col gap-2.5">
                            <li
                                v-for="item in tenantList"
                                :key="item"
                                class="flex items-start gap-3 text-[14.5px] leading-[1.55] text-[#2A3E30]"
                            >
                                <span
                                    class="mt-1.5 size-2 flex-none rotate-45 rounded-[2px] bg-[#2C5C3C]"
                                />
                                {{ item }}
                            </li>
                        </ul>
                        <div class="mt-6">
                            <p
                                class="font-['IBM_Plex_Mono',monospace] text-[10px] tracking-[0.14em] text-[#7A8B7E]"
                            >
                                {{ $t('ROLES EN CADA AGENCIA') }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2.5">
                                <span
                                    v-for="role in roles"
                                    :key="role.name"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-[#123524]/10 bg-[#FBF6EC] px-[15px] py-[9px] text-[12.5px]"
                                >
                                    <strong class="font-semibold">{{
                                        role.name
                                    }}</strong>
                                    <span class="text-[#6A7C6E]"
                                        >· {{ role.description }}</span
                                    >
                                </span>
                            </div>
                        </div>
                        <a
                            :href="props.demoUrl"
                            class="mt-6 inline-block rounded-full bg-[#123524] px-[26px] py-[13px] text-[13.5px] text-[#F6EFE1] transition-colors hover:bg-[#2C5C3C]"
                            >{{ $t('Ver un sitio de ejemplo') }}</a
                        >
                    </div>
                </div>
            </div>
            <svg
                viewBox="0 0 1440 130"
                preserveAspectRatio="none"
                class="absolute -bottom-px left-0 z-20 block h-[clamp(60px,8vw,130px)] w-full"
                aria-hidden="true"
            >
                <path
                    d="M0,50 C220,124 420,10 700,62 C1000,118 1210,6 1440,54 L1440,130 L0,130 Z"
                    fill="#F6EFE1"
                />
            </svg>
        </section>

        <section
            id="planes"
            class="mx-auto max-w-[1180px] px-[clamp(16px,4vw,44px)] py-[clamp(52px,7vw,96px)]"
        >
            <div class="text-center">
                <h2
                    class="font-['Newsreader',serif] text-[clamp(36px,5.2vw,62px)] leading-[1.05] font-semibold italic"
                >
                    {{ $t('Planes') }}
                </h2>
                <p class="mt-3 text-[14.5px] text-[#5C6E60]">
                    {{
                        $t(
                            'Empieza gratis. Paga solo cuando Montree ya te esté trayendo reservas.',
                        )
                    }}
                </p>
            </div>

            <div
                class="mt-[clamp(28px,3.6vw,44px)] rounded-[36px] border border-[#123524]/9 bg-[#FBF6EC] p-[clamp(20px,3vw,40px)]"
            >
                <div class="grid gap-[clamp(22px,3vw,42px)] lg:grid-cols-2">
                    <div class="flex flex-col gap-2.5" role="tablist">
                        <button
                            v-for="(plan, index) in plans"
                            :key="plan.name"
                            type="button"
                            role="tab"
                            class="rounded-[24px] px-[18px] py-4 text-left transition-colors duration-150"
                            :class="
                                index === planIdx
                                    ? 'bg-[#123524] text-[#FBF6EC]'
                                    : 'bg-[#F1EADB] text-[#123524] hover:bg-[#EFE7D7]'
                            "
                            :aria-selected="index === planIdx"
                            @click="planIdx = index"
                        >
                            <span class="flex items-center gap-3">
                                <span
                                    class="size-2.5 flex-none rotate-45 rounded-[2px]"
                                    :class="
                                        index === planIdx
                                            ? 'bg-[#E9D9B4]'
                                            : 'bg-[#B9C7B8]'
                                    "
                                />
                                <span>
                                    <span
                                        class="block font-['IBM_Plex_Mono',monospace] text-[9.5px] tracking-[0.14em]"
                                        :class="
                                            index === planIdx
                                                ? 'text-[#FBF6EC]/70'
                                                : 'text-[#7A8B7E]'
                                        "
                                        >{{
                                            $t('PLAN :number', {
                                                number: plan.number,
                                            })
                                        }}</span
                                    >
                                    <span
                                        class="block font-['Newsreader',serif] text-[23px] leading-[1.2] font-medium"
                                        >{{ plan.name }}</span
                                    >
                                </span>
                                <span
                                    class="ml-auto text-[13px]"
                                    :class="
                                        index === planIdx
                                            ? 'text-[#FBF6EC]/70'
                                            : 'text-[#7A8B7E]'
                                    "
                                    >{{ plan.price }}</span
                                >
                            </span>
                        </button>
                        <p
                            class="mx-1 mt-2 text-[12.5px] leading-[1.6] text-[#7A8B7E]"
                        >
                            {{
                                $t(
                                    'Estamos evaluando una tarifa reducida para agencias pequeñas, pensada solo para cubrir costos de infraestructura. Los precios pueden ajustarse durante la beta.',
                                )
                            }}
                        </p>
                    </div>

                    <div>
                        <p class="flex flex-wrap items-baseline gap-2.5">
                            <span
                                class="font-['Newsreader',serif] text-[clamp(28px,3.4vw,42px)] leading-none font-semibold italic"
                                >{{ activePlan.name }}</span
                            >
                            <span class="text-[13px] text-[#6A7C6E]">{{
                                activePlan.unit
                            }}</span>
                        </p>
                        <p
                            class="mt-3 text-[14.5px] leading-[1.7] text-[#4A5C4E]"
                        >
                            {{ activePlan.description }}
                        </p>
                        <ul class="mt-[18px] flex flex-col gap-2.5">
                            <li
                                v-for="item in activePlan.items"
                                :key="item"
                                class="flex items-start gap-[11px] text-[14px] leading-[1.55] text-[#2A3E30]"
                            >
                                <span
                                    class="mt-1.5 size-2 flex-none rotate-45 rounded-[2px] bg-[#2C5C3C]"
                                />
                                {{ item }}
                            </li>
                        </ul>
                        <Link
                            v-if="planIdx < 2"
                            :href="props.registerUrl"
                            class="mt-[22px] inline-block rounded-full bg-[#123524] px-7 py-[13px] text-[13.5px] text-[#F6EFE1] transition-colors hover:bg-[#2C5C3C]"
                            >{{ activePlan.cta }}</Link
                        >
                        <a
                            v-else
                            :href="props.contactUrl"
                            class="mt-[22px] inline-block rounded-full bg-[#123524] px-7 py-[13px] text-[13.5px] text-[#F6EFE1] transition-colors hover:bg-[#2C5C3C]"
                            >{{ activePlan.cta }}</a
                        >
                    </div>
                </div>

                <dl
                    class="mt-[clamp(24px,3vw,36px)] grid grid-cols-[repeat(auto-fit,minmax(140px,1fr))] gap-2.5"
                >
                    <div
                        v-for="spec in activePlan.specs"
                        :key="spec.key"
                        class="rounded-[22px] bg-[#F1EADB] px-[18px] py-4"
                    >
                        <dt
                            class="font-['IBM_Plex_Mono',monospace] text-[9.5px] tracking-[0.14em] text-[#7A8B7E]"
                        >
                            {{ spec.key }}
                        </dt>
                        <dd class="mt-1.5 text-[14px] leading-[1.4]">
                            {{ spec.value }}
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <section
            id="reviews"
            class="mx-auto max-w-[1220px] px-[clamp(16px,4vw,44px)] pb-[clamp(52px,7vw,90px)]"
        >
            <div class="flex flex-wrap items-end gap-[18px]">
                <div class="min-w-[260px] flex-1">
                    <h2
                        class="font-['Newsreader',serif] text-[clamp(34px,4.8vw,58px)] leading-[1.05] font-semibold italic"
                    >
                        {{ $t('Reviews') }}
                    </h2>
                    <p
                        class="mt-3 max-w-[52ch] text-[14.5px] leading-[1.7] text-[#5C6E60]"
                    >
                        {{
                            $t(
                                'Operadores de la Sierra, el Eje Cafetero y la Amazonía que ya dejaron el Excel. Estas son sus palabras.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <div class="relative mt-[clamp(26px,3.4vw,42px)]">
                <button
                    type="button"
                    :aria-label="$t('Testimonio anterior')"
                    class="absolute top-1/2 left-[clamp(-18px,-1.6vw,-8px)] z-[5] grid size-[46px] -translate-y-1/2 place-items-center rounded-full border border-[#123524]/12 bg-[#FBF6EC] shadow-[0_14px_28px_-18px_rgba(10,32,20,0.5)] transition-colors hover:bg-[#E4EDE0]"
                    @click="prevReview"
                >
                    <ArrowLeft class="size-4" />
                </button>
                <button
                    type="button"
                    :aria-label="$t('Siguiente testimonio')"
                    class="absolute top-1/2 right-[clamp(-18px,-1.6vw,-8px)] z-[5] grid size-[46px] -translate-y-1/2 place-items-center rounded-full border border-[#123524]/12 bg-[#FBF6EC] shadow-[0_14px_28px_-18px_rgba(10,32,20,0.5)] transition-colors hover:bg-[#E4EDE0]"
                    @click="nextReview"
                >
                    <ArrowRight class="size-4" />
                </button>
                <div
                    class="flex items-stretch gap-[clamp(14px,2vw,24px)] overflow-hidden"
                >
                    <figure
                        v-for="(review, index) in visibleReviews"
                        :key="review.name"
                        class="min-w-0 overflow-hidden rounded-[28px] border bg-[#FBF6EC] transition-opacity duration-[250ms]"
                        :class="[
                            index === 0
                                ? 'flex-[1.25] border-[#123524]/14 opacity-100 shadow-[0_26px_50px_-36px_rgba(10,32,20,0.45)]'
                                : 'flex-1 border-[#123524]/8',
                            index === 1 ? 'hidden opacity-92 sm:block' : '',
                            index === 2 ? 'hidden opacity-78 lg:block' : '',
                        ]"
                    >
                        <div
                            class="relative h-[clamp(120px,14vw,168px)] bg-[repeating-linear-gradient(135deg,#E4EDE0_0_10px,#D8E4D4_10px_20px)]"
                        >
                            <span
                                class="absolute bottom-[11px] left-3 rounded-full bg-[#FBF6EC]/90 px-[9px] py-[5px] font-['IBM_Plex_Mono',monospace] text-[9px] text-[#3C5445]"
                                >{{ review.photo }}</span
                            >
                        </div>
                        <div class="p-5">
                            <p
                                class="font-['IBM_Plex_Mono',monospace] text-[10px] tracking-[0.12em] text-[#2C5C3C]"
                            >
                                ★★★★★
                            </p>
                            <blockquote
                                class="mt-3 font-['Newsreader',serif] text-[17.5px] leading-[1.45] font-medium"
                            >
                                “{{ review.quote }}”
                            </blockquote>
                            <figcaption
                                class="mt-4 flex items-center gap-[11px] border-t border-[#123524]/10 pt-3.5"
                            >
                                <span
                                    class="size-[34px] flex-none rounded-full bg-[repeating-linear-gradient(135deg,#E4EDE0_0_6px,#D8E4D4_6px_12px)]"
                                />
                                <span>
                                    <span class="block text-[13.5px]">{{
                                        review.name
                                    }}</span>
                                    <span
                                        class="block text-[11.5px] text-[#6A7C6E]"
                                        >{{ review.role }}</span
                                    >
                                </span>
                            </figcaption>
                        </div>
                    </figure>
                </div>
            </div>

            <div class="mt-[22px] flex items-center gap-2">
                <span
                    v-for="(review, index) in reviews"
                    :key="review.name"
                    class="h-[7px] rounded-full transition-all duration-200"
                    :class="
                        index === reviewIdx
                            ? 'w-5 bg-[#123524]'
                            : 'w-[7px] bg-[#123524]/20'
                    "
                />
                <span
                    class="ml-auto font-['IBM_Plex_Mono',monospace] text-[10px] tracking-[0.14em] text-[#8A9A8E]"
                    >{{ $t('FEEDBACK DE AGENCIAS') }}</span
                >
            </div>
        </section>

        <section
            id="registro"
            class="relative overflow-hidden bg-[#123524] text-[#FBF6EC]"
        >
            <svg
                viewBox="0 0 1440 130"
                preserveAspectRatio="none"
                class="absolute -top-px left-0 z-20 block h-[clamp(60px,8vw,130px)] w-full"
                aria-hidden="true"
            >
                <path
                    d="M0,0 L1440,0 L1440,50 C1190,116 1000,6 700,60 C410,112 210,12 0,60 Z"
                    fill="#F6EFE1"
                />
            </svg>
            <span
                class="absolute top-[52%] left-1/2 size-[clamp(300px,40vw,540px)] -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#FBF6EC]/12"
            />
            <span
                class="absolute top-[52%] left-1/2 size-[clamp(180px,24vw,340px)] -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#FBF6EC]/9"
            />

            <div
                class="relative z-10 mx-auto max-w-[660px] px-[clamp(16px,4vw,44px)] pt-[clamp(110px,15vw,190px)] pb-[clamp(70px,9vw,120px)] text-center"
            >
                <p
                    class="font-['IBM_Plex_Mono',monospace] text-[10.5px] tracking-[0.18em] text-[#E9D9B4]"
                >
                    {{ $t('MONTREE · BETA ABIERTA') }}
                </p>
                <h2
                    class="mt-[18px] font-['Newsreader',serif] text-[clamp(36px,5.6vw,68px)] leading-[1.04] font-semibold italic"
                >
                    {{ $t('¿Listo para dejar el Excel atrás?') }}
                </h2>
                <p
                    class="mt-[18px] text-[15.5px] leading-[1.7] text-[#FBF6EC]/84"
                >
                    {{
                        $t(
                            'Los primeros 30 días son gratis y sin tarjeta. Configuras tu agencia en minutos y empiezas a recibir reservas hoy mismo.',
                        )
                    }}
                </p>
                <div class="mt-7 flex flex-wrap justify-center gap-3">
                    <Link
                        :href="props.registerUrl"
                        class="rounded-full bg-[#F6EFE1] px-8 py-[15px] text-[13.5px] font-medium text-[#123524] transition-colors hover:bg-white"
                        >{{ $t('Comenzar gratis') }}</Link
                    >
                    <a
                        :href="props.contactUrl"
                        class="rounded-full border border-[#FBF6EC]/45 px-7 py-[15px] text-[13.5px] transition-colors hover:bg-[#FBF6EC]/14"
                        >{{ $t('Hablar con el equipo') }}</a
                    >
                </div>
                <p
                    class="mt-[18px] font-['IBM_Plex_Mono',monospace] text-[10.5px] text-[#FBF6EC]/55"
                >
                    {{
                        $t(
                            'SIN TARJETA · CANCELA CUANDO QUIERAS · DATOS EN COLOMBIA',
                        )
                    }}
                </p>
            </div>
        </section>

        <footer
            class="mx-auto max-w-[1220px] px-[clamp(16px,4vw,44px)] pt-[clamp(38px,5vw,64px)] pb-[26px]"
        >
            <div
                class="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-8"
            >
                <div class="max-w-[32ch]">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="grid size-7 place-items-center rounded-full bg-[#123524] font-['Newsreader',serif] text-sm font-semibold text-[#F6EFE1]"
                            >M</span
                        >
                        <span
                            class="font-['Newsreader',serif] text-xl font-medium"
                            >Montree</span
                        >
                    </div>
                    <p class="mt-3.5 text-[13px] leading-[1.65] text-[#6A7C6E]">
                        {{
                            $t(
                                'La plataforma digital para agencias de ecoturismo que quieren crecer sin caos administrativo.',
                            )
                        }}
                    </p>
                    <a
                        href="mailto:hola@montree.co"
                        class="mt-2.5 block text-[13px] transition-colors hover:text-[#2C5C3C]"
                        >hola@montree.co</a
                    >
                </div>
                <div v-for="column in footerColumns" :key="column.title">
                    <p
                        class="font-['IBM_Plex_Mono',monospace] text-[9.5px] tracking-[0.14em] text-[#8A9A8E]"
                    >
                        {{ column.title }}
                    </p>
                    <div class="mt-3.5 flex flex-col gap-[9px] text-[13px]">
                        <a
                            v-for="link in column.links"
                            :key="link[0]"
                            :href="link[1]"
                            class="transition-colors hover:text-[#2C5C3C]"
                            >{{ link[0] }}</a
                        >
                    </div>
                </div>
            </div>
            <div
                class="mt-8 flex flex-wrap justify-between gap-3 border-t border-[#123524]/12 pt-[18px] font-['IBM_Plex_Mono',monospace] text-[10px] text-[#8A9A8E]"
            >
                <span>{{
                    $t('© 2026 MONTREE · TODOS LOS DERECHOS RESERVADOS')
                }}</span>
                <span>{{ $t('HECHO PARA EL ECOTURISMO COLOMBIANO') }}</span>
            </div>
        </footer>
    </div>
</template>
