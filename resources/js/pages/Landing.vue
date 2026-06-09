<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    ArrowRight,
    BarChart3,
    Calendar,
    CheckCircle,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CreditCard,
    Leaf,
    Mail,
    Mountain,
    Palette,
    Sparkles,
    TrendingUp,
    Users,
} from 'lucide-vue-next';

// ── Carousel ─────────────────────────────────────────────────
const carouselImages = [
    { src: 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=1400&q=80&auto=format&fit=crop', label: 'Valle de Cocora', sub: 'Quindío, Colombia' },
    { src: 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?w=1400&q=80&auto=format&fit=crop', label: 'Selva Amazónica', sub: 'Amazonas, Colombia' },
    { src: 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1400&q=80&auto=format&fit=crop', label: 'Los Andes', sub: 'Boyacá, Colombia' },
    { src: 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=1400&q=80&auto=format&fit=crop', label: 'Sierra Nevada', sub: 'Magdalena, Colombia' },
];

const currentSlide = ref(0);
let carouselTimer: ReturnType<typeof setInterval>;

const nextSlide = () => { currentSlide.value = (currentSlide.value + 1) % carouselImages.length; };
const prevSlide = () => { currentSlide.value = (currentSlide.value - 1 + carouselImages.length) % carouselImages.length; };
const goToSlide = (i: number) => { currentSlide.value = i; resetTimer(); };
const resetTimer = () => { clearInterval(carouselTimer); carouselTimer = setInterval(nextSlide, 5000); };

// ── Feature tabs ──────────────────────────────────────────────
const activeFeature = ref(0);

// ── FAQ ───────────────────────────────────────────────────────
const openFaq = ref<number | null>(null);
const toggleFaq = (i: number) => { openFaq.value = openFaq.value === i ? null : i; };

// ── Scroll reveal + header ────────────────────────────────────
onMounted(() => {
    carouselTimer = setInterval(nextSlide, 5000);

    const observer = new IntersectionObserver(
        (entries) => entries.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('revealed'); observer.unobserve(e.target); } }),
        { threshold: 0.07 },
    );
    document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));

    const header = document.querySelector('.site-header') as HTMLElement | null;
    if (header) {
        const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 40);
        window.addEventListener('scroll', onScroll, { passive: true });
    }
});

onUnmounted(() => clearInterval(carouselTimer));

// ── Data ──────────────────────────────────────────────────────
const features = [
    {
        icon: Mountain,
        title: 'Catálogo de tours',
        body: 'Tu vitrina siempre actualizada.',
        detail: 'Publica tours con fotos HD, itinerarios día a día, nivel de dificultad, qué incluye y qué no, y cupos en tiempo real. Todo editable desde el celular en segundos. Tu catálogo luce tan profesional como el de cualquier operadora internacional.',
        bullets: [
            'Galería de fotos y video optimizada para móvil y web',
            'Cupos en tiempo real: cuando se llena, se cierra automáticamente',
            'Filtros por dificultad, duración, precio y tipo de experiencia',
        ],
        image: 'https://images.unsplash.com/photo-1501854140801-50d01698950b?w=900&q=80&auto=format&fit=crop',
        imageAlt: 'Vista aérea de bosque tropical — destinos de ecoturismo',
    },
    {
        icon: Calendar,
        title: 'Reservas 24/7',
        body: 'Confirma reservas mientras duermes.',
        detail: 'El sistema recibe, confirma y gestiona reservas las 24 horas sin que respondas un solo WhatsApp. El cliente elige el tour, la fecha y el número de personas, paga en línea y recibe su confirmación automáticamente por email.',
        bullets: [
            'Confirmación automática por email y WhatsApp al instante',
            'Gestión de cupos: nunca más overbooking ni reservas dobles',
            'Historial completo por cliente: quién reservó qué y cuándo',
        ],
        image: 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?w=900&q=80&auto=format&fit=crop',
        imageAlt: 'Viajero reservando tour desde su teléfono en la naturaleza',
    },
    {
        icon: BarChart3,
        title: 'Dashboard de control',
        body: 'Tu negocio en tiempo real de un vistazo.',
        detail: 'Una vista central de todo en tiempo real. Ingresos del día, tours más vendidos, próximas salidas, cupos disponibles y rendimiento por guía. Con reportes por mes y por tour, tomas decisiones con datos, no con intuición.',
        bullets: [
            'Ingresos del día, semana y mes con comparativo vs período anterior',
            'Top de tours más vendidos y más rentables por margen neto',
            'Próximas salidas con lista de viajeros y estado de pago por persona',
        ],
        image: 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=900&q=80&auto=format&fit=crop',
        imageAlt: 'Dashboard de métricas y análisis de negocio',
    },
    {
        icon: Users,
        title: 'Gestión de equipo',
        body: 'Roles diferenciados para cada miembro.',
        detail: 'Cada persona de tu equipo ve exactamente lo que necesita, nada más. El guía ve sus tours y la lista de viajeros. El operador gestiona logística. El admin controla todo. Sin riesgo de que alguien modifique algo que no debe.',
        bullets: [
            'Roles: Admin, Operador, Guía — permisos específicos por rol',
            'Asignación de guías a tours con notificación automática',
            'Registro de actividad: quién hizo qué y cuándo en cada operación',
        ],
        image: 'https://images.unsplash.com/photo-1539635278303-d4002c07eae3?w=900&q=80&auto=format&fit=crop',
        imageAlt: 'Equipo de guías y operadores de ecoturismo',
    },
    {
        icon: CreditCard,
        title: 'Pagos integrados',
        body: 'Cobra con Stripe directo a tu cuenta.',
        detail: 'Configura si cobras el 100% anticipado o solo un depósito para asegurar el cupo. Los pagos llegan a tu cuenta bancaria en 2-3 días hábiles. Sin intermediarios que retengan tu dinero semanas. Comisiones claras desde el primer día.',
        bullets: [
            'PSE, tarjeta débito/crédito, Nequi y más medios de pago locales',
            'Configura el anticipo por tour: 30%, 50% o pago total',
            'Reembolsos automáticos según tu política de cancelación',
        ],
        image: 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?w=900&q=80&auto=format&fit=crop',
        imageAlt: 'Pago móvil y transacciones digitales seguras',
    },
    {
        icon: Palette,
        title: 'Tu marca, tu sitio',
        body: 'Dominio propio, identidad 100% tuya.',
        detail: 'Tu agencia tiene su propio sitio con logo, colores y dominio personalizado. Sin mencionar "Powered by Montree" en ningún lado. Para tus clientes, es tu plataforma 100%. Con SEO básico incluido para que Google te encuentre.',
        bullets: [
            'Subdominio gratis (tu-agencia.montree.co) o dominio propio',
            'Logo, colores primarios y foto de portada personalizables',
            'SEO incluido: meta tags, Open Graph y sitemap automático',
        ],
        image: 'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=900&q=80&auto=format&fit=crop',
        imageAlt: 'Paleta de colores y personalización de marca para tu agencia',
    },
];

const painPoints = [
    '¿Manejas las reservas por WhatsApp y pierdes clientes cuando no puedes responder a tiempo?',
    '¿Tu Excel tiene errores de cupos y ya tuviste un overbooking que te costó dinero y reputación?',
    '¿No sabes cuánto ganaste el mes pasado sin revisar diez hojas de cálculo distintas?',
];

