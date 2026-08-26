<script setup lang="ts">
import { Check, Loader2, Plus, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import AddressField from '@/components/molecules/AddressField.vue';
import ChipsInput from '@/components/molecules/ChipsInput.vue';
import OptionChips from '@/components/molecules/OptionChips.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { ApiErrors } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import type { SelectOption } from '@/lib/logistics';
import {
    blankRow,
    formStateFor,
    isFilled,
    sectionsFor,
    toPayload,
} from '@/lib/logistics-form';
import type { LogisticsFieldDef } from '@/lib/logistics-form';
import { cn } from '@/lib/utils';
import type { GeocodedPlace } from '@/types/geocoding';
import type {
    LogisticsFormState,
    LogisticsRecord,
    LogisticsResourceKind,
} from '@/types/logistics';

const { t } = useTranslations();

/**
 * Ficha de logística completa: ruta, proveedor u hotel.
 *
 * Cinco secciones con navegación lateral, un pie que dice cuántos campos
 * obligatorios faltan y dos formas de salir —guardar la ficha entera o dejarla
 * a medias como borrador—. El formulario no sabe qué campos tiene: los lee del
 * esquema de `lib/logistics-form.ts`, así que las tres fichas comparten
 * validación visual, contador y maquetación.
 */
type Props = {
    open: boolean;
    kind: LogisticsResourceKind;
    /** `null` crea una ficha nueva. */
    record: LogisticsRecord | null;
    processing: boolean;
    errors: ApiErrors;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submit', payload: Record<string, unknown>): void;
}>();

/** Valor con el que reka-ui representa «sin elegir»: no acepta cadena vacía. */
const NO_SELECTION = '__none__';

const KIND_COPY: Record<
    LogisticsResourceKind,
    { one: string; new: string; newLead: string; editLead: string }
> = {
    routes: {
        one: 'Ruta',
        new: 'Nueva ruta',
        newLead:
            'Se guarda una vez y se reutiliza en cada salida que la necesite.',
        editLead: 'Los cambios se aplican a las salidas futuras que la usan.',
    },
    providers: {
        one: 'Proveedor',
        new: 'Nuevo proveedor',
        newLead:
            'Se guarda una vez y se reutiliza en cada salida que lo necesite.',
        editLead: 'Los cambios se aplican a las salidas futuras que lo usan.',
    },
    hotels: {
        one: 'Hotel',
        new: 'Nuevo hotel',
        newLead:
            'Se guarda una vez y se reutiliza en cada salida que lo necesite.',
        editLead: 'Los cambios se aplican a las salidas futuras que lo usan.',
    },
};

const copy = computed(() => KIND_COPY[props.kind]);
const sections = computed(() => sectionsFor(props.kind));

const form = ref<LogisticsFormState>({});
const activeSection = ref<string>('');
const formEl = ref<HTMLFormElement | null>(null);
const sectionEls = ref<Record<string, HTMLElement | null>>({});

watch(
    () => [props.open, props.record, props.kind] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        form.value = formStateFor(props.kind, props.record);
        activeSection.value = sections.value[0]?.id ?? '';
        void nextTick(() => formEl.value?.scrollTo({ top: 0 }));
    },
    { immediate: true },
);

function textOf(key: string): string {
    const value = form.value[key];

    return typeof value === 'string' ? value : '';
}

function listOf(key: string): string[] {
    const value = form.value[key];

    return Array.isArray(value) ? (value as string[]) : [];
}

function rowsOf(key: string): Record<string, string>[] {
    const value = form.value[key];

    return Array.isArray(value) ? (value as Record<string, string>[]) : [];
}

function set(key: string, value: LogisticsFormState[string]): void {
    form.value = { ...form.value, [key]: value };
}

function setRowValue(
    field: LogisticsFieldDef,
    index: number,
    column: string,
    value: string,
): void {
    const rows = rowsOf(field.key).map((row, position) =>
        position === index ? { ...row, [column]: value } : row,
    );

    set(field.key, rows);
}

function addRow(field: LogisticsFieldDef): void {
    set(field.key, [...rowsOf(field.key), blankRow(field)]);
}

function removeRow(field: LogisticsFieldDef, index: number): void {
    set(
        field.key,
        rowsOf(field.key).filter((_, position) => position !== index),
    );
}

/**
 * La dirección elegida rellena el punto y, si están vacíos, el municipio y el
 * departamento: nadie debería teclear «Salento» dos veces.
 */
