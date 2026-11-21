<script setup lang="ts">
/**
 * Fila "etiqueta / valor" con botón para copiar ese dato individual.
 * Solo UI: no afecta ningún payload.
 */
import { ref } from 'vue';
import { Check, Copy } from 'lucide-vue-next';

interface Props {
    label: string;
    value?: string | null;
    copyValue?: string | null;
    mono?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    value: '',
    copyValue: null,
    mono: false,
});

const copied = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;

const copy = async () => {
    const text = String(props.copyValue ?? props.value ?? '').trim();
    if (!text) return;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        copied.value = true;
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => (copied.value = false), 1400);
    } catch {
        /* sin acción: el usuario puede usar "Copiar todo" */
    }
};
</script>

<template>
    <button
        type="button"
        class="group flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/60 active:bg-muted focus:outline-none focus-visible:bg-muted"
        @click="copy"
    >
        <div class="min-w-0">
            <p class="text-[13px] font-medium text-muted-foreground">{{ label }}</p>
            <p :class="['truncate text-[15px] font-semibold text-foreground', mono && 'tabular-nums']">
                {{ value || '—' }}
            </p>
        </div>
        <span
            :class="[
                'flex h-8 shrink-0 items-center gap-1 rounded-full px-2.5 text-[13px] font-semibold transition-all',
                copied
                    ? 'bg-brand/15 text-green-700 dark:text-brand'
                    : 'bg-muted text-muted-foreground group-hover:bg-foreground group-hover:text-background',
            ]"
        >
            <Check v-if="copied" class="h-3.5 w-3.5" />
            <Copy v-else class="h-3.5 w-3.5" />
            {{ copied ? 'Copiado' : 'Copiar' }}
        </span>
    </button>
</template>
