<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ImageOff } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    index as indexPage,
    edit as editPage,
} from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import { store as storeTour } from '@/actions/App/Http/Controllers/Api/V1/Admin/TourController';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import Heading from '@/components/Heading.vue';
import StickySaveBar from '@/components/molecules/StickySaveBar.vue';
import TourForm from '@/components/organisms/TourForm.vue';
import TourProgressRail from '@/components/organisms/TourProgressRail.vue';
import TourPublishChecklist from '@/components/organisms/TourPublishChecklist.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useApi } from '@/composables/useApi';
import { useTenant } from '@/composables/useTenant';
import { useTourCompletion } from '@/composables/useTourCompletion';
import { useTranslations } from '@/composables/useTranslations';
import { tourStopsPayload } from '@/lib/tour-stops';
import type {
    Tour,
    TourCategory,
    TourFormPayload,
    TourFormStep,
    TourFormStepId,
    TourSubmitPayload,
} from '@/types/tour';

const { t } = useTranslations();

type StoreTourResponse = {
    data?: Tour;
};

type Props = {
    categories: TourCategory[];
};

const props = defineProps<Props>();
const { currency: tenantCurrency } = useTenant();
const api = useApi();

const initialValues: TourFormPayload = {
    name: '',
    short_description: '',
    description: '',
    category_id: null,
    base_price: '0',
    currency: (tenantCurrency.value ?? 'USD') as TourFormPayload['currency'],
    duration_hours: 4,
    default_guide_id: null,
    difficulty: 'easy',
    default_capacity: 10,
    meeting_point: '',
    meeting_latitude: '',
    meeting_longitude: '',
    includes: [],
    excludes: [],
    requirements: [],
    itinerary: [],
    stops: [],
};

const form = useForm<TourFormPayload>(() => ({ ...initialValues }));
const planError = ref<string | null>(null);
const saving = ref(false);
const formErrors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const payload = computed<TourFormPayload>(() => form.data());

/**
 * WHY: el tour todavía no existe, así que no tiene imágenes ni puede tenerlas
 * —`TourImageUploader` sube contra `/tours/{tour}/images`—. La galería se
 * anuncia como paso posterior en vez de fingir un cargador que fallaría.
 */
const { steps, requirements, blockingCount } = useTourCompletion(payload, {
    imagesCount: 0,
    pendingCreation: true,
});

const activeStep = ref<TourFormStepId | null>('general');
let spy: IntersectionObserver | null = null;

