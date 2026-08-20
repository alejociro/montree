<script setup lang="ts">
import { Check, Languages } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';

type Props = {
    variant?: 'dropdown' | 'tabs';
};

withDefaults(defineProps<Props>(), {
    variant: 'dropdown',
});

const { t, locale, locales, setLocale } = useTranslations();
</script>

<template>
    <div
        v-if="variant === 'tabs'"
        class="inline-flex gap-1 rounded-lg bg-muted p-1"
    >
        <button
            v-for="option in locales"
            :key="option.code"
            type="button"
            @click="setLocale(option.code)"
            :aria-current="option.code === locale ? 'true' : undefined"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                option.code === locale
                    ? 'bg-card shadow-xs'
                    : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
            ]"
        >
            <span class="text-sm">{{ option.native }}</span>
        </button>
    </div>

    <DropdownMenu v-else>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                class="gap-1.5"
                :aria-label="t('Cambiar idioma')"
            >
                <Languages class="h-4 w-4" />
                <span class="text-sm uppercase">{{ locale }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="min-w-40">
            <DropdownMenuItem
                v-for="option in locales"
                :key="option.code"
                class="cursor-pointer"
                @select="setLocale(option.code)"
            >
                <Check
                    class="mr-2 h-4 w-4"
                    :class="
                        option.code === locale ? 'opacity-100' : 'opacity-0'
                    "
                />
                {{ option.native }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
