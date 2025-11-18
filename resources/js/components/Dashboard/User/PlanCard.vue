<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { useBcvStore } from '@/stores/bcv'
import { storeToRefs } from 'pinia'

interface Plan {
    name: string
    price: number
}

interface Props {
    userPlan: Plan | null
    pendingInvoicesCount: number
}

defineProps<Props>()

const page = usePage()
const bcvStore = useBcvStore()
const { bcv } = storeToRefs(bcvStore)

const copyCode = () => {
    if ((page.props.auth as any)?.user?.code) {
        navigator.clipboard.writeText((page.props.auth as any).user.code)
        alert('¡Código de abonado copiado!')
    }
}

const formatPrice = (price: number | null) => {
    if (!price) return '0.00'
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(price)
}

const formatPriceBs = (priceUsd: number | null) => {
    if (!priceUsd || !bcv.value) return '0.00'
    const priceBs = priceUsd * parseFloat(bcv.value)
    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(priceBs)
}
</script>

<template>
    <div class="relative min-h-[250px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <div class="p-4 h-full flex flex-col justify-between">
            <div>
                <h3 class="text-lg font-semibold mb-3">📦 Mi Plan</h3>
                <div class="space-y-3">
                    <!-- Código de Abonado -->
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Mi Abonado</p>
                        <div class="flex items-center gap-2">
                            <p class="text-2xl font-bold text-blue-600">{{ $page.props.auth.user.code }}</p>
                            <Button
                                @click="copyCode"
                                variant="outline"
                                size="sm"
                                class="h-8 px-2"
                            >
                                📋
                            </Button>
                        </div>
                    </div>

                    <!-- Estado de Facturas -->
                    <div class="border-t pt-3">
                        <p class="text-xs text-gray-500 mb-2">Estado de Facturación</p>
                        <div v-if="pendingInvoicesCount > 0" class="flex items-center gap-2 p-2 bg-yellow-50 dark:bg-yellow-950/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                            <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-300">
                                    {{ pendingInvoicesCount }} {{ pendingInvoicesCount === 1 ? 'factura pendiente' : 'facturas pendientes' }}
                                </p>
                                <p class="text-xs text-yellow-700 dark:text-yellow-400">Por favor, realiza el pago</p>
                            </div>
                        </div>
                        <div v-else class="flex items-center gap-2 p-2 bg-green-50 dark:bg-green-950/20 rounded-lg border border-green-200 dark:border-green-800">
                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-green-800 dark:text-green-300">Sin facturas pendientes</p>
                                <p class="text-xs text-green-700 dark:text-green-400">¡Todo al día!</p>
                            </div>
                        </div>
                    </div>

                    <!-- Plan y Precio -->
                    <div v-if="userPlan" class="space-y-2 border-t pt-3">
                        <div>
                            <p class="text-xs text-gray-500">Plan Contratado</p>
                            <p class="font-medium text-lg">{{ userPlan.name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Precio</p>
                            <p class="font-medium text-xl text-green-600">
                                ${{ formatPrice(userPlan.price) }} USD
                            </p>
                            <p v-if="bcv" class="font-medium text-md text-blue-600">
                                Bs. {{ formatPriceBs(userPlan.price) }}
                            </p>
                        </div>
                    </div>
                    <div v-else class="text-gray-500 border-t pt-3">
                        <p>No hay plan asignado</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