const steps = [
    { num: '01', title: 'Regístrate gratis', body: 'Solo tu email. Sin tarjeta de crédito. Listo en 60 segundos.' },
    { num: '02', title: 'Personaliza tu agencia', body: 'Sube tu logo, elige colores y activa tu subdominio en minutos.' },
    { num: '03', title: 'Publica tus tours', body: 'Fotos, itinerarios, precios y cupos. Tan fácil como publicar en redes.' },
    { num: '04', title: 'Recibe y cobra', body: 'Reservas 24/7, pagos automáticos, dinero directo a tu cuenta.' },
];

const testimonials = [
    { avatar: 'CM', name: 'Carlos Mendoza', role: 'Fundador, EcoAndes Colombia', text: 'Pasamos de manejar todo por WhatsApp a un sistema profesional en una semana. Las reservas aumentaron y el caos administrativo desapareció.' },
    { avatar: 'LJ', name: 'Laura Jiménez', role: 'Directora, Naturaleza Viva', text: 'Antes perdíamos clientes porque no podíamos responder a tiempo. Ahora el sistema trabaja solo y nosotros nos enfocamos en lo que importa.' },
    { avatar: 'MT', name: 'Miguel Torres', role: 'Operador, Sierra Nevada Tours', text: 'Por primera vez sé exactamente cuánto gano, cuáles tours son más rentables y qué guías necesitan apoyo. Cambió todo.' },
];

const plans = [
    {
        name: 'Starter',
        price: '$99.000',
        period: '/mes',
        desc: 'Para agencias que comienzan su transformación digital',
        items: ['Hasta 5 tours activos', 'Reservas ilimitadas', 'Subdominio incluido', 'Pagos con Stripe', 'Dashboard básico', 'Soporte por email'],
        cta: 'Probar 30 días gratis',
        hot: false,
    },
    {
        name: 'Pro',
        price: '$249.000',
        period: '/mes',
        desc: 'Para agencias en crecimiento que quieren escalar sin límites',
        items: ['Tours ilimitados', 'Dominio personalizado', 'Gestión de equipo completa', 'Dashboard + métricas avanzadas', 'Newsletter integrado', 'Reseñas de clientes', 'Soporte prioritario 24/7', 'Onboarding personalizado'],
        cta: 'Probar 30 días gratis',
        hot: true,
    },
];

const faqs = [
    { q: '¿Necesito saber de tecnología para usar Montree?', a: 'No. Si sabes usar WhatsApp y Excel, puedes usar Montree. Está diseñado para operadores de campo, no programadores.' },
    { q: '¿Cuánto tarda en estar lista mi agencia?', a: 'La mayoría publica su primer tour en menos de 10 minutos. Sin configuraciones complicadas, sin técnicos, sin esperas.' },
    { q: '¿Cómo recibo el dinero de las reservas?', a: 'Usamos Stripe. El dinero se deposita directo a tu cuenta bancaria en 2–3 días hábiles tras cada pago completado.' },
    { q: '¿Puedo migrar desde Excel o WhatsApp?', a: 'Sí. Nuestro equipo te ayuda a migrar toda la información sin costo adicional durante los primeros 30 días.' },
    { q: '¿Qué pasa si quiero cancelar?', a: 'Cancelas cuando quieras, sin penalizaciones ni permanencia mínima. Exportas tu información y listo.' },
];

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const isSuperAdmin = computed(() => user.value?.isSuperAdmin ?? false);
</script>

