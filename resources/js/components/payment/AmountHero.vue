<script setup lang="ts">
/**
 * Bloque visual del monto a pagar. Solo presentación: recibe todo por props.
 */
interface Props {
    amountBs: string;
    amountUsd: number;
    rate: string | number | null | undefined;
    label?: string;
    invoicesCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Total a pagar',
    invoicesCount: 0,
});

const formatBs = (value: string) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return value;
    return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
};

const formatRate = (value: string | number | null | undefined) => {
    const n = parseFloat(String(value ?? ''));
    return Number.isFinite(n) ? n.toFixed(2) : '-';
};
</script>

<template>
    <div class="relative overflow-hidden rounded-2xl bg-neutral-950 px-5 py-5 text-white dark:bg-neutral-900 dark:ring-1 dark:ring-white/10">
        <div class="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-brand/25 blur-3xl" />
        <div class="relative flex items-start justify-between gap-3">
            <p class="text-[13px] font-medium text-white/75">{{ label }}</p>
            <span
                v-if="invoicesCount > 1"
                class="rounded-full bg-white/10 px-2.5 py-0.5 text-[13px] font-semibold text-white/80"
            >
                {{ invoicesCount }} facturas
            </span>
        </div>

        <template v-if="amountBs && amountUsd">
            <p class="relative mt-1 text-[2.5rem] leading-none font-bold tracking-tight tabular-nums">
                {{ formatBs(amountBs) }}<span class="ml-1.5 text-xl font-semibold text-white/75">Bs</span>
            </p>
            <div class="relative mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-white/75">
                <span class="tabular-nums">${{ amountUsd.toFixed(2) }} USD</span>
                <span class="h-1 w-1 rounded-full bg-white/30" />
                <span class="tabular-nums">Tasa BCV {{ formatRate(props.rate) }} Bs</span>
            </div>
        </template>
        <template v-else>
            <div class="relative mt-2 h-10 w-48 animate-pulse rounded-lg bg-white/10" />
            <p class="relative mt-3 text-[13px] text-white/70">Calculando monto con la tasa BCV…</p>
        </template>
    </div>
</template>
