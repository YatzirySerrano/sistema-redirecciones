<script setup lang="ts">
import { computed } from 'vue';
import { hoyIso } from '@/lib/formato';
import { cn } from '@/lib/utils';

/**
 * Rango de fechas con atajos (7, 30 y 90 días). Emite fechas YYYY-MM-DD.
 */
const desde = defineModel<string>('desde', { required: true });
const hasta = defineModel<string>('hasta', { required: true });

const restar = (dias: number) => {
    const d = new Date();
    d.setDate(d.getDate() - dias);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const ATAJOS = [
    { dias: 7, etiqueta: '7 días' },
    { dias: 30, etiqueta: '30 días' },
    { dias: 90, etiqueta: '90 días' },
    { dias: 365, etiqueta: '12 meses' },
];

const atajoActivo = computed(() =>
    hasta.value === hoyIso()
        ? ATAJOS.find((a) => desde.value === restar(a.dias - 1))?.dias
        : undefined,
);

const aplicar = (dias: number) => {
    desde.value = restar(dias - 1);
    hasta.value = hoyIso();
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div
            class="inline-flex rounded-lg border bg-background p-0.5"
            role="group"
            aria-label="Periodos rápidos"
        >
            <button
                v-for="a in ATAJOS"
                :key="a.dias"
                type="button"
                :aria-pressed="atajoActivo === a.dias"
                :class="
                    cn(
                        'rounded-md px-2.5 py-1 text-xs font-medium text-muted-foreground transition hover:text-foreground',
                        atajoActivo === a.dias &&
                            'bg-primary text-primary-foreground hover:text-primary-foreground',
                    )
                "
                @click="aplicar(a.dias)"
            >
                {{ a.etiqueta }}
            </button>
        </div>
        <div class="flex items-center gap-1.5 text-sm">
            <label class="sr-only" for="periodo-desde">Desde</label>
            <input
                id="periodo-desde"
                v-model="desde"
                type="date"
                :max="hasta"
                class="h-8 rounded-lg border border-input bg-background px-2 text-sm"
            />
            <span class="text-muted-foreground" aria-hidden="true">–</span>
            <label class="sr-only" for="periodo-hasta">Hasta</label>
            <input
                id="periodo-hasta"
                v-model="hasta"
                type="date"
                :min="desde"
                :max="hoyIso()"
                class="h-8 rounded-lg border border-input bg-background px-2 text-sm"
            />
        </div>
    </div>
</template>
