<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApi';

const api = useApi();

type Subscriber = {
    id: number;
    email: string;
    name: string | null;
    status: string;
    subscribed_at: string | null;
};

const subscribers = ref<Subscriber[]>([]);
const totalActive = ref(0);
const loading = ref(true);

const subject = ref('');
const bodyHtml = ref('');
const previewText = ref('');
const sending = ref(false);

async function load() {
    loading.value = true;

    try {
        const res = await fetch('/api/v1/admin/newsletter/subscribers', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        subscribers.value = json.data;
        totalActive.value = json.meta.total_active;
    } finally {
        loading.value = false;
    }
}

function send() {
    sending.value = true;
    void api.post(
        '/api/v1/admin/newsletter/send',
        {
            subject: subject.value,
            body_html: bodyHtml.value,
            preview_text: previewText.value,
        },
        {
            onSuccess: () => {
                toast.success('Campaña encolada');
                subject.value = '';
                bodyHtml.value = '';
                previewText.value = '';
            },
            onError: (e) => toast.error(Object.values(e)[0] ?? 'Error'),
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

onMounted(load);
</script>

<template>
    <Head title="Newsletter" />
    <div class="container mx-auto max-w-4xl space-y-8 px-4 py-8">
        <h1 class="text-2xl font-bold">Newsletter</h1>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">
                Suscriptores activos:
                <span class="text-primary">{{ totalActive }}</span>
            </h2>
            <p v-if="loading" class="text-sm text-muted-foreground">
                Cargando...
            </p>
            <ul
                v-else-if="subscribers.length > 0"
                class="space-y-2 rounded-lg border p-4"
            >
                <li
                    v-for="s in subscribers"
                    :key="s.id"
                    class="flex items-center justify-between text-sm"
                >
                    <span>{{ s.email }}</span>
                    <span class="text-muted-foreground">{{ s.status }}</span>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                Sin suscriptores todavía.
            </p>
        </section>

        <section class="space-y-4 rounded-lg border p-6">
            <h2 class="text-lg font-semibold">Enviar campaña</h2>
            <div class="space-y-2">
                <Label for="subject">Asunto</Label>
                <Input id="subject" v-model="subject" maxlength="200" />
            </div>
            <div class="space-y-2">
                <Label for="preview">Texto preview</Label>
                <Input id="preview" v-model="previewText" maxlength="200" />
            </div>
            <div class="space-y-2">
                <Label for="body">Cuerpo HTML</Label>
                <Textarea id="body" v-model="bodyHtml" rows="10" />
            </div>
            <Button :disabled="sending || !subject || !bodyHtml" @click="send">
                {{
                    sending
                        ? 'Encolando...'
                        : `Enviar a ${totalActive} suscriptores`
                }}
            </Button>
        </section>
    </div>
</template>