<template>
    <Head title="Montree — Digitaliza y automatiza tu agencia de ecoturismo">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700;1,900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    </Head>

    <div class="page-root">

        <!-- ── TOP ACCENT ──────────────────────────────────────── -->
        <div class="top-accent-bar" />

        <!-- ── HEADER ──────────────────────────────────────────── -->
        <header class="site-header">
            <div class="container header-inner">
                <a href="/" class="brand">
                    <div class="brand-icon"><Leaf class="size-4" /></div>
                    <span class="brand-name">Montree</span>
                    <span class="brand-tag">Beta</span>
                </a>
                <nav class="main-nav">
                    <a href="#problem">El problema</a>
                    <a href="#features">Funciones</a>
                    <a href="#how-it-works">Cómo funciona</a>
                    <a href="#pricing">Precios</a>
                    <a href="#faq">FAQ</a>
                </nav>
                <div class="flex items-center gap-3">
                    <template v-if="user">
                        <Link
                            v-if="isSuperAdmin"
                            href="/super-admin/dashboard"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                        >
                            Ir al panel
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="inline-flex text-sm font-medium text-zinc-600 transition hover:text-zinc-900"
                        >
                            Cerrar sesión
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            href="/login"
                            class="inline-flex text-sm font-medium text-zinc-600 transition hover:text-zinc-900"
                        >
                            Iniciar sesión
                        </Link>
                        <a
                            href="#contact"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                        >
                            Comenzar gratis
                        </a>
                    </template>
                </div>
            </div>
        </header>

        <!-- ── HERO ───────────────────────────────────────────── -->
        <section class="hero-section">
            <div class="hero-texture" />
            <div class="container hero-grid">
                <!-- Left -->
                <div class="hero-left">
                    <div class="hero-badge">
                        <Sparkles class="size-3.5" />
                        Plataforma para ecoturismo colombiano
                    </div>

                    <h1 class="hero-headline">
                        Digitaliza tu<br />
                        <em>agencia</em> de<br />
                        ecoturismo.
                    </h1>

                    <p class="hero-sub">
                        Montree reemplaza el WhatsApp, el Excel y los papeles. Reservas automáticas, pagos en línea y
                        gestión de equipo en un solo lugar — que trabaja 24/7 por ti.
                    </p>

                    <div class="hero-ctas">
                        <a href="#pricing" class="btn-hero-primary">
                            Comenzar gratis
                            <ArrowRight class="size-5" />
                        </a>
                        <a href="#how-it-works" class="btn-hero-ghost">
                            Ver cómo funciona
                            <ChevronDown class="size-4" />
                        </a>
                    </div>

                    <p class="hero-disclaimer">Sin tarjeta · Cancela cuando quieras · Soporte en español</p>

                    <div class="hero-stats">
                        <div class="hero-stat">
                            <span class="hero-stat-num">85%</span>
                            <span class="hero-stat-label">menos tiempo administrativo</span>
                        </div>
                        <div class="hero-stat-divider" />
                        <div class="hero-stat">
                            <span class="hero-stat-num">3×</span>
                            <span class="hero-stat-label">más reservas en el primer mes</span>
                        </div>
                        <div class="hero-stat-divider" />
                        <div class="hero-stat">
                            <span class="hero-stat-num">&lt;10min</span>
                            <span class="hero-stat-label">para publicar tu primer tour</span>
                        </div>
                    </div>
                </div>

                <!-- Right: carousel only -->
                <div class="hero-right">
                    <div class="carousel">
                        <div
                            v-for="(img, i) in carouselImages"
                            :key="i"
                            class="carousel-slide"
                            :class="{ active: currentSlide === i }"
                            :style="{ backgroundImage: `url(${img.src})` }"
                        />
                        <div class="carousel-gradient" />

                        <button class="carousel-arrow carousel-arrow--prev" aria-label="Anterior" @click="() => { prevSlide(); resetTimer(); }">
                            <ChevronLeft class="size-4" />
                        </button>
                        <button class="carousel-arrow carousel-arrow--next" aria-label="Siguiente" @click="() => { nextSlide(); resetTimer(); }">
                            <ChevronRight class="size-4" />
                        </button>

                        <div class="carousel-footer">
                            <div class="carousel-location">
                                <span class="carousel-loc-label">{{ carouselImages[currentSlide].label }}</span>
                                <span class="carousel-loc-sub">{{ carouselImages[currentSlide].sub }}</span>
                            </div>
                            <div class="carousel-dots">
                                <button
                                    v-for="(_, i) in carouselImages"
                                    :key="i"
                                    :class="['carousel-dot', { active: currentSlide === i }]"
                                    :aria-label="`Ir a imagen ${i + 1}`"
                                    @click="goToSlide(i)"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Floating notification -->
                    <div class="float-notif">
                        <div class="float-notif-icon">
                            <CheckCircle class="size-3.5" />
                        </div>
                        <div>
                            <p class="float-notif-title">¡Nueva reserva!</p>
                            <p class="float-notif-sub">Senderismo Cocora · 2 personas</p>
                        </div>
                    </div>

                    <!-- Floating revenue -->
                    <div class="float-revenue">
                        <TrendingUp class="size-4" style="color:#4a7c59;flex-shrink:0" />
                        <div>
                            <p class="float-notif-title">+$1.2M este mes</p>
                            <p class="float-notif-sub">↑ 34% vs mes anterior</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── TRUSTED BY STRIP ───────────────────────────────── -->
        <div class="trusted-strip">
            <div class="container trusted-inner">
                <span class="trusted-label">Agencias que ya operan con Montree</span>
                <div class="trusted-logos">
                    <span v-for="n in ['EcoAndes', 'Naturaleza Viva', 'Sierra Nevada Tours', 'Parque & Co', 'SelvaVerde']" :key="n" class="trusted-logo">{{ n }}</span>
                </div>
            </div>
        </div>

        <!-- ── PROBLEMA ───────────────────────────────────────── -->
        <section id="problem" class="section-cream">
            <div class="container">
                <div class="reveal section-header">
                    <span class="eyebrow-pill">El problema real</span>
                    <h2 class="section-title">¿Te suena familiar?</h2>
                    <p class="section-sub">El 90% de las agencias de ecoturismo opera con herramientas improvisadas que frenan su crecimiento.</p>
                </div>
                <div class="pain-list">
                    <div
                        v-for="(pain, i) in painPoints"
                        :key="i"
                        class="reveal pain-item"
                        :style="`animation-delay:${i * 110}ms`"
                    >
                        <span class="pain-num">0{{ i + 1 }}</span>
                        <span class="pain-mark">&ldquo;</span>
                        <p class="pain-text">{{ pain }}</p>
                    </div>
                </div>
                <div class="reveal pain-cta">
                    <CheckCircle class="size-5" style="color:#4a7c59;flex-shrink:0" />
                    <p>Montree resuelve los tres de raíz — en menos de 10 minutos. <a href="#pricing">Pruébalo 30 días gratis →</a></p>
                </div>
            </div>
        </section>

        <!-- ── FEATURES SHOWCASE ──────────────────────────────── -->
        <section id="features" class="section-pale">
            <div class="container">
                <div class="reveal section-header">
                    <span class="eyebrow-pill">Funciones</span>
                    <h2 class="section-title">Todo lo que tu agencia necesita</h2>
                    <p class="section-sub">Diseñado para operadores de ecoturismo. Cada función resuelve un problema real de tu operación diaria.</p>
                </div>

                <div class="feat-showcase">
                    <!-- Tab list -->
                    <div class="feat-tabs">
                        <button
                            v-for="(feat, i) in features"
                            :key="i"
                            :class="['feat-tab', activeFeature === i ? 'feat-tab--active' : '']"
                            @click="activeFeature = i"
                        >
                            <div class="feat-tab-icon">
                                <component :is="feat.icon" class="size-4" />
                            </div>
                            <div class="feat-tab-text">
                                <span class="feat-tab-title">{{ feat.title }}</span>
                                <span class="feat-tab-body">{{ feat.body }}</span>
                            </div>
                            <ChevronRight class="feat-tab-arrow size-4" />
                        </button>
                    </div>

                    <!-- Detail panel -->
                    <div class="feat-detail">
                        <Transition name="feat-fade">
                            <div :key="activeFeature" class="feat-detail-inner">
                                <div class="feat-detail-header">
                                    <div class="feat-detail-icon">
                                        <component :is="features[activeFeature].icon" class="size-7" />
                                    </div>
                                    <div>
                                        <p class="feat-detail-eyebrow">Función {{ activeFeature + 1 }} de {{ features.length }}</p>
                                        <h3 class="feat-detail-title">{{ features[activeFeature].title }}</h3>
                                    </div>
                                </div>
                                <p class="feat-detail-desc">{{ features[activeFeature].detail }}</p>
                                <ul class="feat-detail-bullets">
                                    <li v-for="b in features[activeFeature].bullets" :key="b">
                                        <CheckCircle class="size-4 shrink-0" />
                                        <span>{{ b }}</span>
                                    </li>
                                </ul>
                                <div class="feat-detail-visual">
                                    <img
                                        :src="features[activeFeature].image"
                                        :alt="features[activeFeature].imageAlt"
                                        class="feat-detail-img"
                                    />
                                </div>
                                <div class="feat-detail-nav">
                                    <button v-if="activeFeature > 0" class="fdn-btn" @click="activeFeature--">← Anterior</button>
                                    <div class="fdn-dots">
                                        <span
                                            v-for="(_, i) in features"
                                            :key="i"
                                            :class="['fdn-dot', activeFeature === i ? 'fdn-dot--active' : '']"
                                            @click="activeFeature = i"
                                        />
                                    </div>
                                    <button v-if="activeFeature < features.length - 1" class="fdn-btn fdn-btn--next" @click="activeFeature++">Siguiente →</button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── HOW IT WORKS ───────────────────────────────────── -->
        <section id="how-it-works" class="section-dark-alt">
            <div class="container">
                <div class="reveal section-header">
                    <span class="eyebrow-pill eyebrow-pill--light">En 10 minutos</span>
                    <h2 class="section-title section-title--light">En línea antes de terminar tu café</h2>
                    <p class="section-sub section-sub--light">Sin configuraciones complicadas. Sin técnicos. Sin esperas.</p>
                </div>
                <div class="steps-grid">
                    <div
                        v-for="(step, i) in steps"
                        :key="step.num"
                        class="reveal step-card"
                        :style="`animation-delay:${i * 90}ms`"
                    >
                        <div class="step-num-wrap">
                            <span class="step-num">{{ step.num }}</span>
                            <div v-if="i < steps.length - 1" class="step-connector" aria-hidden="true" />
                        </div>
                        <h3 class="step-title">{{ step.title }}</h3>
                        <p class="step-body">{{ step.body }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── TESTIMONIOS ─────────────────────────────────────── -->
        <section class="section-dark">
            <div class="container">
                <div class="reveal section-header">
                    <span class="eyebrow-pill eyebrow-pill--light">Testimonios</span>
                    <h2 class="section-title section-title--light">Agencias que ya dieron el paso</h2>
                    <p class="section-sub section-sub--light">Operadores que pasaron del caos a la automatización.</p>
                </div>
                <div class="testi-grid">
                    <div
                        v-for="(t, i) in testimonials"
                        :key="t.name"
                        class="reveal testi-card"
                        :style="`animation-delay:${i * 100}ms`"
                    >
                        <span class="testi-mark">&ldquo;</span>
                        <p class="testi-text">{{ t.text }}</p>
                        <div class="testi-author">
                            <div class="testi-avatar">{{ t.avatar }}</div>
                            <div>
                                <p class="testi-name">{{ t.name }}</p>
                                <p class="testi-role">{{ t.role }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── PRICING ────────────────────────────────────────── -->
        <section id="pricing" class="section-cream">
            <div class="container">
                <div class="reveal section-header">
                    <span class="eyebrow-pill">Precios</span>
                    <h2 class="section-title">Precios claros, sin sorpresas</h2>
                    <p class="section-sub">Sin comisiones por reserva. Sin costos ocultos. 30 días completamente gratis para empezar.</p>
                </div>
                <div class="pricing-grid">
                    <div
                        v-for="plan in plans"
                        :key="plan.name"
                        :class="['reveal pricing-card', plan.hot ? 'pricing-card--hot' : '']"
                    >
                        <div v-if="plan.hot" class="pricing-badge">Más popular</div>
                        <div class="pricing-card-top">
                            <h3 class="pricing-name">{{ plan.name }}</h3>
                            <p class="pricing-desc">{{ plan.desc }}</p>
                            <div class="pricing-price">
                                <span class="pricing-amount">{{ plan.price }}</span>
                                <span class="pricing-period">{{ plan.period }}</span>
                            </div>
                            <div class="pricing-divider" />
                            <ul class="pricing-features">
                                <li v-for="item in plan.items" :key="item">
                                    <CheckCircle class="size-4 shrink-0" />{{ item }}
                                </li>
                            </ul>
                        </div>
                        <a
                            href="mailto:hola@montree.co"
                            :class="['pricing-btn', plan.hot ? 'pricing-btn--hot' : 'pricing-btn--outline']"
                        >
                            {{ plan.cta }}
                        </a>
                    </div>
                </div>
                <p class="pricing-note">
                    ¿Varias agencias o necesidades especiales?
                    <a href="mailto:hola@montree.co">Contáctanos para un plan Enterprise →</a>
                </p>
            </div>
        </section>

        <!-- ── FAQ ───────────────────────────────────────────── -->
        <section id="faq" class="section-pale">
            <div class="container container--narrow">
                <div class="reveal section-header">
                    <span class="eyebrow-pill">FAQ</span>
                    <h2 class="section-title">Preguntas frecuentes</h2>
                    <p class="section-sub">Todo lo que necesitas saber antes de empezar.</p>
                </div>
                <div class="faq-list">
                    <div
                        v-for="(faq, i) in faqs"
                        :key="i"
                        class="reveal faq-item"
                        :style="`animation-delay:${i * 55}ms`"
                    >
                        <button class="faq-trigger" @click="toggleFaq(i)">
                            <span>{{ faq.q }}</span>
                            <ChevronDown :class="['faq-chevron', openFaq === i ? 'faq-chevron--open' : '']" />
                        </button>
                        <Transition name="faq-slide">
                            <div v-if="openFaq === i" class="faq-answer">{{ faq.a }}</div>
                        </Transition>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── CTA FINAL ─────────────────────────────────────── -->
        <section class="section-cta">
            <div class="cta-bg-decor" />
            <div class="container cta-inner">
                <div class="cta-icon-wrap">
                    <Leaf class="size-6" />
                </div>
                <h2 class="cta-headline">
                    ¿Listo para dejar<br />
                    el <em>Excel</em> atrás?
                </h2>
                <p class="cta-sub">
                    Los primeros 30 días son completamente gratis. Sin tarjeta de crédito. Configuras en minutos y empiezas a recibir reservas hoy.
                </p>
                <div class="cta-actions">
                    <a href="mailto:hola@montree.co" class="btn-cta-primary">
                        <Mail class="size-5" />Comenzar gratis
                    </a>
                    <a href="mailto:hola@montree.co" class="btn-cta-ghost">Hablar con el equipo</a>
                </div>
                <div class="cta-guarantee">
                    <CheckCircle class="size-4" />
                    <span>Sin permanencia mínima · Cancela cuando quieras · Datos exportables</span>
                </div>
            </div>
        </section>

        <!-- ── FOOTER ─────────────────────────────────────────── -->
        <footer class="site-footer">
            <div class="container footer-inner">
                <div class="footer-brand-col">
                    <div class="brand">
                        <div class="brand-icon"><Leaf class="size-4" /></div>
                        <span class="brand-name" style="color:#f2ede4">Montree</span>
                    </div>
                    <p class="footer-desc">La plataforma digital para agencias de ecoturismo que quieren crecer sin el caos administrativo.</p>
                    <a href="mailto:hola@montree.co" class="footer-email"><Mail class="size-4" />hola@montree.co</a>
                </div>
                <div class="footer-links-col">
                    <h4>Producto</h4>
                    <a v-for="l in ['Funciones', 'Precios', 'FAQ', 'Casos de uso']" :key="l" href="#">{{ l }}</a>
                </div>
                <div class="footer-links-col">
                    <h4>Empresa</h4>
                    <a v-for="l in ['Sobre nosotros', 'Blog', 'Términos de uso', 'Privacidad']" :key="l" href="#">{{ l }}</a>
                </div>
            </div>
            <div class="container footer-bottom">
                <p>© {{ new Date().getFullYear() }} Montree. Todos los derechos reservados.</p>
                <p>Hecho con amor para el ecoturismo colombiano 🌿</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* ════════════════════════════════════════════════════════════
   TOKENS — on .page-root so scoped CSS can read them
════════════════════════════════════════════════════════════ */
.page-root {
    --cream: #f2ede4;
    --green-dark: #2a4a34;
    --green-darker: #1a3a2a;
    --green-mid: #4a7c59;
    --green-light: #b8cbb0;
    --green-pale: #eef4ec;
    --text-dark: #2a4a34;
    --text-muted: #5e6e61;
    --border: #d6e2d0;
    --ff-display: 'Playfair Display', Georgia, serif;
    --ff-body: 'Inter', system-ui, sans-serif;

    font-family: var(--ff-body);
    color: var(--text-dark);
    background: var(--cream);
    overflow-x: hidden;
}

.container { max-width: 1200px; margin-inline: auto; padding-inline: 1.5rem; }
.container--narrow { max-width: 760px; }

/* ════════════════════════════════════════════════════════════
   TOP ACCENT BAR
════════════════════════════════════════════════════════════ */
.top-accent-bar {
    height: 3px;
    background: linear-gradient(90deg, var(--green-dark) 0%, var(--green-mid) 50%, var(--green-light) 100%);
}

/* ════════════════════════════════════════════════════════════
   HEADER — always distinguishable
════════════════════════════════════════════════════════════ */
.site-header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(242, 237, 228, 0.9);
    border-bottom: 1px solid var(--border);
    backdrop-filter: blur(16px);
    transition: background 0.3s ease, box-shadow 0.3s ease;
}

.site-header.is-scrolled {
    background: rgba(242, 237, 228, 0.98);
    box-shadow: 0 2px 24px rgba(42, 74, 52, 0.1);
}

.header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 64px;
    gap: 2rem;
}