function applyPlace(field: LogisticsFieldDef, place: GeocodedPlace): void {
    const next: LogisticsFormState = { ...form.value };
    const fills = field.fills ?? {};

    if (fills.latitude) {
        next[fills.latitude] = String(place.latitude);
    }

    if (fills.longitude) {
        next[fills.longitude] = String(place.longitude);
    }

    // Municipio y departamento salen de la ficha del proveedor. Partir la
    // etiqueta por comas ponía «Fría» —un barrio— como municipio de Salento.
    if (fills.city && place.city !== null && !isFilled(next[fills.city])) {
        next[fills.city] = place.city;
    }

    if (fills.state && place.state !== null && !isFilled(next[fills.state])) {
        next[fills.state] = place.state;
    }

    form.value = next;
}

function isLocated(field: LogisticsFieldDef): boolean {
    const latitude = field.fills?.latitude;

    return latitude !== undefined && isFilled(form.value[latitude]);
}

const requiredFields = computed<LogisticsFieldDef[]>(() =>
    sections.value.flatMap((section) =>
        section.fields.filter((field) => field.required === true),
    ),
);

const filledRequired = computed<number>(
    () =>
        requiredFields.value.filter((field) => isFilled(form.value[field.key]))
            .length,
);

const complete = computed<boolean>(
    () => filledRequired.value === requiredFields.value.length,
);

function sectionDone(sectionId: string): boolean {
    const section = sections.value.find((item) => item.id === sectionId);
    const required = (section?.fields ?? []).filter(
        (field) => field.required === true,
    );

    return (
        required.length > 0 &&
        required.every((field) => isFilled(form.value[field.key]))
    );
}

function goToSection(sectionId: string): void {
    activeSection.value = sectionId;
    sectionEls.value[sectionId]?.scrollIntoView({
        block: 'start',
        behavior: 'smooth',
    });
}

function onScroll(): void {
    const container = formEl.value;

    if (!container) {
        return;
    }

    let current = sections.value[0]?.id ?? '';

    for (const section of sections.value) {
        const element = sectionEls.value[section.id];

        if (element && element.offsetTop <= container.scrollTop + 48) {
            current = section.id;
        }
    }

    activeSection.value = current;
}

function registerSection(id: string, element: unknown): void {
    sectionEls.value[id] = (element as HTMLElement | null) ?? null;
}

function spanClass(field: LogisticsFieldDef): string {
    if (field.width === 'half') {
        return 'sm:col-span-3';
    }

    if (field.width === 'third') {
        return 'sm:col-span-2';
    }

    return 'sm:col-span-6';
}

/** Un select opcional necesita una opción para volver a quedar vacío. */
function optionsOf(field: LogisticsFieldDef): SelectOption[] {
    const options = field.options ?? [];

    return field.required === true
        ? options
        : [{ value: NO_SELECTION, label: t('Sin especificar') }, ...options];
}

function selectValue(key: string): string {
    const value = textOf(key);

    return value === '' ? NO_SELECTION : value;
}

function onSelect(key: string, value: unknown): void {
    const text = value === null || value === undefined ? '' : String(value);

    set(key, text === NO_SELECTION ? '' : text);
}

function errorFor(key: string): string | undefined {
    return props.errors[key];
}

function rowErrorFor(
    field: LogisticsFieldDef,
    index: number,
    column: string,
): string | undefined {
    return props.errors[`${field.key}.${index}.${column}`];
}

