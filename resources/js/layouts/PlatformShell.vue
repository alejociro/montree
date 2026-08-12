<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Leaf, Mail } from 'lucide-vue-next';
import { computed, onMounted } from 'vue';
import { start } from '@/routes/onboarding';

defineProps<{
    title: string;
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const isSuperAdmin = computed(() => user.value?.isSuperAdmin ?? false);

onMounted(() => {
    const observer = new IntersectionObserver(
        (entries) =>
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            }),
        { threshold: 0.07 },
    );
    document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));

    const header = document.querySelector('.site-header') as HTMLElement | null;

    if (!header) {
        return;
    }

    const onScroll = () =>
        header.classList.toggle('is-scrolled', window.scrollY > 40);

    window.addEventListener('scroll', onScroll, { passive: true });
});
</script>

<template>
    <Head :title="title">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin="anonymous"
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700;1,900&family=Inter:wght@400;500;600;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="page-root">
        <div class="top-accent-bar" />

        <header class="site-header">
            <div class="header-inner container">
                <a href="/" class="brand">
                    <div class="brand-icon"><Leaf class="size-4" /></div>
                    <span class="brand-name">Montree</span>
                    <span class="brand-tag">Beta</span>
                </a>
                <nav class="main-nav">
                    <a href="/#features">Funciones</a>
                    <a href="/#how-it-works">Cómo funciona</a>
                    <a href="/#pricing">Precios</a>
                    <a href="/faq">FAQ</a>
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
                    </template>
                    <template v-else>
                        <Link
                            href="/login"
                            class="inline-flex text-sm font-medium text-zinc-600 transition hover:text-zinc-900"
                        >
                            Iniciar sesión
                        </Link>
                        <Link
                            :href="start().url"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                        >
                            Comenzar gratis
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <main>
            <slot />
        </main>

        <footer class="site-footer">
            <div class="footer-inner container">
                <div class="footer-brand-col">
                    <div class="brand">
                        <div class="brand-icon"><Leaf class="size-4" /></div>
                        <span class="brand-name" style="color: #f2ede4"
                            >Montree</span
                        >
                    </div>
                    <p class="footer-desc">
                        La plataforma digital para agencias de ecoturismo que
                        quieren crecer sin el caos administrativo.
                    </p>
                    <a href="mailto:hola@montree.co" class="footer-email"
                        ><Mail class="size-4" />hola@montree.co</a
                    >
                </div>
                <div class="footer-links-col">
                    <h4>Producto</h4>
                    <a href="/#features">Funciones</a>
                    <a href="/#pricing">Precios</a>
                    <a href="/faq">Preguntas frecuentes</a>
                </div>
                <div class="footer-links-col">
                    <h4>Legal</h4>
                    <a href="/politica-de-pago">Política de pago</a>
                    <a href="/politica-de-cancelacion">
                        Política de cancelación
                    </a>
                </div>
            </div>
            <div class="footer-bottom container">
                <p>
                    © {{ new Date().getFullYear() }} Montree. Todos los derechos
                    reservados.
                </p>
                <p>Hecho con amor para el ecoturismo colombiano 🌿</p>
            </div>
        </footer>
    </div>
</template>

<style>
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

.page-root .container {
    max-width: 1200px;
    margin-inline: auto;
    padding-inline: 1.5rem;
}
.page-root .container--narrow {
    max-width: 760px;
}

/* ════════════════════════════════════════════════════════════
   TOP ACCENT BAR
════════════════════════════════════════════════════════════ */
.page-root .top-accent-bar {
    height: 3px;
    background: linear-gradient(
        90deg,
        var(--green-dark) 0%,
        var(--green-mid) 50%,
        var(--green-light) 100%
    );
}

/* ════════════════════════════════════════════════════════════
   HEADER — always distinguishable
════════════════════════════════════════════════════════════ */
.page-root .site-header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(242, 237, 228, 0.9);
    border-bottom: 1px solid var(--border);
    backdrop-filter: blur(16px);
    transition:
        background 0.3s ease,
        box-shadow 0.3s ease;
}

.page-root .site-header.is-scrolled {
    background: rgba(242, 237, 228, 0.98);
    box-shadow: 0 2px 24px rgba(42, 74, 52, 0.1);
}

.page-root .header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 64px;
    gap: 2rem;
}

.page-root .brand {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    text-decoration: none;
}

.page-root .brand-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: var(--green-mid);
    color: var(--cream);
    flex-shrink: 0;
    transition: transform 0.25s ease;
}
.page-root .brand:hover .brand-icon {
    transform: rotate(-8deg) scale(1.05);
}

.page-root .brand-name {
    font-family: var(--ff-display);
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--green-dark);
    letter-spacing: -0.01em;
}

.page-root .brand-tag {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--green-mid);
    background: var(--green-pale);
    border: 1px solid var(--border);
    padding: 1px 6px;
    border-radius: 4px;
    margin-left: 2px;
}

.page-root .main-nav {
    display: none;
    align-items: center;
    gap: 1.75rem;
}
@media (min-width: 900px) {
    .page-root .main-nav {
        display: flex;
    }
}