function goToStep(step: TourFormStep): void {
    activeStep.value = step.id;
    document
        .getElementById(step.anchor)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

onMounted(() => {
    if (typeof IntersectionObserver === 'undefined') {
        return;
    }

    spy = new IntersectionObserver(
        (entries) => {
            const visible = entries.find((entry) => entry.isIntersecting);

            if (!visible) {
                return;
            }

            const match = steps.value.find(
                (step) => step.anchor === visible.target.id,
            );

            if (match) {
                activeStep.value = match.id;
            }
        },
        { rootMargin: '-80px 0px -55% 0px', threshold: 0 },
    );

    for (const step of steps.value) {
        const element = document.getElementById(step.anchor);

        if (element) {
            spy.observe(element);
        }
    }
});

onBeforeUnmount(() => {
    spy?.disconnect();
    spy = null;
});

function normalizePayload(data: TourFormPayload): TourSubmitPayload {
    return {
        ...data,
        meeting_latitude:
            data.meeting_latitude === '' ? null : data.meeting_latitude,
        meeting_longitude:
            data.meeting_longitude === '' ? null : data.meeting_longitude,
        meeting_point: data.meeting_point === '' ? null : data.meeting_point,
        short_description:
            data.short_description === '' ? null : data.short_description,
        stops: tourStopsPayload(data.stops),
    };
}

function submit(): void {
    planError.value = null;
    form.clearErrors();
    saving.value = true;

    void api.post<StoreTourResponse>(
        storeTour().url,
        normalizePayload(form.data()),
        {
            onSuccess: (response) => {
                const tour = response?.data;

                toast.success(t('Tour creado en borrador.'));

                if (tour) {
                    router.visit(editPage({ tour: tour.id }).url);

                    return;
                }

                router.visit(indexPage().url);
            },
            onError: (errors) => {
                if (errors.error_code === 'PLAN_LIMIT_TOURS_REACHED') {
                    planError.value = t(
                        'Alcanzaste el límite de tours de tu plan. Actualiza tu plan para crear más.',
                    );

                    return;
                }

                form.setError(errors);
                toast.error(t('Revisa los campos marcados.'));
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="px-4 py-6 md:px-8">
        <Head :title="$t('Nuevo tour')" />

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <Link
                    :href="indexPage().url"
                    class="inline-flex items-center gap-1.5 text-[13px] text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-3.5" />
                    {{ $t('Volver a tours') }}
                </Link>
                <Heading
                    class="mt-1.5"
                    :title="$t('Nuevo tour')"
                    :description="
                        $t(
                            'Crea el borrador. Se publica cuando tenga imagen, precio, cupo y guía por defecto.',
                        )
                    "
                />
            </div>
            <div class="flex items-center gap-2">
                <Link :href="indexPage().url">
                    <Button type="button" variant="outline">{{
                        $t('Cancelar')
                    }}</Button>
                </Link>
                <Button type="button" :disabled="saving" @click="submit">
                    {{ saving ? $t('Creando…') : $t('Guardar borrador') }}
                </Button>
            </div>
        </div>

        <Alert v-if="planError" variant="destructive" class="mt-5">
            <AlertTitle>{{ $t('Límite del plan alcanzado') }}</AlertTitle>
            <AlertDescription>{{ planError }}</AlertDescription>
        </Alert>

        <div
            class="mt-5 grid grid-cols-1 items-start gap-6 min-[1180px]:grid-cols-[minmax(0,1fr)_320px]"
        >
            <form @submit.prevent="submit">
                <TourForm
                    :model-value="payload"
                    :errors="formErrors"
                    :categories="props.categories"
                    @update:model-value="(value) => Object.assign(form, value)"
                >
                    <template #gallery>
                        <Card>
                            <CardHeader>
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div>
                                        <CardTitle>{{
                                            $t('Galería')
                                        }}</CardTitle>
                                        <CardDescription>{{
                                            $t(
                                                'JPG, PNG o WebP. La portada es la primera imagen.',
                                            )
                                        }}</CardDescription>
                                    </div>
                                    <MonoLabel class="shrink-0 pt-1">{{
                                        $t('Paso :number', { number: 5 })
                                    }}</MonoLabel>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div
                                    class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-input px-6 py-8 text-center"
                                >
                                    <ImageOff
                                        class="size-5 text-muted-foreground"
                                    />
                                    <p class="text-sm font-medium">
                                        {{
                                            $t(
                                                'Las imágenes se cargan al guardar el borrador',
                                            )
                                        }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            $t(
                                                'Al guardar te llevamos a la edición del tour, donde se sube la galería.',
                                            )
                                        }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </template>
                </TourForm>

                <StickySaveBar>
                    <template #note>
                        <span v-if="blockingCount > 0">
                            {{
                                $tc(
                                    'Falta :count condición para publicar|Faltan :count condiciones para publicar',
                                    blockingCount,
                                    { count: blockingCount },
                                )
                            }}
                        </span>
                        <span v-else>
                            {{
                                $t(
                                    'Todo listo para publicar desde la edición del tour.',
                                )
                            }}
                        </span>
                    </template>
                    <template #actions>
                        <Link :href="indexPage().url">
                            <Button type="button" variant="ghost">{{
                                $t('Cancelar')
                            }}</Button>
                        </Link>
                        <Button type="submit" :disabled="saving">
                            {{
                                saving ? $t('Creando…') : $t('Guardar borrador')
                            }}
                        </Button>
                    </template>
                </StickySaveBar>
            </form>

            <aside
                class="flex flex-col gap-4 min-[1180px]:sticky min-[1180px]:top-20"
            >
                <TourProgressRail
                    :steps="steps"
                    :active-id="activeStep"
                    @select="goToStep"
                />
                <TourPublishChecklist :requirements="requirements" />
            </aside>
        </div>
    </div>
</template>
