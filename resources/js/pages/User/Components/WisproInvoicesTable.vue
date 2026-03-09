<script setup lang="ts">
import { useBcvStore } from '@/stores/bcv'
import { Button } from '@/components/ui/button'

interface Props {
    invoices: any[]
}

const props = defineProps<Props>()
const emit = defineEmits(['openPaymentModal'])
const bcvStore = useBcvStore()

const openPaymentModal = (invoice: any) => {
    emit('openPaymentModal', invoice)
}

const formatDate = (dateString: string) => {
    if (!dateString) return '-'
    const date = new Date(dateString)
    return date.toLocaleDateString('es-VE', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(price)
}

const formatPriceBs = (priceUSD: number) => {
    const priceBs = priceUSD * bcvStore.bcv
    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(priceBs)
}

const getInvoiceStateLabel = (state: string) => {
    const stateLabels: { [key: string]: string } = {
        'draft': 'Borrador',
        'authorizing': 'Autorizando',
        'authorizing_error': 'Error de Autorización',
        'pending': 'Pendiente de Pago',
        'paid': 'Pagada',
        'void': 'Anulada',
        'issuing': 'Emitiendo'
    }
    return stateLabels[state] || state
}

const getInvoiceStateClass = (state: string) => {
    const stateClasses: { [key: string]: string } = {
        'draft': 'bg-gray-100 text-gray-800',
        'authorizing': 'bg-blue-100 text-blue-800',
        'authorizing_error': 'bg-red-100 text-red-800',
        'pending': 'bg-yellow-100 text-yellow-800',
        'paid': 'bg-green-100 text-green-800',
        'void': 'bg-red-100 text-red-800',
        'issuing': 'bg-blue-100 text-blue-800'
    }
    return stateClasses[state] || 'bg-gray-100 text-gray-800'
}
</script>

<template>
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
        <h2 class="text-lg font-semibold mb-4">Mis Facturas</h2>

        <div v-if="invoices && invoices.length > 0">

            <!-- ── MOBILE: Cards (visible en < md) ── -->
            <div class="flex flex-col gap-3 md:hidden">
                <div
                    v-for="invoice in invoices"
                    :key="invoice.id"
                    class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 flex flex-col gap-2"
                >
                    <!-- Cabecera: número + estado -->
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                            #{{ invoice.invoice_number }}
                        </span>
                        <span :class="['px-2 py-1 text-xs rounded font-semibold', getInvoiceStateClass(invoice.state)]">
                            {{ getInvoiceStateLabel(invoice.state) }}
                        </span>
                    </div>

                    <!-- Cliente -->
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ invoice.client_name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ invoice.client_address }}</p>
                    </div>

                    <!-- Período y vencimientos -->
                    <div class="grid grid-cols-2 gap-1 text-xs text-gray-600 dark:text-gray-400">
                        <div v-if="invoice.from && invoice.to">
                            <span class="font-medium text-gray-500 uppercase tracking-wide">Período</span><br>
                            {{ formatDate(invoice.from) }} → {{ formatDate(invoice.to) }}
                        </div>
                        <div>
                            <span class="font-medium text-gray-500 uppercase tracking-wide">Vencimiento</span><br>
                            {{ formatDate(invoice.first_due_date) }}
                            <span v-if="invoice.second_due_date"> / {{ formatDate(invoice.second_due_date) }}</span>
                        </div>
                    </div>

                    <!-- Montos + botón -->
                    <div class="flex items-center justify-between mt-1">
                        <div>
                            <p class="text-sm font-bold text-green-600">${{ formatPrice(invoice.amount) }}</p>
                            <p v-if="bcvStore.bcv" class="text-xs font-semibold text-blue-600">
                                Bs. {{ formatPriceBs(invoice.amount) }}
                            </p>
                        </div>
                        <Button
                            v-if="invoice.state === 'pending' && $page.props.auth.user.role === 0"
                            @click="openPaymentModal(invoice)"
                            size="sm"
                            class="bg-green-600 hover:bg-green-700"
                        >
                            💰 Pagar
                        </Button>
                    </div>
                </div>
            </div>

            <!-- ── DESKTOP: Tabla (visible en ≥ md) ── -->
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Factura #</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cliente</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Período</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">1ra Vencimiento</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">2da Vencimiento</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Estado</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Monto USD</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Monto Bs</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ invoice.invoice_number }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                <div class="font-medium">{{ invoice.client_name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ invoice.client_address }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                <div v-if="invoice.from && invoice.to">
                                    {{ formatDate(invoice.from) }}<br>
                                    <span class="text-xs text-gray-500">hasta</span><br>
                                    {{ formatDate(invoice.to) }}
                                </div>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                <span v-if="invoice.first_due_date">{{ formatDate(invoice.first_due_date) }}</span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                <span v-if="invoice.second_due_date">{{ formatDate(invoice.second_due_date) }}</span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <span :class="['px-2 py-1 text-xs rounded font-semibold', getInvoiceStateClass(invoice.state)]">
                                    {{ getInvoiceStateLabel(invoice.state) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-green-600">${{ formatPrice(invoice.amount) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-blue-600">
                                <div v-if="bcvStore.bcv">Bs. {{ formatPriceBs(invoice.amount) }}</div>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <Button
                                    v-if="invoice.state === 'pending' && $page.props.auth.user.role === 0"
                                    @click="openPaymentModal(invoice)"
                                    size="sm"
                                    class="bg-green-600 hover:bg-green-700"
                                >
                                    💰 Pagar
                                </Button>
                                <span v-else class="text-xs text-gray-500">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="bcvStore.date" class="mt-3 text-xs text-gray-400 text-right">
                Tasa BCV: {{ bcvStore.bcv }} ({{ bcvStore.date }})
            </div>
        </div>

        <!-- Sin facturas -->
        <div v-else class="bg-blue-50 border-l-4 border-blue-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">Este cliente aún no tiene facturas disponibles en Wispro.</p>
                </div>
            </div>
        </div>
    </div>
</template>
