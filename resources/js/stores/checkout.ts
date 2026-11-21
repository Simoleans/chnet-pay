import { defineStore } from 'pinia'
import { ref } from 'vue'

interface InvoiceItem {
    id: number | string
    invoice_number?: string
    amount: number
}

// UI: se guarda en sessionStorage para que, si el usuario recarga /pagos/bdv/checkout,
// no lo devuelva al dashboard. Mismos datos que antes; se borra con clear().
const STORAGE_KEY = 'chnet:checkout'
const readSaved = () => {
    try {
        return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || 'null')
    } catch {
        return null
    }
}

export const useCheckoutStore = defineStore('checkout', () => {
    const saved = readSaved()
    const amountUsd  = ref<number>(saved?.amountUsd ?? 0)
    const invoiceIds = ref<string[]>(saved?.invoiceIds ?? [])
    const invoices   = ref<InvoiceItem[]>(saved?.invoices ?? [])
    const clientId   = ref<string>(saved?.clientId ?? '')

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
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ ...data, clientId: clientId.value }))
        } catch { /* sin storage: sigue funcionando en memoria */ }
    }

    const clear = () => {
        amountUsd.value  = 0
        invoiceIds.value = []
        invoices.value   = []
        clientId.value   = ''
        try { sessionStorage.removeItem(STORAGE_KEY) } catch { /* noop */ }
    }

    return { amountUsd, invoiceIds, invoices, clientId, set, clear }
})
