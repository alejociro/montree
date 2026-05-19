<script setup lang="ts">
import { Download } from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import RevenueReportController from '@/actions/App/Http/Controllers/Api/V1/Admin/RevenueReportController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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

type ExportFormat = 'csv' | 'json';
type GroupBy = 'day' | 'week' | 'month';

const open = ref(false);
const isExporting = ref(false);

const today = new Date().toISOString().slice(0, 10);
const monthAgo = (() => {
    const date = new Date();
    date.setDate(date.getDate() - 30);

    return date.toISOString().slice(0, 10);
})();

const from = ref(monthAgo);
const to = ref(today);
const groupBy = ref<GroupBy>('day');
const format = ref<ExportFormat>('csv');

function buildExportUrl(): string {
    const action = RevenueReportController({
        query: {
            from: from.value,
            to: to.value,
            group_by: groupBy.value,
            format: format.value,
        },
    });

    return action.url;
}

async function submit(): Promise<void> {
    if (!from.value || !to.value) {
        toast.error('Seleccioná un rango de fechas.');

        return;
    }

    isExporting.value = true;

    try {
        const url = buildExportUrl();

        if (format.value === 'csv') {
            window.location.href = url;
        } else {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Export failed.');
            }

            const blob = await response.blob();
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `revenue-${from.value}-to-${to.value}.json`;
            link.click();
            URL.revokeObjectURL(link.href);
        }

        toast.success('Reporte generado.');
        open.value = false;
    } catch {
        toast.error('No se pudo generar el reporte.');
    } finally {
        isExporting.value = false;
    }
}

function handleGroupByChange(value: AcceptableValue): void {
    if (typeof value === 'string') {
        groupBy.value = value as GroupBy;
    }
}

function handleFormatChange(value: AcceptableValue): void {
    if (typeof value === 'string') {
        format.value = value as ExportFormat;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">
                <Download class="size-4" />
                Exportar
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Exportar reporte de ingresos</DialogTitle>
                <DialogDescription>
                    Seleccioná el rango y formato del reporte.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-2">
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="export-from">Desde</Label>
                        <Input
                            id="export-from"
                            v-model="from"
                            type="date"
                            :max="to"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="export-to">Hasta</Label>
                        <Input
                            id="export-to"
                            v-model="to"
                            type="date"
                            :min="from"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>Agrupar por</Label>
                    <Select
                        :model-value="groupBy"
                        @update:model-value="handleGroupByChange"
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="day">Día</SelectItem>
                                <SelectItem value="week">Semana</SelectItem>
                                <SelectItem value="month">Mes</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <Label>Formato</Label>
                    <Select
                        :model-value="format"
                        @update:model-value="handleFormatChange"
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="csv">CSV</SelectItem>
                                <SelectItem value="json">JSON</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="isExporting"
                    @click="open = false"
                >
                    Cancelar
                </Button>
                <Button type="button" :disabled="isExporting" @click="submit">
                    {{ isExporting ? 'Generando…' : 'Descargar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
