<script setup lang="ts">
interface Props {
    payments: any[]
    invoices: any[]
}

const props = defineProps<Props>()
</script>

<template>
    <div class="space-y-4">
        <!-- Tabla de Pagos -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
            <h2 class="text-lg font-semibold mb-4">Historial de Pagos</h2>

            <div v-if="payments && payments.length > 0" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monto</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Banco</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="payment in payments" :key="payment.id">
                            <td class="px-4 py-3">{{ payment.reference }}</td>
                            <td class="px-4 py-3">${{ payment.amount }}</td>
                            <td class="px-4 py-3">{{ payment.payment_date }}</td>
                            <td class="px-4 py-3">{{ payment.bank }}</td>
                            <td class="px-4 py-3">
                                <span :class="[
                                    'px-2 py-1 text-xs rounded',
                                    payment.verify_payments ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                ]">
                                    {{ payment.verify_payments ? 'Verificado' : 'Pendiente' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="text-center py-8 text-gray-500">
                No hay pagos registrados
            </div>
        </div>

        <!-- Tabla de Facturas -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
            <h2 class="text-lg font-semibold mb-4">Historial de Facturas</h2>

            <div v-if="invoices && invoices.length > 0" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monto Total</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pagado</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="invoice in invoices" :key="invoice.id">
                            <td class="px-4 py-3">{{ invoice.code }}</td>
                            <td class="px-4 py-3">{{ invoice.period }}</td>
                            <td class="px-4 py-3">${{ invoice.amount_due }}</td>
                            <td class="px-4 py-3">${{ invoice.amount_paid }}</td>
                            <td class="px-4 py-3">
                                <span :class="[
                                    'px-2 py-1 text-xs rounded',
                                    invoice.status === 'paid' ? 'bg-green-100 text-green-800' :
                                    invoice.status === 'partial' ? 'bg-yellow-100 text-yellow-800' :
                                    'bg-red-100 text-red-800'
                                ]">
                                    {{
                                        invoice.status === 'paid' ? 'Pagado' :
                                        invoice.status === 'partial' ? 'Parcial' :
                                        'Pendiente'
                                    }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="text-center py-8 text-gray-500">
                No hay facturas registradas
            </div>
        </div>
    </div>
</template>

