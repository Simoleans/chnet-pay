<script setup lang="ts">
/**
 * Lista de bancos con los más usados arriba.
 * El VALOR de cada opción mantiene el mismo formato que antes:
 *  - pad = true  → "0134" (padStart 4)  → Pago Móvil BNC / BDV
 *  - pad = false → String(b.Code)        → C2P
 */
import { computed } from 'vue';

interface Bank {
    Code: string | number;
    Name: string;
}
const props = withDefaults(
    defineProps<{
        modelValue: string;
        banks: Bank[] | null | undefined;
        pad?: boolean;
        loading?: boolean;
        error?: string | null;
        id?: string;
        placeholder?: string;
    }>(),
    { pad: true, loading: false, error: null, placeholder: 'Selecciona tu banco' },
);
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>();

// Bancos más usados (en este orden). Ajusta esta lista si cambia la realidad de tus clientes.
const POPULAR = ['0102', '0134', '0105', '0108', '0191', '0172'];

const code4 = (b: Bank) => String(b.Code).padStart(4, '0');
const valueOf = (b: Bank) => (props.pad ? code4(b) : String(b.Code));

const popular = computed(() =>
    POPULAR.map((c) => (props.banks || []).find((b) => code4(b) === c)).filter(Boolean) as Bank[],
);
const others = computed(() => (props.banks || []).filter((b) => !POPULAR.includes(code4(b))));
</script>

<template>
    <div class="space-y-1">
        <select
            :id="id"
            :value="modelValue"
            class="h-12 w-full rounded-xl border border-input bg-background px-3 text-[15px] shadow-xs outline-none transition focus:border-foreground focus:ring-2 focus:ring-foreground/10"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <option value="" disabled>{{ loading ? 'Cargando bancos…' : placeholder }}</option>
            <optgroup v-if="popular.length" label="Más usados">
                <option v-for="b in popular" :key="'p' + b.Code" :value="valueOf(b)">{{ b.Name }} ({{ code4(b) }})</option>
            </optgroup>
            <optgroup label="Todos los bancos">
                <option v-for="b in others" :key="b.Code" :value="valueOf(b)">{{ b.Name }} ({{ code4(b) }})</option>
            </optgroup>
        </select>
        <p v-if="error" class="text-[13px] text-destructive">{{ error }}</p>
    </div>
</template>
