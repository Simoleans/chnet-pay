<template>
    <AppLayout>
        <Head title="Pagos Wispro" />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex justify-between flex-col md:lg:flex-row">
                <h1 class="text-2xl font-semibold">Pagos Wispro</h1>
            </div>

            <div class="flex flex-col gap-4 mb-2 md:flex-row md:justify-between md:items-end">
                <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-end">
                    <div class="flex flex-col">
                        <label class="text-xs text-muted-foreground mb-1">Fecha de pago:</label>
                        <input
                            v-model="fromDate"
                            @change="submitFilter"
                            type="date"
                            class="p-2 border rounded-md dark:text-black text-sm"
                        />
                    </div>
                    <Button variant="outline" @click="restoreFilters">
                        Restaurar filtro
                    </Button>
                </div>
            </div>

            <div v-if="payments && payments.meta" class="p-3 bg-blue-50 rounded-lg overflow-x-auto">
                <p class="text-sm text-blue-700 whitespace-nowrap">
                    <strong>Total:</strong> {{ payments.meta.pagination?.total_records || 0 }} |
                    <strong>Página:</strong> {{ payments.meta.pagination?.current_page || 1 }} de {{ payments.meta.pagination?.total_pages || 1 }} |
                    <strong>Por página:</strong> {{ payments.meta.pagination?.per_page || 0 }}
                </p>
            </div>

            <div class="w-full overflow-auto rounded-xl border bg-background shadow-sm">
                <table class="min-w-max w-full text-sm text-left border-collapse">
                    <thead class="border-b bg-muted">
                        <tr>
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="payment in paymentsData"
                            :key="payment.id"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td class="px-4 py-3">
                                <div class="flex flex-col max-w-xs">
                                    <span class="font-medium truncate" :title="payment.client_name">
                                        {{ payment.client_name || 'N/A' }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        ID {{ payment.client_public_id || 'N/A' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-medium">${{ formatAmount(payment.amount) }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ formatDate(payment.payment_date) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ payment.transaction_kind || 'N/A' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="max-w-xs truncate" :title="payment.comment">
                                    {{ payment.comment || 'N/A' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ invoiceNumbers(payment) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded font-medium',
                                        payment.state === 'success'
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-yellow-100 text-yellow-800'
                                    ]"
                                >
                                    {{ payment.state === 'success' ? 'Exitoso' : (payment.state || 'N/A') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <Button
                                    size="sm"
                                    :disabled="!hasInvoices(payment) || loadingPaymentId === payment.id"
                                    @click="viewInvoice(payment)"
                                >
                                    {{ loadingPaymentId === payment.id ? 'Cargando...' : 'Ver Factura' }}
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!paymentsData || paymentsData.length === 0" class="text-center py-8 text-gray-500">
                No se encontraron pagos de Wispro.
            </div>

            <div
                v-if="payments && payments.meta && payments.meta.pagination"
                class="flex flex-col gap-3 px-4 py-3 bg-white border-t border-gray-200 sm:px-6"
            >
                <span class="text-sm text-gray-700 text-center sm:text-left">
                    Página
                    <span class="font-medium">{{ payments.meta.pagination.current_page }}</span>
                    de
                    <span class="font-medium">{{ payments.meta.pagination.total_pages }}</span>
                    <span class="hidden sm:inline">
                        ({{ payments.meta.pagination.total_records }} registros)
                    </span>
                </span>

                <div class="overflow-x-auto">
                    <div class="flex gap-1 justify-center sm:justify-end min-w-max">
                        <button
                            @click="previousPage"
                            :disabled="payments.meta.pagination.current_page === 1"
                            class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <span class="hidden sm:inline">← Anterior</span>
                            <span class="sm:hidden">←</span>
                        </button>

                        <div class="flex gap-1">
                            <button
                                v-for="page in visiblePages"
                                :key="page"
                                @click="goToPage(page)"
                                :class="{
                                    'bg-blue-600 text-white': page === payments.meta.pagination.current_page,
                                    'bg-white text-gray-500 hover:bg-gray-50': page !== payments.meta.pagination.current_page
                                }"
                                class="relative inline-flex items-center px-3 py-2 text-sm font-medium border border-gray-300 rounded-md transition-colors"
                            >
                                {{ page }}
                            </button>
                        </div>

                        <button
                            @click="nextPage"
                            :disabled="payments.meta.pagination.current_page === payments.meta.pagination.total_pages"
                            class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <span class="hidden sm:inline">Siguiente →</span>
                            <span class="sm:hidden">→</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:open="showInvoiceModal">
            <DialogContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Factura Wispro</DialogTitle>
                    <DialogDescription>
                        Datos principales de la factura y sus ítems
                    </DialogDescription>
                </DialogHeader>

                <div v-if="invoiceError" class="text-sm text-red-600">
                    {{ invoiceError }}
                </div>

                <div v-else class="max-h-[70vh] overflow-auto space-y-4">
                    <div
                        v-for="invoice in invoiceDetails"
                        :key="invoice.id"
                        class="rounded-lg border p-4 space-y-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm text-muted-foreground">Factura</p>
                                <p class="text-lg font-semibold">#{{ invoice.invoice_number }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded font-medium',
                                        invoice.state === 'paid'
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-yellow-100 text-yellow-800'
                                    ]"
                                >
                                    {{ invoiceStateLabel(invoice.state) }}
                                </span>
                                <Button size="sm" @click="openInvoicePdf(invoice.id)">
                                    Ver factura
                                </Button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-muted-foreground">Cliente</p>
                                <p class="font-medium">{{ invoice.client_name || 'N/A' }}</p>
                                <p class="text-xs text-muted-foreground">
                                    Cédula: {{ invoice.client_national_identification_number || 'N/A' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Empresa</p>
                                <p class="font-medium">{{ invoice.invoicing_firm_company_name || 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Período</p>
                                <p class="font-medium">{{ formatDateOnly(invoice.from) }} — {{ formatDateOnly(invoice.to) }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Emisión</p>
                                <p class="font-medium">{{ formatDate(invoice.issued_at) }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Vencimiento</p>
                                <p class="font-medium">{{ formatDateOnly(invoice.first_due_date) }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Total</p>
                                <p class="font-semibold text-green-700">${{ formatAmount(invoice.gross_amount) }}</p>
                                <!-- <p class="text-xs text-muted-foreground">
                                    Neto ${{ formatAmount(invoice.net_amount) }} · IVA ${{ formatAmount(invoice.tax_amount) }}
                                </p> -->
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-medium mb-2">Ítems</p>
                            <div class="overflow-auto rounded-md border">
                                <table class="w-full text-sm">
                                    <thead class="bg-muted">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Descripción</th>
                                            <th class="px-3 py-2 text-left font-medium text-muted-foreground">Código</th>
                                            <th class="px-3 py-2 text-right font-medium text-muted-foreground">Cant.</th>
                                            <th class="px-3 py-2 text-right font-medium text-muted-foreground">Neto</th>
                                            <th class="px-3 py-2 text-right font-medium text-muted-foreground">IVA</th>
                                            <th class="px-3 py-2 text-right font-medium text-muted-foreground">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in invoice.items || []"
                                            :key="item.id"
                                            class="border-t"
                                        >
                                            <td class="px-3 py-2">{{ item.description || 'N/A' }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap">{{ item.product_code || 'N/A' }}</td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ item.quantity }}</td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap">${{ formatAmount(item.net_amount) }}</td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap">${{ formatAmount(item.tax_amount) }}</td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap font-medium">${{ formatAmount(item.gross_amount) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div v-if="invoiceDetails.length === 0" class="text-sm text-gray-500">
                        No se encontraron datos de factura.
                    </div>

                    <div>
                        <Button variant="outline" size="sm" @click="showFullResponse = !showFullResponse">
                            {{ showFullResponse ? 'Ocultar respuesta' : 'Ver respuesta completa' }}
                        </Button>
                    </div>

                    <pre
                        v-if="showFullResponse"
                        class="max-h-[40vh] overflow-auto rounded-md bg-muted p-4 text-xs whitespace-pre-wrap"
                    >{{ JSON.stringify(invoiceResponse, null, 2) }}</pre>
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Cerrar</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'

interface PaymentTransaction {
    id: string
    payment_id: string
    invoice_id: string
    invoice_number: string
    amount: string
}

interface WisproPayment {
    id: string
    public_id: number
    created_at: string
    updated_at: string
    state: string
    amount: string
    comment: string | null
    name_user: string | null
    client_id: string
    client_name: string
    client_public_id: number
    payment_date: string
    credit_amount: string
    name_collector: string | null
    transaction_kind: string | null
    payment_transactions: PaymentTransaction[]
}

interface WisproResponse {
    status: number
    meta: {
        object: string
        pagination: {
            total_records: number
            total_pages: number
            per_page: number
            current_page: number
        }
    }
    data: WisproPayment[]
}

interface InvoiceItem {
    id: string
    description: string | null
    product_code: string | null
    quantity: number
    net_amount: string | number
    tax_amount: string | number
    gross_amount: string | number
}

interface WisproInvoice {
    id: string
    invoice_number: string
    client_name: string | null
    client_national_identification_number: string | null
    invoicing_firm_company_name: string | null
    from: string
    to: string
    issued_at: string
    first_due_date: string
    state: string
    gross_amount: string | number
    net_amount: string | number
    tax_amount: string | number
    items: InvoiceItem[]
}

const props = defineProps<{
    payments: WisproResponse
    filters: {
        from?: string
    }
}>()

const defaultFrom = `${new Date().getFullYear()}-07-01`
const fromDate = ref(props.filters?.from || defaultFrom)

const columns = [
    { key: 'client_name', label: 'Cliente' },
    { key: 'amount', label: 'Monto' },
    { key: 'payment_date', label: 'Fecha de pago' },
    { key: 'transaction_kind', label: 'Tipo' },
    { key: 'comment', label: 'Comentario' },
    { key: 'invoice', label: 'Factura' },
    { key: 'state', label: 'Estado' },
    { key: 'actions', label: '' },
]

const showInvoiceModal = ref(false)
const showFullResponse = ref(false)
const invoiceResponse = ref<unknown>(null)
const invoiceError = ref('')
const loadingPaymentId = ref<string | null>(null)

const invoiceDetails = computed((): WisproInvoice[] => {
    const entries = (invoiceResponse.value as { data?: any[] } | null)?.data || []

    return entries
        .map((entry) => entry?.data?.data)
        .filter(Boolean)
})

const paymentsData = computed((): WisproPayment[] => {
    return props.payments?.data || []
})

const visiblePages = computed(() => {
    if (!props.payments?.meta?.pagination) return []

    const { current_page, total_pages } = props.payments.meta.pagination
    const pages: number[] = []
    const maxVisible = 5

    let start = Math.max(1, current_page - Math.floor(maxVisible / 2))
    const end = Math.min(total_pages, start + maxVisible - 1)

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1)
    }

    for (let i = start; i <= end; i++) {
        pages.push(i)
    }

    return pages
})

const navigate = (page: number) => {
    router.get(route('payments-wispro.index'), {
        page,
        from: fromDate.value,
    }, {
        preserveState: true,
        replace: true,
    })
}

const submitFilter = () => {
    navigate(1)
}

const restoreFilters = () => {
    fromDate.value = defaultFrom
    navigate(1)
}

const previousPage = () => {
    const current = props.payments?.meta?.pagination?.current_page
    if (current && current > 1) {
        navigate(current - 1)
    }
}

const nextPage = () => {
    if (!props.payments?.meta?.pagination) return
    const { current_page, total_pages } = props.payments.meta.pagination
    if (current_page < total_pages) {
        navigate(current_page + 1)
    }
}

const goToPage = (page: number) => {
    if (page !== props.payments?.meta?.pagination?.current_page) {
        navigate(page)
    }
}

const formatAmount = (amount?: string | number) => {
    return Number(amount || 0).toFixed(2)
}

const formatDate = (value?: string) => {
    if (!value) return 'N/A'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return value
    return date.toLocaleString('es-VE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const formatDateOnly = (value?: string) => {
    if (!value) return 'N/A'
    const date = new Date(`${value}T00:00:00`)
    if (Number.isNaN(date.getTime())) return value
    return date.toLocaleDateString('es-VE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    })
}

const invoiceStateLabel = (state?: string) => {
    const labels: Record<string, string> = {
        draft: 'Borrador',
        pending: 'Pendiente',
        paid: 'Pagada',
        void: 'Anulada',
    }

    return labels[state || ''] || state || 'N/A'
}

const invoiceNumbers = (payment: WisproPayment) => {
    const numbers = payment.payment_transactions
        ?.map((transaction) => transaction.invoice_number)
        .filter(Boolean)

    return numbers && numbers.length > 0 ? numbers.join(', ') : 'N/A'
}

const getInvoiceIds = (payment: WisproPayment) => {
    return [...new Set(
        (payment.payment_transactions || [])
            .map((transaction) => transaction.invoice_id)
            .filter(Boolean)
    )]
}

const hasInvoices = (payment: WisproPayment) => {
    return getInvoiceIds(payment).length > 0
}

const viewInvoice = async (payment: WisproPayment) => {
    const invoiceIds = getInvoiceIds(payment)

    if (invoiceIds.length === 0) {
        return
    }

    loadingPaymentId.value = payment.id
    invoiceError.value = ''
    invoiceResponse.value = null
    showFullResponse.value = false

    try {
        const response = await axios.post(route('payments-wispro.invoices'), {
            invoice_ids: invoiceIds,
        })

        invoiceResponse.value = response.data
        showInvoiceModal.value = true
    } catch (error: any) {
        invoiceError.value = error.response?.data?.error || 'No se pudo obtener la factura.'
        invoiceResponse.value = error.response?.data || null
        showInvoiceModal.value = true
    } finally {
        loadingPaymentId.value = null
    }
}

const openInvoicePdf = (invoiceId: string) => {
    window.open(route('payments-wispro.invoice-pdf', invoiceId), '_blank')
}
</script>
