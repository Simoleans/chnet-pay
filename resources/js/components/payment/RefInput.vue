<script setup lang="ts">
/**
 * Campo de referencia bancaria.
 * - Al ESCRIBIR: solo dígitos y se detiene en `length` (no pisa lo ya escrito).
 * - Al PEGAR un número largo: conserva los ÚLTIMOS `length` dígitos.
 * El v-model devuelve el mismo string de dígitos que antes usaba el payload.
 */
interface Props {
    modelValue: string;
    length: number;
    id?: string;
}
const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>();

const onInput = (e: Event) => {
    const el = e.target as HTMLInputElement;
    const clean = el.value.replace(/\D/g, '').slice(0, props.length);
    if (clean !== el.value) el.value = clean;
    emit('update:modelValue', clean);
};

const onPaste = (e: ClipboardEvent) => {
    const text = e.clipboardData?.getData('text') ?? '';
    const digits = text.replace(/\D/g, '');
    if (!digits) return;
    e.preventDefault();
    emit('update:modelValue', digits.slice(-props.length));
};
</script>

<template>
    <div>
        <input
            :id="id"
            :value="modelValue"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            :maxlength="length"
            :pattern="`[0-9]{${length}}`"
            :placeholder="'•'.repeat(length)"
            class="h-16 w-full rounded-2xl border-2 border-input bg-background text-center font-mono text-3xl font-bold tracking-[0.45em] outline-none transition placeholder:text-muted-foreground/40 focus:border-foreground"
            @input="onInput"
            @paste="onPaste"
        />
        <!-- Barra de progreso de dígitos: ayuda a saber cuántos faltan -->
        <div class="mt-2 flex gap-1.5">
            <span
                v-for="i in length"
                :key="i"
                :class="['h-1.5 flex-1 rounded-full transition-colors', i <= modelValue.length ? 'bg-foreground' : 'bg-muted']"
            />
        </div>
    </div>
</template>
