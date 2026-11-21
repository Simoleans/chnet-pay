<script setup lang="ts">
/**
 * Alerta DENTRO de la hoja de pago (no flotante).
 * Errores y avisos se quedan hasta que la persona toque "Entendido".
 * Reemplaza a SweetAlert en el flujo de pago: Swal se abría encima del
 * Dialog de shadcn, que bloquea foco/clics fuera de él, y a veces no respondía.
 */
import { AlertTriangle, CheckCircle2, XCircle } from 'lucide-vue-next';

import type { PaymentAlert } from './types';

defineProps<{ alert: PaymentAlert }>();
const emit = defineEmits<{ (e: 'dismiss'): void }>();

const styles: Record<PaymentAlert['type'], string> = {
    error: 'border-red-300 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100',
    warning: 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100',
    success: 'border-green-300 bg-green-50 text-green-900 dark:border-green-900 dark:bg-green-950/40 dark:text-green-100',
    info: 'border-border bg-muted text-foreground',
};
const titles: Record<PaymentAlert['type'], string> = {
    error: 'No pudimos completar el pago',
    warning: 'Revisa estos datos',
    success: 'Listo',
    info: 'Información',
};
</script>

<template>
    <div role="alert" aria-live="assertive" :class="['rounded-2xl border-2 p-4', styles[alert.type]]">
        <div class="flex gap-3">
            <XCircle v-if="alert.type === 'error'" class="mt-0.5 h-6 w-6 shrink-0" />
            <AlertTriangle v-else-if="alert.type === 'warning'" class="mt-0.5 h-6 w-6 shrink-0" />
            <CheckCircle2 v-else class="mt-0.5 h-6 w-6 shrink-0" />
            <div class="min-w-0 flex-1">
                <p class="text-[15px] font-bold">{{ alert.title || titles[alert.type] }}</p>
                <p class="mt-0.5 text-[15px] leading-snug">{{ alert.message }}</p>
            </div>
        </div>
        <button
            v-if="alert.type !== 'success'"
            type="button"
            class="mt-3 h-11 w-full rounded-xl bg-foreground text-[15px] font-semibold text-background transition hover:opacity-90"
            @click="emit('dismiss')"
        >
            Entendido
        </button>
    </div>
</template>
