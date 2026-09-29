<script setup lang="ts">
import PlatformShell from '@/layouts/PlatformShell.vue';

export type LegalSection = {
    title: string;
    paragraphs?: string[];
    items?: string[];
    after?: string[];
};

defineProps<{
    pageTitle: string;
    heading: string;
    lead: string;
    updatedAt: string;
    sections: LegalSection[];
}>();
</script>

<template>
    <PlatformShell :title="pageTitle">
        <section class="section-cream">
            <div class="container--narrow container">
                <div class="reveal section-header">
                    <span class="eyebrow-pill">{{ $t('Legal') }}</span>
                    <h1 class="section-title">{{ heading }}</h1>
                    <p class="section-sub">{{ lead }}</p>
                </div>

                <article class="reveal legal-doc">
                    <p class="legal-updated">
                        {{
                            $t('Última actualización: :date', {
                                date: updatedAt,
                            })
                        }}
                    </p>

                    <slot name="intro" />

                    <template v-for="section in sections" :key="section.title">
                        <h2>{{ section.title }}</h2>
                        <p
                            v-for="paragraph in section.paragraphs ?? []"
                            :key="paragraph"
                        >
                            {{ paragraph }}
                        </p>
                        <ul v-if="section.items?.length">
                            <li v-for="item in section.items" :key="item">
                                {{ item }}
                            </li>
                        </ul>
                        <p
                            v-for="paragraph in section.after ?? []"
                            :key="paragraph"
                        >
                            {{ paragraph }}
                        </p>
                    </template>

                    <slot />
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
    margin-bottom: 2rem;
}
.legal-doc :deep(h2) {
    font-family: var(--ff-display);
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-top: 2rem;
    margin-bottom: 0.6rem;
}
.legal-doc :deep(* + p) {
    margin-top: 0.9rem;
}
.legal-doc :deep(ul) {
    margin: 0.75rem 0 0;
    padding-left: 1.1rem;
    list-style: disc;
}
.legal-doc :deep(ul + p) {
    margin-top: 0.9rem;
}
.legal-doc :deep(li + li) {
    margin-top: 0.4rem;
}
.legal-doc :deep(strong) {
    color: var(--text-dark);
    font-weight: 600;
}
.legal-doc :deep(a) {
    color: var(--green-mid);
    font-weight: 600;
    text-decoration: underline;
    text-underline-offset: 3px;
}
.legal-doc :deep(h2 + p) {
    margin-top: 0;
}
.legal-doc :deep(h2:first-of-type) {
    margin-top: 0;
}
.legal-doc :deep(dl) {
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: 0.35rem 1.25rem;
    margin: 0 0 1.5rem;
    padding: 1rem 1.25rem;
    border: 1px solid var(--border);
    border-radius: 10px;
}
.legal-doc :deep(dt) {
    color: var(--text-dark);
    font-weight: 600;
}
.legal-doc :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.75rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}
.legal-doc :deep(th),
.legal-doc :deep(td) {
    border-bottom: 1px solid var(--border);
    padding: 0.55rem 0.5rem;
    text-align: left;
    vertical-align: top;
}
.legal-doc :deep(th) {
    color: var(--text-dark);
}
</style>
