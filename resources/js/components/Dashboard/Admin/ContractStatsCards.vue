<script setup lang="ts">
interface Props {
    contractStats: {
        enabled?: number
        alerted?: number
        degraded?: number
        disabled?: number
    }
}

defineProps<Props>()
</script>

<template>
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <!-- Contratos Activos (Enabled) -->
        <div class="relative min-h-[150px] overflow-hidden rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/20">
            <div class="p-4">
                <h3 class="text-sm font-semibold mb-2 text-green-700 dark:text-green-300">✅ Activos</h3>
                <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ contractStats.enabled || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Contratos habilitados</p>
            </div>
        </div>

        <!-- Contratos Alertados -->
        <div class="relative min-h-[150px] overflow-hidden rounded-xl border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950/20">
            <div class="p-4">
                <h3 class="text-sm font-semibold mb-2 text-yellow-700 dark:text-yellow-300">⚠️ Alertados</h3>
                <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ contractStats.alerted || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Contratos con alertas</p>
            </div>
        </div>

        <!-- Contratos Degradados -->
        <div class="relative min-h-[150px] overflow-hidden rounded-xl border border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-950/20">
            <div class="p-4">
                <h3 class="text-sm font-semibold mb-2 text-orange-700 dark:text-orange-300">⬇️ Degradados</h3>
                <p class="text-3xl font-bold text-orange-600 dark:text-orange-400">{{ contractStats.degraded || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Contratos degradados</p>
            </div>
        </div>

        <!-- Contratos Deshabilitados -->
        <div class="relative min-h-[150px] overflow-hidden rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/20">
            <div class="p-4">
                <h3 class="text-sm font-semibold mb-2 text-red-700 dark:text-red-300">❌ Deshabilitados</h3>
                <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ contractStats.disabled || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">Contratos deshabilitados</p>
            </div>
        </div>
    </div>
</template>

