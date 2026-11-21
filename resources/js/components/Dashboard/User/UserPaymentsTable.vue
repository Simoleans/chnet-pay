<script setup lang="ts">
// UI: formato de montos (solo para mostrar)
import { formatBs, formatUsd } from '@/lib/money'
import { ChevronRight, Receipt } from 'lucide-vue-next'
import { computed, ref } from 'vue'

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

// UI: solo los 5 más recientes; el resto con "Ver todos" (evita una lista enorme)
const LIMIT = 5
const showAll = ref(false)
const visiblePayments = computed(() => (showAll.value ? props.payments : (props.payments || []).slice(0, LIMIT)))

const emit = defineEmits(['viewPayment', 'viewReceipt'])

const paymentColumns = [
    { key: 'reference', label: 'Referencia' },
    { key: 'amount', label: 'Monto' },
    { key: 'payment_date', label: 'Fecha Pago' },
    { key: 'bank', label: 'Banco' },
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

<!-- Historial de pagos en tarjetas (antes: tabla ancha con scroll horizontal). Mismos emits. -->
<template>
    <section>
        <h2 class="mb-3 text-lg font-bold">Mis pagos</h2>

        <template v-if="payments && payments.length > 0">
        <div class="divide-y overflow-hidden rounded-2xl border bg-card">
            <div v-for="(item, i) in visiblePayments" :key="i" class="flex items-center gap-3 p-4">
                <span
                    :class="[
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                        item.verify_payments ? 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
                    ]"
                >
                    <Receipt class="h-5 w-5" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-[15px] font-semibold tabular-nums">
                        {{ formatBs(item.amount_bs) }} Bs
                        <span class="text-[13px] font-normal text-muted-foreground">· ${{ formatUsd(item.amount) }}</span>
                    </p>
                    <p class="truncate text-[13px] text-muted-foreground">
                        {{ item.payment_date }}<template v-if="item.bank"> · {{ item.bank }}</template> · Ref. {{ item.reference }}
                    </p>
                    <p :class="['text-[13px] font-semibold', item.verify_payments ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400']">
                        {{ item.verify_payments ? 'Verificado' : 'En revisión' }}
                    </p>
                </div>

                <div class="flex shrink-0 flex-col items-end gap-1">
                    <button
                        type="button"
                        class="flex h-10 items-center gap-0.5 rounded-full bg-muted pr-2 pl-3 text-[13px] font-semibold transition hover:bg-foreground hover:text-background"
                        @click="viewPayment(item)"
                    >
                        Detalles <ChevronRight class="h-4 w-4" />
                    </button>
                    <button v-if="item.image_path" type="button" class="px-2 text-[13px] font-medium underline underline-offset-4" @click="viewReceipt(item)">
                        Comprobante
                    </button>
                </div>
            </div>
        </div>
        <button
            v-if="payments.length > LIMIT"
            type="button"
            class="mt-3 text-[15px] font-semibold text-foreground/80 underline-offset-4 hover:underline"
            @click="showAll = !showAll"
        >
            {{ showAll ? 'Ver menos' : `Ver todos mis pagos (${payments.length})` }}
        </button>
        </template>

        <div v-else class="rounded-2xl border border-dashed p-8 text-center">
            <p class="text-[15px] font-medium">Todavía no tienes pagos registrados</p>
            <p class="text-[13px] text-muted-foreground">Cuando pagues una factura aparecerá aquí.</p>
        </div>
    </section>
</template>
