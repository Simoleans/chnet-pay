<script setup lang="ts">
/**
 * Capa de espera mientras el banco responde. Cubre la hoja para evitar
 * doble toque y le dice a la persona que no cierre la pantalla.
 */
import { Loader2 } from 'lucide-vue-next';

withDefaults(defineProps<{ title?: string; message?: string }>(), {
    title: 'Estamos confirmando con tu banco',
    message: 'Esto puede tardar unos segundos. No cierres esta pantalla.',
});
</script>

<template>
    <div class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-4 bg-background/95 px-8 text-center backdrop-blur-sm" aria-live="polite">
        <div class="relative flex h-20 w-20 items-center justify-center">
            <span class="absolute inset-0 animate-ping rounded-full bg-brand/20" />
            <span class="relative flex h-16 w-16 items-center justify-center rounded-full bg-foreground text-background">
                <Loader2 class="h-8 w-8 animate-spin" />
            </span>
        </div>
        <div>
            <p class="text-lg font-bold">{{ title }}</p>
            <p class="mt-1 text-[15px] text-muted-foreground">{{ message }}</p>
        </div>
    </div>
</template>
