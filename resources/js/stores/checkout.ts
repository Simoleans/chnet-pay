import { defineStore } from 'pinia'
import { ref } from 'vue'

interface InvoiceItem {
    id: number | string
    invoice_number?: string
    amount: number
}

export const useCheckoutStore = defineStore('checkout', () => {
    const amountUsd  = ref(0)
    const invoiceIds = ref<string[]>([])
    const invoices   = ref<InvoiceItem[]>([])
    const clientId   = ref('')

    const set = (data: {
        amountUsd:  number
        invoiceIds: string[]
        invoices:   InvoiceItem[]
        clientId?:  string
    }) => {
        amountUsd.value  = data.amountUsd
        invoiceIds.value = data.invoiceIds
        invoices.value   = data.invoices
        clientId.value   = data.clientId ?? ''
    }

    const clear = () => {
        amountUsd.value  = 0
        invoiceIds.value = []
        invoices.value   = []
        clientId.value   = ''
    }

    return { amountUsd, invoiceIds, invoices, clientId, set, clear }
})
