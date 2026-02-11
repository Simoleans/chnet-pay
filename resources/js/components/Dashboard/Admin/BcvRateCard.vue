<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { useBcvStore } from '@/stores/bcv'
import { storeToRefs } from 'pinia'
import { usePage } from '@inertiajs/vue3'
import { useNotifications } from '@/composables/useNotifications'
import axios from 'axios'

const bcvStore = useBcvStore()
const { bcv, date, loading, error } = storeToRefs(bcvStore)
const page = usePage()
const { notify } = useNotifications()

// Estados del modal
const showUpdateModal = ref(false)
const newBcvRate = ref('')
const newBcvDate = ref('')
const saving = ref(false)

// Verificar si es admin
const isAdmin = page.props.auth?.user?.role === 1

const reloadBcvRate = async () => {
    await bcvStore.$reloadBcvAmount()
}

const openUpdateModal = () => {
    // Precargar con el valor actual
    newBcvRate.value = bcv.value || ''
    newBcvDate.value = new Date().toISOString().split('T')[0]
    showUpdateModal.value = true
}

const saveBcvRate = async () => {
    if (!newBcvRate.value || parseFloat(newBcvRate.value) <= 0) {
        notify({
            message: 'Por favor ingrese una tasa válida',
            type: 'error',
            duration: 2000
        })
        return
    }

    saving.value = true

    try {
        const response = await axios.post('/api/bcv/store', {
            rate: parseFloat(newBcvRate.value),
            date: newBcvDate.value
        })

        if (response.data.success) {
            notify({
                message: response.data.message,
                type: 'success',
                duration: 2000
            })

            // Recargar la tasa desde el store
            await bcvStore.$reloadBcvAmount()

            // Cerrar modal
            showUpdateModal.value = false
        }
    } catch (err: any) {
        console.error('Error al guardar BCV:', err)
        notify({
            message: err.response?.data?.message || 'Error al guardar la tasa BCV',
            type: 'error',
            duration: 3000
        })
    } finally {
        saving.value = false
    }
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
                <!-- Botón de actualizar manual (solo admin) -->
                <Button
                    v-if="isAdmin"
                    @click="openUpdateModal"
                    size="sm"
                    variant="default"
                    class="flex-1"
                >
                    ✏️ Actualizar BCV
                </Button>
                <Button @click="reloadBcvRate" size="sm" variant="outline" :disabled="loading" class="flex-1">
                    🔄 Recargar
                </Button>
                <Button as="a" href="https://www.bcv.org.ve/" target="_blank" size="sm" variant="outline" class="flex-1">
                    🔍 Verificar
                </Button>
            </div>
        </div>
    </div>

    <!-- Modal para actualizar BCV -->
    <Dialog v-model:open="showUpdateModal">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Actualizar Tasa BCV</DialogTitle>
                <DialogDescription>
                    Ingresa la nueva tasa del BCV. Esta será la tasa oficial para todos los cálculos del sistema.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <div class="space-y-2">
                    <label for="bcv-rate" class="text-sm font-medium">
                        Tasa BCV (Bs)
                    </label>
                    <Input
                        id="bcv-rate"
                        v-model="newBcvRate"
                        type="number"
                        step="0.01"
                        placeholder="Ej: 56.50"
                        class="w-full"
                        :disabled="saving"
                    />
                    <p class="text-xs text-muted-foreground">
                        Valor actual: {{ bcv ? `${bcv} Bs` : 'No disponible' }}
                    </p>
                </div>

                <div class="space-y-2">
                    <label for="bcv-date" class="text-sm font-medium">
                        Fecha de la tasa
                    </label>
                    <Input
                        id="bcv-date"
                        v-model="newBcvDate"
                        type="date"
                        class="w-full"
                        :disabled="saving"
                    />
                </div>

                <div class="bg-yellow-50 dark:bg-yellow-950/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-3">
                    <p class="text-xs text-yellow-800 dark:text-yellow-300 font-medium">
                        ⚠️ Esta tasa se usará para calcular todos los montos en bolívares del sistema.
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    @click="showUpdateModal = false"
                    :disabled="saving"
                >
                    Cancelar
                </Button>
                <Button
                    @click="saveBcvRate"
                    :disabled="saving || !newBcvRate || parseFloat(newBcvRate) <= 0"
                >
                    {{ saving ? 'Guardando...' : 'Guardar Tasa' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
