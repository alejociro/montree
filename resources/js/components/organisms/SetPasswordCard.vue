<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { KeyRound } from 'lucide-vue-next';
import { computed } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const page = usePage();
const mustSetPassword = computed(
    () => page.props.auth?.user?.mustSetPassword === true,
);
</script>

<template>
    <section
        v-if="mustSetPassword"
        class="space-y-4 rounded-xl border border-primary/30 bg-primary/5 p-6"
    >
        <div class="flex items-start gap-3">
            <span
                class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
            >
                <KeyRound class="size-5" />
            </span>
            <div class="space-y-1">
                <h2 class="text-base font-semibold text-foreground">
                    {{ $t('Define tu contraseña') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Creamos tu cuenta automáticamente al reservar. Establece una contraseña para poder iniciar sesión cuando quieras.',
                        )
                    }}
                </p>
            </div>
        </div>

        <Form
            v-bind="SecurityController.setup.form()"
            reset-on-success
            :options="{ preserveScroll: true }"
            class="space-y-4"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="setup-password">{{ $t('Nueva contraseña') }}</Label>
                <PasswordInput
                    id="setup-password"
                    name="password"
                    autocomplete="new-password"
                    :placeholder="$t('Mínimo 8 caracteres')"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="setup-password-confirmation">
                    {{ $t('Confirmar contraseña') }}
                </Label>
                <PasswordInput
                    id="setup-password-confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    :placeholder="$t('Repite la contraseña')"
                />
            </div>

            <Button type="submit" :disabled="processing">
                {{ processing ? $t('Guardando...') : $t('Guardar contraseña') }}
            </Button>
        </Form>
    </section>
</template>
