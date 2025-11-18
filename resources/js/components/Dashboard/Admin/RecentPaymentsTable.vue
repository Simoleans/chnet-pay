<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { router } from '@inertiajs/vue3'

interface Payment {
    id: number
    reference: string
    amount: number
    amount_bs: number
    payment_date: string
    bank: string
    phone: string
    id_number: string
    user_name: string
    user_code: string
    invoice_period: string
    created_at: string
    image_path?: string
    verify_payments: boolean
}

interface Props {
    payments: Payment[]
}

const props = defineProps<Props>()

const emit = defineEmits(['viewPayment', 'viewReceipt'])

const paymentColumns = [
    { key: 'reference', label: 'Referencia' },
    { key: 'amount', label: 'Monto' },
    { key: 'payment_date', label: 'Fecha Pago' },
    { key: 'bank', label: 'Banco' },
    { key: 'invoice_period', label: 'Período' },
    { key: 'verification_status', label: 'Verificación' },
    { key: 'created_at', label: 'Registrado' },
    { key: 'actions', label: 'Acciones' },
]

const getValue = (item: Payment, key: string) => {
    switch (key) {
        case 'reference':
            return item.reference
        case 'payment_date':
            return item.payment_date
        case 'bank':
            return item.bank || ''
        case 'invoice_period':
            return item.invoice_period
        case 'created_at':
            return item.created_at
        default:
            return (item as any)[key]
    }
}

const viewPayment = (payment: Payment) => {
    emit('viewPayment', payment)
}

const viewReceipt = (payment: Payment) => {
    emit('viewReceipt', payment)
}
</script>

<template>
    <div class="flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <div class="p-4">
            <div class="mb-4 flex justify-between items-center">
                <h2 class="text-xl font-semibold">📋 Pagos Recientes (Últimos 50)</h2>
                <Button
                    as="a"
                    :href="route('payments.index')"
                    variant="outline"
                    size="sm"
                    class="flex items-center gap-2"
                >
                    Ver todos
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Button>
            </div>

            <div v-if="payments && payments.length > 0" class="w-full overflow-auto rounded-xl border bg-background shadow-sm">
                <table class="min-w-max w-full text-sm text-left border-collapse">
                    <thead class="border-b bg-muted">
                        <tr>
                            <th
                                v-for="column in paymentColumns"
                                :key="column.key"
                                class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(item, i) in payments"
                            :key="i"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td v-for="column in paymentColumns" :key="column.key" class="px-4 py-3 whitespace-nowrap">
                                <template v-if="column.key === 'actions'">
                                    <div class="flex gap-2">
                                        <button
                                            v-if="item.image_path"
                                            @click="viewReceipt(item)"
                                            class="px-3 py-1 bg-green-600 text-white rounded-md hover:bg-green-700 transition text-sm"
                                        >
                                            Ver Comprobante
                                        </button>
                                        <button
                                            @click="viewPayment(item)"
                                            class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-sm"
                                        >
                                            Ver Detalles
                                        </button>
                                    </div>
                                </template>
                                <template v-else-if="column.key === 'amount'">
                                    <div class="flex flex-col">
                                        <span class="font-medium">${{ Number(item.amount).toFixed(2) }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            Bs. {{ Number(item.amount_bs).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                                        </span>
                                    </div>
                                </template>
                                <template v-else-if="column.key === 'verification_status'">
                                    <div class="flex items-center gap-2">
                                        <div
                                            :class="[
                                                'w-2 h-2 rounded-full',
                                                item.verify_payments ? 'bg-green-500' : 'bg-red-500'
                                            ]"
                                        ></div>
                                        <span
                                            :class="[
                                                'text-xs font-medium',
                                                item.verify_payments ? 'text-green-600' : 'text-red-600'
                                            ]"
                                        >
                                            {{ item.verify_payments ? 'Verificado' : 'Sin verificar' }}
                                        </span>
                                    </div>
                                </template>
                                <template v-else>
                                    {{ getValue(item, column.key) }}
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="text-center py-12 text-muted-foreground">
                <div class="flex flex-col items-center gap-2">
                    <div class="text-4xl">💳</div>
                    <p class="text-lg font-medium">No hay pagos registrados</p>
                </div>
            </div>
        </div>
    </div>
</template>

