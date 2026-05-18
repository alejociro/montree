<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    index as indexPage,
    edit as editPage,
} from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import { store as storeTour } from '@/actions/App/Http/Controllers/Api/V1/Admin/TourController';
import Heading from '@/components/Heading.vue';
import TourForm from '@/components/organisms/TourForm.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import type { Tour, TourCategory, TourFormPayload } from '@/types/tour';

type Props = {
    categories: TourCategory[];
};

const props = defineProps<Props>();
const { currency: tenantCurrency } = useTenant();

const initialValues: TourFormPayload = {
    name: '',
    short_description: '',
    description: '',
    category_id: null,
    base_price: '0',
    currency: (tenantCurrency.value ?? 'USD') as TourFormPayload['currency'],
    duration_hours: 4,
    difficulty: 'easy',
    default_capacity: 10,
    meeting_point: '',
    meeting_latitude: '',
    meeting_longitude: '',
    includes: [],
    excludes: [],
    requirements: [],
    itinerary: [],
};

const form = useForm<TourFormPayload>(() => ({ ...initialValues }));
const planError = ref<string | null>(null);
const formErrors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function submit(): void {
    planError.value = null;

    form.transform((data) => ({
        ...data,
        meeting_latitude:
            data.meeting_latitude === '' ? null : data.meeting_latitude,
        meeting_longitude:
            data.meeting_longitude === '' ? null : data.meeting_longitude,
        meeting_point: data.meeting_point === '' ? null : data.meeting_point,
        short_description:
            data.short_description === '' ? null : data.short_description,
    }));

    form.submit(storeTour(), {
        preserveScroll: true,
        onSuccess: (response) => {
            const tour = (response as { props?: { tour?: Tour } }).props?.tour;

            toast.success('Tour creado en borrador.');

            if (tour) {
                router.visit(editPage({ tour: tour.id }).url);

                return;
            }

            router.visit(indexPage().url);
        },
        onError: (errors) => {
            const code = (errors as { error_code?: string }).error_code;

            if (code === 'PLAN_LIMIT_TOURS_REACHED') {
                planError.value =
                    'Alcanzaste el límite de tours de tu plan. Actualizá tu plan para crear más.';

                return;
            }

            toast.error('Revisá los campos marcados.');
        },
    });
}
</script>

<template>
    <Head title="Nuevo tour" />

    <div class="px-4 py-6 md:px-8">
        <div class="mb-6 flex items-center gap-3">
            <Link :href="indexPage().url">
                <Button variant="ghost" size="icon">
                    <ArrowLeft class="size-4" />
                </Button>
            </Link>
            <Heading
                title="Nuevo tour"
                description="Empezá creando un borrador. Podrás publicarlo cuando tenga imágenes y al menos una fecha futura."
            />
        </div>

        <Alert v-if="planError" variant="destructive" class="mb-6">
            <AlertTitle>Límite del plan alcanzado</AlertTitle>
            <AlertDescription>{{ planError }}</AlertDescription>
        </Alert>

        <form class="space-y-8" @submit.prevent="submit">
            <TourForm
                v-model="form"
                :errors="formErrors"
                :categories="props.categories"
            />

            <div class="flex items-center gap-3 border-t border-input pt-6">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Creando…' : 'Crear borrador' }}
                </Button>
                <Link :href="indexPage().url">
                    <Button type="button" variant="ghost">Cancelar</Button>
                </Link>
            </div>
        </form>
    </div>
</template>
