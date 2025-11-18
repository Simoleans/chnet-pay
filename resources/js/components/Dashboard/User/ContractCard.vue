<script setup lang="ts">
import { Button } from '@/components/ui/button'

interface Contract {
    state: string
    start_date: string | null
    latitude?: string | null
    longitude?: string | null
}

interface Props {
    userContract: Contract | null
}

defineProps<Props>()

const formatDate = (dateString: string | null) => {
    if (!dateString) return 'N/A'
    const date = new Date(dateString)
    return date.toLocaleDateString('es-VE', { year: 'numeric', month: 'long', day: 'numeric' })
}

const getStateLabel = (state: string) => {
    const stateLabels: { [key: string]: string } = {
        'enabled': '✅ Activo',
        'alerted': '⚠️ Alertado',
        'disabled': '❌ Deshabilitado',
        'degraded': '⬇️ Degradado'
    }
    return stateLabels[state] || state
}

const getMapUrl = (contract: Contract | null) => {
    if (!contract || !contract.latitude || !contract.longitude) {
        return ''
    }
    const lat = parseFloat(contract.latitude)
    const lng = parseFloat(contract.longitude)
    return `https://www.google.com/maps?q=${lat},${lng}`
}
</script>

<template>
    <div class="relative min-h-[250px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <div class="p-4 h-full flex flex-col justify-between">
            <div>
                <h3 class="text-lg font-semibold mb-3">📋 Mi Contrato</h3>
                <div v-if="userContract" class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-500">Estado del Servicio</p>
                        <span :class="[
                            'px-2 py-1 text-xs rounded font-semibold inline-block mt-1',
                            userContract.state === 'enabled' ? 'bg-green-100 text-green-800' :
                            userContract.state === 'alerted' ? 'bg-yellow-100 text-yellow-800' :
                            userContract.state === 'disabled' ? 'bg-red-100 text-red-800' :
                            userContract.state === 'degraded' ? 'bg-orange-100 text-orange-800' :
                            'bg-gray-100 text-gray-800'
                        ]">
                            {{ getStateLabel(userContract.state) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Fecha de Inicio</p>
                        <p class="font-medium">{{ formatDate(userContract.start_date) }}</p>
                    </div>
                    <div v-if="userContract.latitude && userContract.longitude">
                        <Button
                            as="a"
                            :href="getMapUrl(userContract)"
                            target="_blank"
                            variant="outline"
                            size="sm"
                            class="w-full flex items-center justify-center gap-2"
                        >
                            📍 Ver Ubicación en Maps
                        </Button>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-gray-500">
                    <p>No hay contrato disponible</p>
                </div>
            </div>
        </div>
    </div>
</template>

