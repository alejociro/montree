<script setup lang="ts">
/**
 * Barra de guardado pegada al pie del formulario.
 *
 * WHY: el formulario del tour mide varias pantallas. Con el botón al final,
 * guardar exige volver a bajar hasta el fondo; pegada, la acción está siempre
 * a la vista. El texto de la izquierda va por slot para que «editar» pueda
 * poner su contador de cambios sin duplicar la barra.
 */
type Props = {
    /** Texto de la izquierda cuando no hace falta un slot. */
    note?: string;
};

withDefaults(defineProps<Props>(), { note: undefined });
</script>

<template>
    <div
        class="sticky bottom-0 z-10 -mx-4 mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border bg-background/90 px-4 py-3 backdrop-blur md:-mx-8 md:px-8"
    >
        <p class="text-xs text-muted-foreground">
            <slot name="note">{{ note }}</slot>
        </p>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