.page-root .main-nav a {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-muted);
    text-decoration: none;
    transition: color 0.2s;
    position: relative;
}
.page-root .main-nav a::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--green-mid);
    border-radius: 1px;
    transition: width 0.25s ease;
}
.page-root .main-nav a:hover {
    color: var(--green-dark);
}
.page-root .main-nav a:hover::after {
    width: 100%;
}

.page-root .header-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-root .nav-link {
    display: none;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-muted);
    text-decoration: none;
    transition: color 0.2s;
}
@media (min-width: 640px) {
    .page-root .nav-link {
        display: inline-flex;
    }
}
.page-root .nav-link:hover {
    color: var(--green-dark);
}

.page-root .btn-cta-sm {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 1.125rem;
    border-radius: 8px;
    background: var(--green-dark);
    color: var(--cream);
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(42, 74, 52, 0.18);
}
.page-root .btn-cta-sm:hover {
    background: var(--green-mid);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(42, 74, 52, 0.26);
}

/* ════════════════════════════════════════════════════════════
   SECTION BASES
════════════════════════════════════════════════════════════ */
.page-root .section-cream {
    background: var(--cream);
    padding-block: 6rem;
}
.page-root .section-pale {
    background: var(--green-pale);
    padding-block: 6rem;
}
.page-root .section-dark {
    background: var(--green-dark);
    padding-block: 6rem;
}
.page-root .section-dark-alt {
    background: var(--green-darker);
    padding-block: 6rem;
}

.page-root .section-cta {
    background: var(--green-darker);
    padding-block: 7rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.page-root .cta-bg-decor {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(
            ellipse at 20% 50%,
            rgba(74, 124, 89, 0.22) 0%,
            transparent 60%
        ),
        radial-gradient(
            ellipse at 80% 50%,
            rgba(74, 124, 89, 0.14) 0%,
            transparent 60%
        );
    pointer-events: none;
}

.page-root .section-header {
    text-align: center;
    margin-bottom: 3.5rem;
}

.page-root .eyebrow-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.875rem;
    border-radius: 999px;
    background: white;
    border: 1.5px solid var(--border);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--green-mid);
    margin-bottom: 1.125rem;
    box-shadow: 0 1px 4px rgba(42, 74, 52, 0.07);
}
.page-root .eyebrow-pill--light {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(184, 203, 176, 0.35);
    color: var(--green-light);
}

.page-root .section-title {
    font-family: var(--ff-display);
    font-size: clamp(1.875rem, 3.5vw, 2.875rem);
    font-weight: 900;
    letter-spacing: -0.025em;
    color: var(--text-dark);
    line-height: 1.1;
    margin-bottom: 1rem;
}
.page-root .section-title--light {
    color: var(--cream);
}

.page-root .section-sub {
    font-size: 1.0625rem;
    line-height: 1.72;
    color: var(--text-muted);
    max-width: 560px;
    margin-inline: auto;
}
.page-root .section-sub--light {
    color: rgba(242, 237, 228, 0.72);
}

/* ════════════════════════════════════════════════════════════
   FOOTER
════════════════════════════════════════════════════════════ */
.page-root .site-footer {
    background: #1a3a2a;
    border-top: 1px solid rgba(184, 203, 176, 0.1);
    padding-top: 3.5rem;
    padding-bottom: 1.5rem;
}

.page-root .footer-inner {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2.5rem;
    margin-bottom: 3rem;
}
@media (min-width: 768px) {
    .page-root .footer-inner {
        grid-template-columns: 1.6fr 1fr 1fr;
    }
}

.page-root .footer-brand-col {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.page-root .footer-desc {
    font-size: 0.875rem;
    line-height: 1.65;
    color: rgba(242, 237, 228, 0.45);
    max-width: 300px;
}
.page-root .footer-email {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.875rem;
    color: rgba(242, 237, 228, 0.5);
    text-decoration: none;
    transition: color 0.2s;
}
.page-root .footer-email:hover {
    color: var(--green-light);
}

.page-root .footer-links-col {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}
.page-root .footer-links-col h4 {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--cream);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 0.25rem;
}
.page-root .footer-links-col a {
    font-size: 0.875rem;
    color: rgba(242, 237, 228, 0.45);
    text-decoration: none;
    transition: color 0.2s;
}
.page-root .footer-links-col a:hover {
    color: var(--green-light);
}

.page-root .footer-bottom {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(184, 203, 176, 0.1);
}
@media (min-width: 640px) {
    .page-root .footer-bottom {
        flex-direction: row;
        justify-content: space-between;
    }
}
.page-root .footer-bottom p {
    font-size: 0.8125rem;
    color: rgba(242, 237, 228, 0.28);
}

/* ════════════════════════════════════════════════════════════
   ANIMATIONS
════════════════════════════════════════════════════════════ */
@keyframes badge-in {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.96);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes fade-up {
    from {
        opacity: 0;
        transform: translateY(22px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes float-up-gentle {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-7px);
    }
}

@keyframes float-down-gentle {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(8px);
    }
}

@keyframes cta-breathe {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(74, 124, 89, 0);
    }
    50% {
        box-shadow: 0 0 0 12px rgba(74, 124, 89, 0.07);
    }
}

.page-root .reveal {
    opacity: 0;
    transform: translateY(26px);
}
.page-root .reveal.revealed {
    animation: fade-up 0.65s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}
</style>
