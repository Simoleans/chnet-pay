<script setup lang="ts">
/**
 * Selector de fecha: Hoy / Ayer / Otra.
 * "Otra" abre el calendario nativo con showPicker(); si el navegador no lo
 * soporta, muestra el campo de fecha visible. Devuelve YYYY-MM-DD (igual que antes).
 */
import { computed, nextTick, ref } from 'vue';
import { CalendarDays } from 'lucide-vue-next';
import { displayDate, localIsoDate } from '@/lib/dates';

const props = defineProps<{ modelValue: string; id?: string }>();
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>();

const pickerRef = ref<HTMLInputElement | null>(null);
const showNativeField = ref(false);

const isToday = computed(() => props.modelValue === localIsoDate(0));
const isYesterday = computed(() => props.modelValue === localIsoDate(-1));
const isOther = computed(() => !!props.modelValue && !isToday.value && !isYesterday.value);

const openOther = async () => {
    showNativeField.value = true;
    await nextTick();
    const el = pickerRef.value;
    if (!el) return;
    try {
        if (typeof el.showPicker === 'function') {
            el.showPicker();
            return;
        }
    } catch {
        /* algunos navegadores lanzan error: queda el campo visible */
    }
    el.focus();
};

const chip = (active: boolean) => [
    'h-12 rounded-xl border text-[15px] font-semibold transition',
    active ? 'border-foreground bg-foreground text-background' : 'bg-background hover:bg-muted',
];
</script>

<template>
    <div class="space-y-2">
        <div class="grid grid-cols-3 gap-2">
            <button type="button" :class="chip(isToday)" @click="emit('update:modelValue', localIsoDate(0))">Hoy</button>
            <button type="button" :class="chip(isYesterday)" @click="emit('update:modelValue', localIsoDate(-1))">Ayer</button>
            <button type="button" :class="[chip(isOther), 'flex items-center justify-center gap-1.5']" @click="openOther">
                <CalendarDays class="h-4 w-4" />
                Otra
            </button>
        </div>

        <!-- Campo de fecha visible cuando se elige "Otra" -->
        <div v-show="showNativeField || isOther" class="space-y-1">
            <input
                :id="id"
                ref="pickerRef"
                type="date"
                :value="modelValue"
                :max="localIsoDate(0)"
                class="h-12 w-full rounded-xl border border-input bg-background px-3 text-[15px] outline-none focus:border-foreground"
                @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            />
            <p v-if="modelValue" class="text-[13px] text-muted-foreground">Fecha elegida: {{ displayDate(modelValue) }}</p>
        </div>
    </div>
</template>