.brand { display: flex; align-items: center; gap: 0.55rem; text-decoration: none; }

.brand-icon {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px;
    border-radius: 9px;
    background: var(--green-mid);
    color: var(--cream);
    flex-shrink: 0;
    transition: transform 0.25s ease;
}
.brand:hover .brand-icon { transform: rotate(-8deg) scale(1.05); }

.brand-name { font-family: var(--ff-display); font-size: 1.25rem; font-weight: 700; color: var(--green-dark); letter-spacing: -0.01em; }

.brand-tag {
    font-size: 0.625rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--green-mid); background: var(--green-pale); border: 1px solid var(--border);
    padding: 1px 6px; border-radius: 4px; margin-left: 2px;
}

.main-nav { display: none; align-items: center; gap: 1.75rem; }
@media (min-width: 900px) { .main-nav { display: flex; } }

.main-nav a {
    font-size: 0.875rem; font-weight: 500; color: var(--text-muted); text-decoration: none;
    transition: color 0.2s; position: relative;
}
.main-nav a::after {
    content: ''; position: absolute; bottom: -4px; left: 0;
    width: 0; height: 2px; background: var(--green-mid); border-radius: 1px;
    transition: width 0.25s ease;
}
.main-nav a:hover { color: var(--green-dark); }
.main-nav a:hover::after { width: 100%; }

