<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { TenantStatus } from '@/types';

defineProps<{
    currentStatus: TenantStatus;
    processing?: boolean;
}>();

const emit = defineEmits<{
    submit: [next: TenantStatus, reason: string | null];
}>();

const open = ref(false);
const targetStatus = ref<TenantStatus | null>(null);
const reason = ref('');
const reasonError = ref<string | null>(null);

const dialogTitle = computed(() => {
    if (targetStatus.value === 'suspended') {
        return 'Suspender tenant';
    }

    if (targetStatus.value === 'active') {
        return 'Restablecer tenant';
    }

    return 'Cambiar estado';
});

function openDialog(next: TenantStatus): void {
    targetStatus.value = next;
    reason.value = '';
    reasonError.value = null;
    open.value = true;
}

function submit(): void {
    if (targetStatus.value === null) {
        return;
    }

    if (targetStatus.value === 'suspended' && reason.value.trim() === '') {
        reasonError.value = 'El motivo es obligatorio al suspender.';

        return;
    }

    emit('submit', targetStatus.value, reason.value.trim() === '' ? null : reason.value.trim());
}

function closeDialog(): void {
    open.value = false;
}

defineExpose({ closeDialog });
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button
            v-if="currentStatus !== 'active'"
            variant="default"
            size="sm"
            :disabled="processing"
            @click="openDialog('active')"
        >
            Activar
        </Button>
        <Button
            v-if="currentStatus !== 'suspended'"
            variant="destructive"
            size="sm"
            :disabled="processing"
            @click="openDialog('suspended')"
        >
            Suspender
        </Button>

        <Dialog v-model:open="open">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>
                        Esta acción afecta el acceso del tenant a la plataforma y notifica a sus
                        administradores.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="targetStatus === 'suspended'" class="space-y-2">
                    <Label for="suspension-reason">Motivo</Label>
                    <Textarea
                        id="suspension-reason"
                        v-model="reason"
                        rows="3"
                        placeholder="Describí brevemente el motivo de la suspensión"
                    />
                    <p v-if="reasonError" class="text-sm text-red-600">{{ reasonError }}</p>
                </div>

                <DialogFooter>
                    <Button variant="ghost" :disabled="processing" @click="open = false">
                        Cancelar
                    </Button>
                    <Button :disabled="processing" @click="submit">
                        {{ processing ? 'Procesando...' : 'Confirmar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
