<script setup lang="ts">
/**
 * Pantalla de pago verificado. La persona decide cuándo continuar
 * (antes se cerraba sola en 1,5 s y no alcanzaban a leer).
 */
import { CheckCircle2 } from 'lucide-vue-next';
import { formatBs } from '@/lib/money';
import { displayDate } from '@/lib/dates';

defineProps<{
    title: string;
    message: string;
    amountBs?: string;
    reference?: string;
    date?: string;
    tone?: 'success' | 'warning';
}>();
const emit = defineEmits<{ (e: 'done'): void }>();
</script>

<template>
    <div class="flex flex-col items-center px-2 pt-2 pb-1 text-center">
        <div :class="['flex h-20 w-20 items-center justify-center rounded-full', tone === 'warning' ? 'bg-amber-100 text-amber-600 dark:bg-amber-950/50' : 'bg-brand/15 text-green-600 dark:text-brand']">
            <CheckCircle2 class="h-11 w-11" />
        </div>
        <h3 class="mt-4 text-2xl font-bold">{{ title }}</h3>
        <p class="mt-1 text-[15px] text-muted-foreground">{{ message }}</p>

        <dl v-if="amountBs || reference || date" class="mt-5 w-full divide-y rounded-2xl border text-left">
            <div v-if="amountBs" class="flex justify-between px-4 py-3">
                <dt class="text-[15px] text-muted-foreground">Monto</dt>
                <dd class="text-[15px] font-bold tabular-nums">{{ formatBs(amountBs) }} Bs</dd>
            </div>
            <div v-if="reference" class="flex justify-between px-4 py-3">
                <dt class="text-[15px] text-muted-foreground">Referencia</dt>
                <dd class="font-mono text-[15px] font-semibold">{{ reference }}</dd>
            </div>
            <div v-if="date" class="flex justify-between px-4 py-3">
                <dt class="text-[15px] text-muted-foreground">Fecha</dt>
                <dd class="text-[15px] font-semibold">{{ displayDate(date) }}</dd>
            </div>
        </dl>
        <p class="mt-3 text-[13px] text-muted-foreground">Puedes tomar una captura de pantalla como respaldo.</p>

        <button
            type="button"
            class="mt-5 h-14 w-full rounded-2xl bg-foreground text-base font-bold text-background transition hover:opacity-90"
            @click="emit('done')"
        >
            Listo
        </button>
    </div>
</template>
