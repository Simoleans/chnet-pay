<script setup lang="ts">
/**
 * Confirmación del monto pagado (en vez de un campo editable expuesto).
 * - "Sí, exacto" → deja el modelo igual al monto esperado.
 * - "Pagué otro monto" → muestra el campo para escribirlo.
 * El v-model sigue siendo el mismo string que antes (paymentAmount / p2pImporte).
 */
import { computed, ref, watch } from 'vue';
import { Check, Pencil } from 'lucide-vue-next';
import { formatBs } from '@/lib/money';

const props = defineProps<{ modelValue: string; expected: string }>();
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>();

const sameAsExpected = (v: string) => Number(String(v).replace(',', '.')) === Number(props.expected);

// Si el valor guardado/restaurado difiere del esperado, abrimos directo en "otro monto"
const custom = ref(!!props.modelValue && !!props.expected && !sameAsExpected(props.modelValue));
watch(
    () => props.expected,
    () => {
        if (!custom.value && props.expected && !props.modelValue) emit('update:modelValue', props.expected);
    },
    { immediate: true },
);

const chooseExact = () => {
    custom.value = false;
    emit('update:modelValue', props.expected);
};
const chooseCustom = () => {
    custom.value = true;
};

const exactLabel = computed(() => (props.expected ? `${formatBs(props.expected)} Bs` : '…'));
</script>

<template>
    <div class="space-y-2">
        <button
            type="button"
            :class="[
                'flex w-full items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition',
                !custom ? 'border-foreground bg-foreground/[0.03]' : 'border-input hover:bg-muted/50',
            ]"
            @click="chooseExact"
        >
            <span :class="['flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2', !custom ? 'border-foreground bg-foreground text-background' : 'border-input']">
                <Check v-if="!custom" class="h-3.5 w-3.5" />
            </span>
            <span class="text-[15px]">
                Sí, pagué exactamente <span class="font-bold tabular-nums">{{ exactLabel }}</span>
            </span>
        </button>

        <button
            type="button"
            :class="[
                'flex w-full items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition',
                custom ? 'border-foreground bg-foreground/[0.03]' : 'border-input hover:bg-muted/50',
            ]"
            @click="chooseCustom"
        >
            <span :class="['flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2', custom ? 'border-foreground bg-foreground text-background' : 'border-input']">
                <Pencil v-if="custom" class="h-3 w-3" />
            </span>
            <span class="text-[15px]">Pagué otro monto</span>
        </button>

        <div v-if="custom" class="relative">
            <input
                :value="modelValue"
                type="text"
                inputmode="decimal"
                placeholder="Ej: 5911.50"
                class="h-12 w-full rounded-xl border border-input bg-background px-3 pr-12 text-lg font-bold tabular-nums outline-none focus:border-foreground"
                @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            />
            <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-sm font-semibold text-muted-foreground">Bs</span>
            <p class="mt-1 text-[13px] text-muted-foreground">Escribe el monto tal cual aparece en tu comprobante.</p>
        </div>
    </div>
</template>