.header-actions { display: flex; align-items: center; gap: 0.75rem; }

.nav-link { display: none; font-size: 0.875rem; font-weight: 500; color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
@media (min-width: 640px) { .nav-link { display: inline-flex; } }
.nav-link:hover { color: var(--green-dark); }

.btn-cta-sm {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.5rem 1.125rem; border-radius: 8px;
    background: var(--green-dark); color: var(--cream);
    font-size: 0.875rem; font-weight: 600; text-decoration: none;
    transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(42, 74, 52, 0.18);
}
.btn-cta-sm:hover { background: var(--green-mid); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(42, 74, 52, 0.26); }

/* ════════════════════════════════════════════════════════════
   HERO
════════════════════════════════════════════════════════════ */
.hero-section {
    background: var(--cream); padding-top: 3rem; padding-bottom: 5rem;
    min-height: calc(100vh - 67px); display: flex; align-items: center;
    position: relative; overflow: hidden;
}

.hero-texture {
    position: absolute; inset: 0;
    background-image: radial-gradient(circle, rgba(74,124,89,0.07) 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events: none;
}

.hero-grid { display: grid; grid-template-columns: 1fr; gap: 3rem; align-items: center; width: 100%; position: relative; z-index: 1; }
@media (min-width: 1024px) { .hero-grid { grid-template-columns: 11fr 9fr; gap: 4rem; } }

.hero-left { display: flex; flex-direction: column; }

.hero-badge {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.4rem 1rem; border-radius: 999px; border: 1.5px solid var(--border);
    background: white; color: var(--green-mid); font-size: 0.8125rem; font-weight: 600;
    width: fit-content; margin-bottom: 1.75rem;
    box-shadow: 0 2px 12px rgba(42, 74, 52, 0.07);
    animation: badge-in 0.5s ease 0.05s both;
}

.hero-headline {
    font-family: var(--ff-display);
    font-size: clamp(2.75rem, 6vw, 5rem); font-weight: 900; line-height: 1.07;
    letter-spacing: -0.03em; color: var(--green-dark); margin-bottom: 1.5rem;
    animation: fade-up 0.6s ease 0.15s both;
}
.hero-headline em { font-style: italic; color: var(--green-mid); }

.hero-sub {
    font-size: clamp(1rem, 2vw, 1.125rem); line-height: 1.78; color: var(--text-muted);
    max-width: 520px; margin-bottom: 2.25rem; animation: fade-up 0.6s ease 0.25s both;
}

.hero-ctas { display: flex; flex-wrap: wrap; gap: 0.875rem; margin-bottom: 0.875rem; animation: fade-up 0.6s ease 0.35s both; }
.hero-disclaimer { font-size: 0.8rem; color: #a0a89a; margin-bottom: 2.75rem; animation: fade-up 0.6s ease 0.42s both; }

.btn-hero-primary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.9rem 2rem; border-radius: 10px;
    background: var(--green-dark); color: var(--cream);
    font-weight: 700; font-size: 1rem; text-decoration: none;
    box-shadow: 0 4px 20px rgba(42, 74, 52, 0.25); transition: all 0.25s ease;
    position: relative; overflow: hidden;
}
.btn-hero-primary::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transform: translateX(-100%); transition: transform 0.5s ease;
}
.btn-hero-primary:hover::after { transform: translateX(100%); }
.btn-hero-primary:hover { background: var(--green-mid); box-shadow: 0 8px 32px rgba(74, 124, 89, 0.4); transform: translateY(-2px); }

.btn-hero-ghost {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.9rem 1.75rem; border-radius: 10px; border: 1.5px solid var(--border);
    color: var(--text-dark); font-weight: 600; font-size: 1rem; text-decoration: none;
    transition: all 0.22s ease;
}
.btn-hero-ghost:hover { border-color: var(--green-mid); color: var(--green-mid); background: var(--green-pale); }

.hero-stats { display: flex; align-items: center; gap: 1.5rem; animation: fade-up 0.6s ease 0.5s both; }
.hero-stat { display: flex; flex-direction: column; gap: 0.2rem; }
.hero-stat-num { font-family: var(--ff-display); font-size: 1.75rem; font-weight: 900; color: var(--green-dark); line-height: 1; letter-spacing: -0.02em; }
.hero-stat-label { font-size: 0.6875rem; color: var(--text-muted); line-height: 1.3; max-width: 90px; }
.hero-stat-divider { width: 1px; height: 38px; background: var(--border); flex-shrink: 0; }

/* ── Hero right ── */
.hero-right { display: none; position: relative; height: 540px; border-radius: 20px; overflow: visible; animation: fade-up 0.7s ease 0.2s both; }
@media (min-width: 1024px) { .hero-right { display: block; } }

.carousel {
    position: absolute; inset: 0; border-radius: 20px; overflow: hidden;
    background: var(--green-darker);
    box-shadow: 0 24px 80px rgba(42, 74, 52, 0.24), 0 0 0 1px rgba(42, 74, 52, 0.1);
}

.carousel-slide {
    position: absolute; inset: 0;
    background-size: cover; background-position: center;
    opacity: 0; transition: opacity 1.2s ease;
}
.carousel-slide.active { opacity: 1; }

.carousel-gradient {
    position: absolute; inset: 0;
    background: linear-gradient(to bottom, rgba(15,31,21,0.1) 0%, rgba(15,31,21,0) 40%, rgba(15,31,21,0.72) 100%);
    z-index: 1;
}

.carousel-arrow {
    position: absolute; top: 50%; transform: translateY(-50%); z-index: 3;
    display: flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 50%;
    border: 1px solid rgba(242,237,228,0.3); background: rgba(15,31,21,0.5);
    color: var(--cream); cursor: pointer; backdrop-filter: blur(6px);
    transition: all 0.2s ease; opacity: 0;
}
.carousel:hover .carousel-arrow { opacity: 1; }
.carousel-arrow:hover { background: rgba(74,124,89,0.75); border-color: rgba(242,237,228,0.5); }
.carousel-arrow--prev { left: 14px; }
.carousel-arrow--next { right: 14px; }

.carousel-footer {
    position: absolute; bottom: 0; left: 0; right: 0;
    padding: 1rem 1.25rem; display: flex; align-items: flex-end; justify-content: space-between; z-index: 2;
}
.carousel-location { display: flex; flex-direction: column; gap: 2px; }
.carousel-loc-label { font-family: var(--ff-display); font-size: 0.9375rem; font-weight: 700; color: var(--cream); text-shadow: 0 1px 4px rgba(0,0,0,0.4); }
.carousel-loc-sub { font-size: 0.75rem; color: rgba(242,237,228,0.72); text-shadow: 0 1px 3px rgba(0,0,0,0.3); }

.carousel-dots { display: flex; gap: 6px; align-items: center; }
.carousel-dot { width: 6px; height: 6px; border-radius: 50%; border: none; background: rgba(242,237,228,0.4); cursor: pointer; transition: all 0.3s ease; padding: 0; }
.carousel-dot.active { background: var(--cream); width: 20px; border-radius: 3px; }

.float-notif,
.float-revenue {
    position: absolute; display: flex; align-items: center; gap: 0.625rem;
    padding: 0.65rem 0.9rem; border-radius: 12px;
    background: rgba(242,237,228,0.97); border: 1px solid var(--border);
    box-shadow: 0 8px 28px rgba(42, 74, 52, 0.16); backdrop-filter: blur(8px); z-index: 4;
}
.float-notif { bottom: 80px; left: -18px; animation: float-up-gentle 3.6s ease-in-out infinite; }
.float-revenue { bottom: 18px; left: -18px; animation: float-down-gentle 4.2s ease-in-out infinite; }

.float-notif-icon {
    display: flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--green-pale); color: var(--green-mid); flex-shrink: 0;
}
.float-notif-title { font-size: 0.71875rem; font-weight: 700; color: var(--green-dark); line-height: 1.2; white-space: nowrap; }
.float-notif-sub { font-size: 0.625rem; color: var(--text-muted); white-space: nowrap; }

