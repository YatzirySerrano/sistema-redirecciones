<script setup lang="ts">
import { CalendarRange } from '@lucide/vue';
import { computed, ref } from 'vue';
import { hoyIso } from '@/lib/formato';
import { cn } from '@/lib/utils';

/**
 * Periodo de consulta con atajos (hoy, últimos días, este mes, mes
 * anterior) y fechas personalizadas. Emite fechas YYYY-MM-DD en la zona
 * local; el servidor vuelve a validar el rango (máximo un año).
 */
const desde = defineModel<string>('desde', { required: true });
const hasta = defineModel<string>('hasta', { required: true });

const iso = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const restar = (dias: number) => {
    const d = new Date();
    d.setDate(d.getDate() - dias);

    return iso(d);
};

type Atajo = { clave: string; etiqueta: string; rango: () => [string, string] };

const ATAJOS: Atajo[] = [
    { clave: 'hoy', etiqueta: 'Hoy', rango: () => [hoyIso(), hoyIso()] },
    { clave: '7', etiqueta: '7 días', rango: () => [restar(6), hoyIso()] },
    { clave: '30', etiqueta: '30 días', rango: () => [restar(29), hoyIso()] },
    {
        clave: 'mes',
        etiqueta: 'Este mes',
        rango: () => {
            const d = new Date();

            return [iso(new Date(d.getFullYear(), d.getMonth(), 1)), hoyIso()];
        },
    },
    {
        clave: 'mes_anterior',
        etiqueta: 'Mes anterior',
        rango: () => {
            const d = new Date();

            return [
                iso(new Date(d.getFullYear(), d.getMonth() - 1, 1)),
                iso(new Date(d.getFullYear(), d.getMonth(), 0)),
            ];
        },
    },
    { clave: '90', etiqueta: '90 días', rango: () => [restar(89), hoyIso()] },
    {
        clave: '365',
        etiqueta: '12 meses',
        rango: () => [restar(364), hoyIso()],
    },
];

const personalizado = ref(false);

const atajoActivo = computed(() => {
    if (personalizado.value) {
        return null;
    }

    // «Este mes» el día 1 coincide con «Hoy»: gana el más específico.
    return (
        ATAJOS.find((a) => {
            const [d, h] = a.rango();

            return d === desde.value && h === hasta.value;
        })?.clave ?? null
    );
});

const mostrarFechas = computed(
    () => personalizado.value || atajoActivo.value === null,
);

const aplicar = (a: Atajo) => {
    personalizado.value = false;
    [desde.value, hasta.value] = a.rango();
};
</script>

<template>
    <div
        class="flex min-w-0 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
    >
        <div
            class="-mx-1 flex snap-x [scrollbar-width:none] gap-1 overflow-x-auto px-1 pb-0.5 sm:mx-0 sm:px-0"
            role="group"
            aria-label="Periodos rápidos"
        >
            <button
                v-for="a in ATAJOS"
                :key="a.clave"
                type="button"
                :aria-pressed="atajoActivo === a.clave"
                :class="
                    cn(
                        'h-8 shrink-0 snap-start rounded-lg border px-2.5 text-xs font-medium whitespace-nowrap text-muted-foreground transition hover:border-primary/40 hover:text-foreground',
                        atajoActivo === a.clave &&
                            'border-primary bg-primary text-primary-foreground hover:text-primary-foreground',
                    )
                "
                @click="aplicar(a)"
            >
                {{ a.etiqueta }}
            </button>
            <button
                type="button"
                :aria-pressed="mostrarFechas"
                :class="
                    cn(
                        'inline-flex h-8 shrink-0 snap-start items-center gap-1 rounded-lg border px-2.5 text-xs font-medium whitespace-nowrap text-muted-foreground transition hover:border-primary/40 hover:text-foreground',
                        mostrarFechas &&
                            'border-primary/50 bg-primary/10 text-primary',
                    )
                "
                @click="personalizado = !personalizado"
            >
                <CalendarRange class="size-3.5" aria-hidden="true" />
                Personalizado
            </button>
        </div>
        <div v-if="mostrarFechas" class="flex items-center gap-1.5 text-sm">
            <label class="sr-only" for="periodo-desde">Desde</label>
            <input
                id="periodo-desde"
                v-model="desde"
                type="date"
                :max="hasta"
                class="h-8 min-w-0 flex-1 rounded-lg border border-input bg-background px-2 text-sm sm:flex-none"
            />
            <span class="text-muted-foreground" aria-hidden="true">–</span>
            <label class="sr-only" for="periodo-hasta">Hasta</label>
            <input
                id="periodo-hasta"
                v-model="hasta"
                type="date"
                :min="desde"
                :max="hoyIso()"
                class="h-8 min-w-0 flex-1 rounded-lg border border-input bg-background px-2 text-sm sm:flex-none"
            />
        </div>
    </div>
</template>
