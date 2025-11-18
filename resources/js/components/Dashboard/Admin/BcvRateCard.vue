<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { useBcvStore } from '@/stores/bcv'
import { storeToRefs } from 'pinia'

const bcvStore = useBcvStore()
const { bcv, date, loading, error } = storeToRefs(bcvStore)

const reloadBcvRate = async () => {
    await bcvStore.$reloadBcvAmount()
}
</script>

<template>
    <div class="relative min-h-[200px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <div class="p-4 h-full flex flex-col justify-between">
            <div>
                <h3 class="text-lg font-semibold mb-2">💵 Tasa BCV</h3>
                <div v-if="loading" class="text-sm text-gray-500">Cargando...</div>
                <div v-else-if="error" class="text-sm text-red-500">{{ error }}</div>
                <div v-else class="space-y-2">
                    <p class="text-3xl font-bold">{{ bcv ? `${bcv} Bs` : 'No disponible' }}</p>
                    <p class="text-sm text-gray-500">{{ date ? `Fecha: ${date}` : '' }}</p>
                </div>
            </div>
            <div v-if="!loading && !error" class="flex gap-2 mt-4">
                <Button @click="reloadBcvRate" size="sm" variant="outline" :disabled="loading" class="flex-1">
                    Actualizar
                </Button>
                <Button as="a" href="https://www.bcv.org.ve/" target="_blank" size="sm" variant="outline" class="flex-1">
                    Verificar
                </Button>
            </div>
        </div>
    </div>
</template>