/* ════════════════════════════════════════════════════════════
   TRUSTED STRIP
════════════════════════════════════════════════════════════ */
.trusted-strip { background: white; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding-block: 1.25rem; }
.trusted-inner { display: flex; flex-direction: column; align-items: center; gap: 1rem; }
@media (min-width: 640px) { .trusted-inner { flex-direction: row; justify-content: center; } }
.trusted-label { font-size: 0.75rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); white-space: nowrap; }
.trusted-logos { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1.5rem; justify-content: center; }
.trusted-logo { font-family: var(--ff-display); font-size: 0.9375rem; font-weight: 700; color: var(--green-dark); opacity: 0.35; transition: opacity 0.2s; }
.trusted-logo:hover { opacity: 0.6; }

/* ════════════════════════════════════════════════════════════
   SECTION BASES
════════════════════════════════════════════════════════════ */
.section-cream { background: var(--cream); padding-block: 6rem; }
.section-pale  { background: var(--green-pale); padding-block: 6rem; }
.section-dark  { background: var(--green-dark); padding-block: 6rem; }
.section-dark-alt { background: var(--green-darker); padding-block: 6rem; }

.section-cta { background: var(--green-darker); padding-block: 7rem; text-align: center; position: relative; overflow: hidden; }

.cta-bg-decor {
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(74,124,89,0.22) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 50%, rgba(74,124,89,0.14) 0%, transparent 60%);
    pointer-events: none;
}

.section-header { text-align: center; margin-bottom: 3.5rem; }

.eyebrow-pill {
    display: inline-flex; align-items: center;
    padding: 0.3rem 0.875rem; border-radius: 999px;
    background: white; border: 1.5px solid var(--border);
    font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--green-mid); margin-bottom: 1.125rem;
    box-shadow: 0 1px 4px rgba(42,74,52,0.07);
}
.eyebrow-pill--light { background: rgba(255,255,255,0.1); border-color: rgba(184,203,176,0.35); color: var(--green-light); }

.section-title { font-family: var(--ff-display); font-size: clamp(1.875rem, 3.5vw, 2.875rem); font-weight: 900; letter-spacing: -0.025em; color: var(--text-dark); line-height: 1.1; margin-bottom: 1rem; }
.section-title--light { color: var(--cream); }

.section-sub { font-size: 1.0625rem; line-height: 1.72; color: var(--text-muted); max-width: 560px; margin-inline: auto; }
.section-sub--light { color: rgba(242,237,228,0.72); }

/* ════════════════════════════════════════════════════════════
   PROBLEM
════════════════════════════════════════════════════════════ */
.pain-list { max-width: 800px; margin-inline: auto; margin-bottom: 3rem; }

.pain-item {
    position: relative; padding: 2rem 1rem 2rem 4.5rem;
    border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 0.5rem;
}
.pain-item:first-child { border-top: 1px solid var(--border); }

.pain-num { position: absolute; left: 0; top: 2rem; font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.08em; color: var(--green-mid); opacity: 0.6; }

.pain-mark { font-family: var(--ff-display); font-size: 4rem; font-weight: 900; color: var(--green-light); line-height: 0.9; opacity: 0.7; }

.pain-text { font-family: var(--ff-display); font-size: clamp(1.125rem, 2vw, 1.375rem); font-style: italic; color: var(--green-dark); line-height: 1.55; }

.pain-cta {
    display: flex; align-items: center; gap: 0.625rem;
    font-size: 0.9375rem; color: var(--text-muted);
    max-width: 800px; margin-inline: auto;
    background: white; border: 1px solid var(--border); border-radius: 12px; padding: 1rem 1.25rem;
}
.pain-cta a { color: var(--green-mid); font-weight: 600; text-decoration: none; }
.pain-cta a:hover { color: var(--green-dark); }

/* ════════════════════════════════════════════════════════════
   FEATURES SHOWCASE (tabbed)
════════════════════════════════════════════════════════════ */
.feat-showcase { display: grid; grid-template-columns: 1fr; gap: 2rem; align-items: start; }
@media (min-width: 900px) { .feat-showcase { grid-template-columns: 340px 1fr; gap: 2.5rem; } }

.feat-tabs { display: flex; flex-direction: column; gap: 0.5rem; }

.feat-tab {
    display: flex; align-items: center; gap: 0.875rem;
    padding: 1rem 1.125rem; border-radius: 14px; border: 1.5px solid transparent;
    background: transparent; cursor: pointer; text-align: left;
    transition: all 0.2s ease; width: 100%; font-family: var(--ff-body);
}
.feat-tab:hover { background: white; border-color: var(--border); }
.feat-tab--active { background: white; border-color: var(--green-mid); box-shadow: 0 4px 20px rgba(42,74,52,0.09); }

.feat-tab-icon {
    display: flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--green-pale); color: var(--text-muted); flex-shrink: 0;
    transition: background 0.2s, color 0.2s;
}
.feat-tab--active .feat-tab-icon { background: var(--green-mid); color: white; }

