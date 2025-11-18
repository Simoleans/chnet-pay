<script setup lang="ts">
interface Props {
    totalClients: number
}

defineProps<Props>()
</script>

<template>
    <div class="relative min-h-[200px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <div class="p-4 h-full flex flex-col justify-between">
            <div>
                <h3 class="text-lg font-semibold mb-2">👥 Total Clientes</h3>
                <div class="space-y-2">
                    <p class="text-3xl font-bold">{{ totalClients }}</p>
                    <p class="text-sm text-gray-500">Clientes registrados en el sistema local</p>
                </div>
            </div>
        </div>
    </div>
</template>