function submit(): void {
    emit('submit', toPayload(props.kind, form.value));
}
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-h-[92vh] gap-0 overflow-hidden p-0 sm:max-w-[880px]"
        >
            <DialogHeader class="px-6 pt-6 pb-4 text-left">
                <MonoLabel class="text-muted-foreground">
                    {{
                        $t('Ficha de logística · :kind', {
                            kind: $t(copy.one),
                        })
                    }}
                </MonoLabel>
                <DialogTitle class="text-xl">
                    {{
                        props.record === null
                            ? $t(copy.new)
                            : $t('Editar :name', { name: props.record.name })
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        props.record === null
                            ? $t(copy.newLead)
                            : $t(copy.editLead)
                    }}
                </DialogDescription>
            </DialogHeader>

            <div
                class="grid border-t border-brand-line-2 md:grid-cols-[212px_minmax(0,1fr)]"
            >
                <nav
                    class="hidden content-start gap-1 border-r border-brand-line-2 bg-muted/40 p-3 md:grid"
                    :aria-label="$t('Secciones de la ficha')"
                >
                    <button
                        v-for="(section, index) in sections"
                        :key="section.id"
                        type="button"
                        :aria-current="
                            activeSection === section.id ? 'true' : undefined
                        "
                        :class="
                            cn(
                                'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] transition',
                                activeSection === section.id
                                    ? 'bg-card font-medium shadow-xs'
                                    : 'text-muted-foreground hover:bg-card/70',
                            )
                        "
                        @click="goToSection(section.id)"
                    >
                        <span
                            :class="
                                cn(
                                    'grid size-5 shrink-0 place-items-center rounded-full text-[11px] font-semibold',
                                    sectionDone(section.id)
                                        ? 'bg-primary text-primary-foreground'
                                        : 'border border-input bg-card text-muted-foreground',
                                )
                            "
                        >
                            <Check
                                v-if="sectionDone(section.id)"
                                class="size-3"
                                aria-hidden="true"
                            />
                            <template v-else>{{ index + 1 }}</template>
                        </span>
                        {{ section.nav }}
                    </button>

                    <p class="mt-2 px-2.5 text-xs text-muted-foreground">
                        {{
                            $t(
                                'Los campos con * son obligatorios. Puedes guardar un borrador y terminarla después.',
                            )
                        }}
                    </p>
                </nav>

                <form
                    ref="formEl"
                    class="max-h-[58vh] space-y-9 overflow-y-auto px-6 py-6"
                    @scroll="onScroll"
                    @submit.prevent="submit"
                >
                    <p
                        v-if="props.errors._global"
                        class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {{ props.errors._global }}
                    </p>

                    <section
                        v-for="section in sections"
                        :key="section.id"
                        :ref="(element) => registerSection(section.id, element)"
                    >
                        <h3 class="text-[15px] font-semibold">
                            {{ section.title }}
                        </h3>
                        <p class="mt-1 text-[13px] text-muted-foreground">
                            {{ section.lead }}
                        </p>

                        <div class="mt-4 grid gap-4 sm:grid-cols-6">
                            <div
                                v-for="field in section.fields"
                                :key="field.key"
                                class="grid content-start gap-2"
                                :class="spanClass(field)"
                            >
                                <!-- Las listas repetibles traen su propia
                                     etiqueta y su botón de agregar. -->
                                <template v-if="field.type === 'repeat'">
                                    <Label>{{ field.label }}</Label>
                                    <div class="grid gap-2.5">
                                        <div
                                            v-for="(row, index) in rowsOf(
                                                field.key,
                                            )"
                                            :key="index"
                                            class="grid items-start gap-2"
                                            :style="{
                                                gridTemplateColumns: `${(field.columns ?? []).map((column) => column.width).join(' ')} 34px`,
                                            }"
                                        >
                                            <div
                                                v-for="column in field.columns ??
                                                []"
                                                :key="column.key"
                                                class="min-w-0"
                                            >
                                                <Select
                                                    v-if="
                                                        column.type === 'select'
                                                    "
                                                    :model-value="
                                                        row[column.key] ||
                                                        undefined
                                                    "
                                                    @update:model-value="
                                                        setRowValue(
                                                            field,
                                                            index,
                                                            column.key,
                                                            String($event),
                                                        )
                                                    "
                                                >
                                                    <SelectTrigger
                                                        class="w-full"
                                                        :aria-label="
                                                            column.label
                                                        "
                                                    >
                                                        <SelectValue
                                                            :placeholder="
                                                                column.label
                                                            "
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            <SelectItem
                                                                v-for="option in column.options ??
                                                                []"
                                                                :key="
                                                                    option.value
                                                                "
                                                                :value="
                                                                    option.value
                                                                "
                                                            >
                                                                {{
                                                                    option.label
                                                                }}
                                                            </SelectItem>
                                                        </SelectGroup>
                                                    </SelectContent>
                                                </Select>
                                                <Input
                                                    v-else
                                                    :model-value="
                                                        row[column.key] ?? ''
                                                    "
                                                    :type="
                                                        column.type === 'number'
                                                            ? 'number'
                                                            : column.type ===
                                                                'date'
                                                              ? 'date'
                                                              : 'text'
                                                    "
                                                    :placeholder="
                                                        column.placeholder ??
                                                        column.label
                                                    "
                                                    :aria-label="column.label"
                                                    @update:model-value="
                                                        setRowValue(
                                                            field,
                                                            index,
                                                            column.key,
                                                            String($event),
                                                        )
                                                    "
                                                />
                                                <p
                                                    v-if="
                                                        rowErrorFor(
                                                            field,
                                                            index,
                                                            column.key,
                                                        )
                                                    "
                                                    class="mt-1 text-xs text-destructive"
                                                >
                                                    {{
                                                        rowErrorFor(
                                                            field,
                                                            index,
                                                            column.key,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                class="grid size-9 place-items-center rounded-md border border-input text-muted-foreground transition hover:border-destructive/50 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                :aria-label="
                                                    $t('Quitar fila :number', {
                                                        number: index + 1,
                                                    })
                                                "
                                                @click="removeRow(field, index)"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>

                                        <p
                                            v-if="
                                                rowsOf(field.key).length === 0
                                            "
                                            class="rounded-lg border border-dashed border-input px-3 py-3 text-[13px] text-muted-foreground"
                                        >
                                            {{ $t('Todavía no hay filas.') }}
                                        </p>
                                    </div>
                                    <div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="addRow(field)"
                                        >
                                            <Plus class="size-4" />
                                            {{ field.addLabel }}
                                        </Button>
                                    </div>
                                </template>

                                <template v-else>
                                    <Label
                                        v-if="field.type !== 'tags'"
                                        :for="`ficha-${field.key}`"
                                    >
                                        {{ field.label
                                        }}<span
                                            v-if="field.required"
                                            class="text-destructive"
                                        >
                                            *</span
                                        >
                                    </Label>

                                    <AddressField
                                        v-if="field.type === 'address'"
                                        :id="`ficha-${field.key}`"
                                        :model-value="textOf(field.key)"
                                        :placeholder="field.placeholder"
                                        :located="isLocated(field)"
                                        @update:model-value="
                                            set(field.key, $event)
                                        "
                                        @select="applyPlace(field, $event)"
                                    />

                                    <OptionChips
                                        v-else-if="field.type === 'options'"
                                        :model-value="
                                            field.multiple
                                                ? listOf(field.key)
                                                : textOf(field.key)
                                        "
                                        :options="field.options ?? []"
                                        :multiple="field.multiple"
                                        :label="field.label"
                                        @update:model-value="
                                            set(field.key, $event)
                                        "
                                    />

                                    <ChipsInput
                                        v-else-if="field.type === 'tags'"
                                        :id="`ficha-${field.key}`"
                                        :label="field.label"
                                        :model-value="listOf(field.key)"
                                        :placeholder="field.placeholder"
                                        :max="20"
                                        :max-length="80"
                                        :error="errorFor(field.key)"
                                        @update:model-value="
                                            set(field.key, $event)
                                        "
                                    />

                                    <Select
                                        v-else-if="field.type === 'select'"
                                        :model-value="selectValue(field.key)"
                                        @update:model-value="
                                            onSelect(field.key, $event)
                                        "
                                    >
                                        <SelectTrigger
                                            :id="`ficha-${field.key}`"
                                            class="w-full"
                                        >
                                            <SelectValue
                                                :placeholder="field.label"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem
                                                    v-for="option in optionsOf(
                                                        field,
                                                    )"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>

                                    <Textarea
                                        v-else-if="field.type === 'textarea'"
                                        :id="`ficha-${field.key}`"
                                        :model-value="textOf(field.key)"
                                        :rows="field.rows ?? 3"
                                        :placeholder="field.placeholder"
                                        @update:model-value="
                                            set(field.key, String($event))
                                        "
                                    />

                                    <div v-else class="relative">
                                        <Input
                                            :id="`ficha-${field.key}`"
                                            :model-value="textOf(field.key)"
                                            :type="
                                                field.type === 'number'
                                                    ? 'number'
                                                    : field.type === 'email'
                                                      ? 'email'
                                                      : field.type === 'date'
                                                        ? 'date'
                                                        : 'text'
                                            "
                                            :placeholder="field.placeholder"
                                            :class="field.unit ? 'pr-16' : ''"
                                            @update:model-value="
                                                set(field.key, String($event))
                                            "
                                        />
                                        <span
                                            v-if="field.unit"
                                            class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-muted-foreground"
                                        >
                                            {{ field.unit }}
                                        </span>
                                    </div>

                                    <p
                                        v-if="
                                            field.hint && !errorFor(field.key)
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ field.hint }}
                                    </p>
                                    <p
                                        v-if="
                                            errorFor(field.key) &&
                                            field.type !== 'tags'
                                        "
                                        class="text-xs text-destructive"
                                    >
                                        {{ errorFor(field.key) }}
                                    </p>
                                </template>
                            </div>
                        </div>
                    </section>
                </form>
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t border-brand-line-2 px-6 py-4"
            >
                <div class="flex items-center gap-2.5 text-[13px]">
                    <span
                        :class="
                            cn(
                                'rounded-full px-2.5 py-1 text-xs font-medium',
                                complete
                                    ? 'bg-primary-soft text-primary-readable'
                                    : 'bg-brand-warn-50 text-brand-warn',
                            )
                        "
                    >
                        {{
                            $t(':done de :total obligatorios', {
                                done: filledRequired,
                                total: requiredFields.length,
                            })
                        }}
                    </span>
                    <span class="hidden text-muted-foreground sm:inline">
                        {{ $t('El resto puede completarse luego.') }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="props.processing"
                        @click="emit('update:open', false)"
                    >
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="props.processing"
                        @click="submit"
                    >
                        {{ $t('Guardar borrador') }}
                    </Button>
                    <Button
                        type="button"
                        :disabled="props.processing || !complete"
                        @click="submit"
                    >
                        <Loader2
                            v-if="props.processing"
                            class="size-4 animate-spin"
                        />
                        <Check v-else class="size-4" />
                        {{ $t('Guardar ficha') }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