.feat-tab-text { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.feat-tab-title { font-size: 0.9375rem; font-weight: 700; color: var(--text-dark); }
.feat-tab-body { font-size: 0.78125rem; color: var(--text-muted); }
.feat-tab--active .feat-tab-title { color: var(--green-dark); }

.feat-tab-arrow { color: var(--text-muted); opacity: 0; transition: opacity 0.2s; flex-shrink: 0; }
.feat-tab--active .feat-tab-arrow { opacity: 1; color: var(--green-mid); }

.feat-detail {
    border-radius: 20px; background: white; border: 1.5px solid var(--border);
    box-shadow: 0 4px 32px rgba(42,74,52,0.07); overflow: hidden; min-height: 440px;
    position: relative;
}

.feat-detail-inner { padding: 2.5rem; display: flex; flex-direction: column; gap: 1.5rem; }

.feat-detail-header { display: flex; align-items: flex-start; gap: 1rem; }

.feat-detail-icon {
    display: flex; align-items: center; justify-content: center;
    width: 60px; height: 60px; border-radius: 16px;
    background: var(--green-pale); color: var(--green-mid);
    flex-shrink: 0; border: 1.5px solid var(--border);
}

.feat-detail-eyebrow { font-size: 0.6875rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.25rem; }

.feat-detail-title { font-family: var(--ff-display); font-size: 1.625rem; font-weight: 900; color: var(--green-dark); letter-spacing: -0.02em; line-height: 1.15; }

.feat-detail-desc { font-size: 1rem; line-height: 1.78; color: var(--text-muted); }

.feat-detail-bullets { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem; }
.feat-detail-bullets li { display: flex; align-items: flex-start; gap: 0.625rem; font-size: 0.9375rem; color: var(--text-dark); line-height: 1.5; }
.feat-detail-bullets li svg { color: var(--green-mid); flex-shrink: 0; margin-top: 2px; }

.feat-detail-visual {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--border);
    background: var(--green-pale);
    aspect-ratio: 16 / 7;
    flex-shrink: 0;
}

.feat-detail-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.5s ease;
}

.feat-detail-visual:hover .feat-detail-img {
    transform: scale(1.03);
}

.feat-detail-nav { display: flex; align-items: center; gap: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border); }
.fdn-btn { background: none; border: none; font-size: 0.8125rem; font-weight: 600; color: var(--green-mid); cursor: pointer; font-family: var(--ff-body); transition: color 0.2s; padding: 0.25rem 0; }
.fdn-btn:hover { color: var(--green-dark); }
.fdn-btn--next { margin-left: auto; }
.fdn-dots { display: flex; gap: 5px; align-items: center; flex: 1; justify-content: center; }
.fdn-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--border); cursor: pointer; transition: all 0.25s; }
.fdn-dot--active { background: var(--green-mid); width: 18px; border-radius: 3px; }

/* Feature transition */
.feat-fade-enter-active { transition: opacity 0.22s ease, transform 0.22s ease; }
.feat-fade-leave-active { transition: opacity 0.15s ease; position: absolute; inset: 0; }
.feat-fade-enter-from { opacity: 0; transform: translateX(14px); }
.feat-fade-leave-to { opacity: 0; }

/* ════════════════════════════════════════════════════════════
   HOW IT WORKS
════════════════════════════════════════════════════════════ */
.steps-grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }
@media (min-width: 900px) { .steps-grid { grid-template-columns: repeat(4, 1fr); gap: 1.5rem; } }

.step-card { position: relative; }

.step-num-wrap { position: relative; display: flex; align-items: center; margin-bottom: 1.25rem; }

.step-num {
    font-family: var(--ff-display); font-size: 2.25rem; font-weight: 900;
    color: var(--green-light); line-height: 1;
    width: 64px; height: 64px; border-radius: 16px;
    background: rgba(184, 203, 176, 0.12); border: 1.5px solid rgba(184, 203, 176, 0.28);
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s ease; flex-shrink: 0;
}
.step-card:hover .step-num { background: rgba(184,203,176,0.22); border-color: rgba(184,203,176,0.5); }

.step-connector {
    display: none; position: absolute; right: -0.75rem; top: 50%;
    width: 1.5rem; height: 1px;
    background: linear-gradient(to right, rgba(184,203,176,0.5), transparent);
}
@media (min-width: 900px) { .step-connector { display: block; } }

.step-title { font-family: var(--ff-display); font-size: 1.0625rem; font-weight: 700; color: var(--cream); margin-bottom: 0.5rem; }
.step-body { font-size: 0.875rem; line-height: 1.65; color: rgba(242,237,228,0.6); }

/* ════════════════════════════════════════════════════════════
   TESTIMONIALS
════════════════════════════════════════════════════════════ */
.testi-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
@media (min-width: 768px) { .testi-grid { grid-template-columns: repeat(3, 1fr); } }

.testi-card {
    display: flex; flex-direction: column; gap: 1.25rem; padding: 2rem;
    border-radius: 16px; border: 1px solid rgba(184,203,176,0.18);
    background: rgba(255,255,255,0.04); transition: all 0.25s ease;
}
.testi-card:hover { background: rgba(255,255,255,0.08); border-color: rgba(184,203,176,0.32); transform: translateY(-3px); }

.testi-mark { font-family: var(--ff-display); font-size: 4.5rem; font-weight: 900; color: var(--green-mid); line-height: 0.7; opacity: 0.45; }
.testi-text { font-size: 0.9375rem; line-height: 1.75; color: rgba(242,237,228,0.9); flex: 1; }

.testi-author { display: flex; align-items: center; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid rgba(184,203,176,0.14); }
.testi-avatar { display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; background: var(--green-mid); color: var(--cream); font-size: 0.75rem; font-weight: 700; flex-shrink: 0; }
.testi-name { font-size: 0.875rem; font-weight: 700; color: var(--cream); }
.testi-role { font-size: 0.75rem; color: rgba(242,237,228,0.5); }

/* ════════════════════════════════════════════════════════════
   PRICING — button always pinned to bottom via flex
════════════════════════════════════════════════════════════ */
.pricing-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; max-width: 840px; margin-inline: auto; }
@media (min-width: 768px) { .pricing-grid { grid-template-columns: repeat(2, 1fr); } }

.pricing-card {
    position: relative; display: flex; flex-direction: column;
    padding: 2.25rem; border-radius: 20px; border: 1.5px solid var(--border);
    background: white; box-shadow: 0 2px 16px rgba(42,74,52,0.05);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.pricing-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(42,74,52,0.1); }
.pricing-card--hot { background: var(--green-dark); border-color: var(--green-mid); box-shadow: 0 0 0 1px rgba(74,124,89,0.3), 0 16px 60px rgba(42,74,52,0.2); }

/* Top content grows to fill available space, pushing button down */
.pricing-card-top { flex: 1; display: flex; flex-direction: column; }

.pricing-badge { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); padding: 0.25rem 0.875rem; border-radius: 999px; background: var(--green-mid); color: var(--cream); font-size: 0.75rem; font-weight: 700; white-space: nowrap; box-shadow: 0 2px 8px rgba(74,124,89,0.3); }

.pricing-name { font-family: var(--ff-display); font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem; }
.pricing-card--hot .pricing-name { color: var(--cream); }

.pricing-desc { font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.5rem; line-height: 1.5; }
.pricing-card--hot .pricing-desc { color: rgba(242,237,228,0.62); }

.pricing-price { display: flex; align-items: baseline; gap: 0.25rem; margin-bottom: 1.5rem; }
.pricing-amount { font-family: var(--ff-display); font-size: 2.5rem; font-weight: 900; color: var(--text-dark); letter-spacing: -0.02em; }
.pricing-card--hot .pricing-amount { color: var(--cream); }
.pricing-period { font-size: 0.9rem; color: var(--text-muted); }
.pricing-card--hot .pricing-period { color: rgba(242,237,228,0.5); }

.pricing-divider { height: 1px; background: var(--border); margin-bottom: 1.5rem; }
.pricing-card--hot .pricing-divider { background: rgba(184,203,176,0.2); }

/* flex:1 makes the list grow, pushing button to bottom */
.pricing-features { list-style: none; padding: 0; margin: 0 0 2rem; display: flex; flex-direction: column; gap: 0.75rem; flex: 1; }
.pricing-features li { display: flex; align-items: center; gap: 0.625rem; font-size: 0.9rem; color: var(--text-dark); }
.pricing-card--hot .pricing-features li { color: rgba(242,237,228,0.88); }
.pricing-features li svg { color: var(--green-mid); flex-shrink: 0; }
.pricing-card--hot .pricing-features li svg { color: var(--green-light); }

/* Button at the very bottom, always */
.pricing-btn { display: flex; width: 100%; align-items: center; justify-content: center; padding: 0.9rem; border-radius: 12px; font-weight: 700; font-size: 0.9375rem; text-decoration: none; transition: all 0.2s ease; }
.pricing-btn--outline { border: 1.5px solid var(--green-mid); color: var(--green-mid); background: transparent; }
.pricing-btn--outline:hover { background: var(--green-pale); }
.pricing-btn--hot { background: var(--green-mid); color: var(--cream); box-shadow: 0 4px 20px rgba(74,124,89,0.3); }
.pricing-btn--hot:hover { background: #3a6347; box-shadow: 0 6px 28px rgba(74,124,89,0.45); transform: translateY(-1px); }

.pricing-note { text-align: center; margin-top: 2rem; font-size: 0.875rem; color: var(--text-muted); }
.pricing-note a { color: var(--green-mid); text-decoration: none; font-weight: 600; }
.pricing-note a:hover { text-decoration: underline; }

/* ════════════════════════════════════════════════════════════
   FAQ
════════════════════════════════════════════════════════════ */
.faq-list { display: flex; flex-direction: column; }
.faq-item { border-bottom: 1px solid var(--border); }
.faq-item:first-child { border-top: 1px solid var(--border); }

.faq-trigger {
    width: 100%; display: flex; align-items: center; justify-content: space-between;
    padding: 1.375rem 0; text-align: left; background: transparent; border: none; cursor: pointer;
    color: var(--text-dark); font-size: 0.9375rem; font-weight: 600; font-family: var(--ff-body);
    gap: 1rem; transition: color 0.2s;
}
.faq-trigger:hover { color: var(--green-mid); }

.faq-chevron { width: 18px; height: 18px; flex-shrink: 0; color: var(--text-muted); transition: transform 0.3s ease, color 0.2s; }
.faq-chevron--open { transform: rotate(180deg); color: var(--green-mid); }

.faq-answer { padding-bottom: 1.375rem; font-size: 0.9rem; line-height: 1.78; color: var(--text-muted); }

.faq-slide-enter-active { transition: all 0.28s ease; overflow: hidden; }
.faq-slide-leave-active { transition: all 0.2s ease; overflow: hidden; }
.faq-slide-enter-from, .faq-slide-leave-to { opacity: 0; max-height: 0; padding-bottom: 0; }
.faq-slide-enter-to, .faq-slide-leave-from { opacity: 1; max-height: 200px; }

/* ════════════════════════════════════════════════════════════
   CTA
════════════════════════════════════════════════════════════ */
.cta-inner { position: relative; z-index: 1; }

.cta-icon-wrap {
    display: inline-flex; align-items: center; justify-content: center;
    width: 60px; height: 60px; border-radius: 18px;
    background: rgba(74,124,89,0.2); border: 1.5px solid rgba(184,203,176,0.25);
    color: var(--green-light); margin-bottom: 2rem;
    animation: cta-breathe 3s ease-in-out infinite;
}

.cta-headline { font-family: var(--ff-display); font-size: clamp(2.25rem, 5vw, 4.25rem); font-weight: 900; letter-spacing: -0.03em; line-height: 1.08; color: var(--cream); margin-bottom: 1.5rem; }
.cta-headline em { font-style: italic; color: var(--green-light); }

.cta-sub { font-size: 1.0625rem; line-height: 1.72; color: rgba(242,237,228,0.7); max-width: 520px; margin-inline: auto; margin-bottom: 2.5rem; }

.cta-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.875rem; margin-bottom: 1.75rem; }

.btn-cta-primary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.9rem 2rem; border-radius: 10px; background: var(--cream);
    color: var(--green-dark); font-weight: 700; font-size: 1rem; text-decoration: none;
    box-shadow: 0 4px 24px rgba(242,237,228,0.12); transition: all 0.22s ease;
}
.btn-cta-primary:hover { background: #fff; box-shadow: 0 8px 36px rgba(242,237,228,0.22); transform: translateY(-2px); }

.btn-cta-ghost {
    display: inline-flex; align-items: center; padding: 0.9rem 1.75rem; border-radius: 10px;
    border: 1px solid rgba(242,237,228,0.22); color: rgba(242,237,228,0.8);
    font-weight: 600; font-size: 1rem; text-decoration: none; transition: all 0.22s ease;
}
.btn-cta-ghost:hover { border-color: rgba(242,237,228,0.4); color: var(--cream); background: rgba(242,237,228,0.07); }

.cta-guarantee { display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; color: rgba(242,237,228,0.45); }
.cta-guarantee svg { color: rgba(184,203,176,0.5); flex-shrink: 0; }

/* ════════════════════════════════════════════════════════════
   FOOTER
════════════════════════════════════════════════════════════ */
.site-footer { background: #1a3a2a; border-top: 1px solid rgba(184,203,176,0.1); padding-top: 3.5rem; padding-bottom: 1.5rem; }

.footer-inner { display: grid; grid-template-columns: 1fr; gap: 2.5rem; margin-bottom: 3rem; }
@media (min-width: 768px) { .footer-inner { grid-template-columns: 1.6fr 1fr 1fr; } }

.footer-brand-col { display: flex; flex-direction: column; gap: 0.75rem; }
.footer-desc { font-size: 0.875rem; line-height: 1.65; color: rgba(242,237,228,0.45); max-width: 300px; }
.footer-email { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.875rem; color: rgba(242,237,228,0.5); text-decoration: none; transition: color 0.2s; }
.footer-email:hover { color: var(--green-light); }

.footer-links-col { display: flex; flex-direction: column; gap: 0.625rem; }
.footer-links-col h4 { font-size: 0.8125rem; font-weight: 700; color: var(--cream); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.25rem; }
.footer-links-col a { font-size: 0.875rem; color: rgba(242,237,228,0.45); text-decoration: none; transition: color 0.2s; }
.footer-links-col a:hover { color: var(--green-light); }

.footer-bottom { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding-top: 2rem; border-top: 1px solid rgba(184,203,176,0.1); }
@media (min-width: 640px) { .footer-bottom { flex-direction: row; justify-content: space-between; } }
.footer-bottom p { font-size: 0.8125rem; color: rgba(242,237,228,0.28); }

/* ════════════════════════════════════════════════════════════
   ANIMATIONS
════════════════════════════════════════════════════════════ */
@keyframes badge-in {
    from { opacity: 0; transform: translateY(10px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes fade-up {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
}

@keyframes float-up-gentle {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-7px); }
}

@keyframes float-down-gentle {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(8px); }
}

@keyframes cta-breathe {
    0%, 100% { box-shadow: 0 0 0 0 rgba(74,124,89,0); }
    50%       { box-shadow: 0 0 0 12px rgba(74,124,89,0.07); }
}

.reveal { opacity: 0; transform: translateY(26px); }
.reveal.revealed { animation: fade-up 0.65s cubic-bezier(0.22, 1, 0.36, 1) forwards; }
</style>
