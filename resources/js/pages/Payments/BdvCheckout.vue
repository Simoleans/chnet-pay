<template>
    <AppLayout>
        <Head title="Pago BioPago BDV" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-lg mx-auto">

            <!-- Cabecera -->
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 dark:bg-red-950/30 overflow-hidden shrink-0">
                    <img src="/img/bdv.webp" alt="BDV" class="h-10 w-10 object-contain" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold">BioPago BDV</h1>
                    <p class="text-sm text-muted-foreground">Punto de venta digital — IPG2</p>
                </div>
            </div>

            <!-- Resumen de facturas -->
            <div v-if="checkout.amountUsd > 0" class="rounded-lg border-2 border-red-200 dark:border-red-800 bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-950/20 dark:to-rose-950/20 overflow-hidden">

                <!-- Monto total -->
                <div class="p-4 text-center border-b border-red-200 dark:border-red-800">
                    <p class="text-xs font-medium text-muted-foreground mb-1">Total a pagar</p>
                    <div v-if="amountBs">
                        <p class="text-4xl font-bold text-red-600 dark:text-red-400">{{ amountBs }} Bs</p>
                        <p class="text-xs text-muted-foreground mt-1">
                            ${{ checkout.amountUsd.toFixed(2) }} USD × {{ bcv }} Bs/USD
                        </p>
                    </div>
                    <div v-else>
                        <p class="text-lg text-muted-foreground">Calculando monto...</p>
                        <p class="text-xs text-muted-foreground">Cargando tasa BCV</p>
                    </div>
                </div>

                <!-- Detalle por factura -->
                <!-- <div v-if="checkout.invoices.length > 0" class="divide-y divide-red-100 dark:divide-red-900">
                    <div
                        v-for="inv in checkout.invoices"
                        :key="inv.id"
                        class="flex items-center justify-between px-4 py-2.5 text-sm"
                    >
                        <span class="text-muted-foreground">
                            Factura <span class="font-medium text-foreground">#{{ inv.invoice_number ?? inv.id }}</span>
                        </span>
                        <div class="text-right">
                            <span class="font-semibold text-foreground">${{ inv.amount.toFixed(2) }}</span>
                            <span v-if="bcv" class="block text-xs text-muted-foreground">
                                Bs {{ (inv.amount * parseFloat(String(bcv))).toFixed(2) }}
                            </span>
                        </div>
                    </div>
                </div> -->

                <!-- Solo IDs si no hay detalle -->
                <!-- <div v-else-if="checkout.invoiceIds.length > 0" class="px-4 py-2.5 text-sm text-muted-foreground">
                    {{ checkout.invoiceIds.length === 1 ? 'Factura' : `${checkout.invoiceIds.length} facturas` }}:
                    <span class="font-medium text-foreground">#{{ checkout.invoiceIds.join(', #') }}</span>
                </div> -->
            </div>

            <!-- Error global del servidor -->
            <div v-if="form.errors.bdv" class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                {{ form.errors.bdv }}
            </div>

            <!-- Formulario -->
            <form @submit.prevent="submit" class="flex flex-col gap-5">

                <!-- Cédula -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium">Cédula</label>
                    <div class="flex gap-2">
                        <select
                            v-model="form.idLetter"
                            class="w-20 rounded-md border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                            :class="{ 'border-destructive': form.errors.idLetter }"
                        >
                            <option value="V">V</option>
                            <option value="E">E</option>
                            <option value="J">J</option>
                        </select>
                        <input
                            v-model="form.idNumber"
                            type="text"
                            inputmode="numeric"
                            placeholder="Número de cédula"
                            maxlength="9"
                            class="flex-1 rounded-md border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                            :class="{ 'border-destructive': form.errors.idNumber }"
                        />
                    </div>
                    <p v-if="form.errors.idLetter" class="text-xs text-destructive">{{ form.errors.idLetter }}</p>
                    <p v-if="form.errors.idNumber" class="text-xs text-destructive">{{ form.errors.idNumber }}</p>
                </div>

                <!-- Correo -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium">Correo electrónico</label>
                    <input
                        v-model="form.email"
                        type="email"
                        placeholder="ejemplo@correo.com"
                        class="rounded-md border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                        :class="{ 'border-destructive': form.errors.email }"
                    />
                    <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                </div>

                <!-- Teléfono -->
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium">Teléfono celular</label>
                    <input
                        v-model="form.cellphone"
                        type="text"
                        inputmode="numeric"
                        placeholder="04XX0000000"
                        maxlength="11"
                        class="rounded-md border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                        :class="{ 'border-destructive': form.errors.cellphone }"
                    />
                    <p class="text-xs text-muted-foreground">11 dígitos, ej: 04121234567</p>
                    <p v-if="form.errors.cellphone" class="text-xs text-destructive">{{ form.errors.cellphone }}</p>
                </div>

                <!-- Monto manual (solo si no viene de facturas) -->
                <div v-if="checkout.amountUsd <= 0" class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium">Monto (Bs)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">Bs</span>
                        <input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            class="w-full rounded-md border bg-background pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                            :class="{ 'border-destructive': form.errors.amount }"
                        />
                    </div>
                    <p v-if="form.errors.amount" class="text-xs text-destructive">{{ form.errors.amount }}</p>
                </div>

                <!-- Botón -->
                <button
                    type="submit"
                    :disabled="form.processing || (checkout.amountUsd > 0 && !amountBs)"
                    class="w-full flex items-center justify-center gap-3 rounded-xl bg-primary text-primary-foreground px-4 py-3 text-sm font-semibold transition-all hover:bg-primary/90 disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <img v-else src="/img/bdv.webp" alt="BDV" class="h-5 w-5 object-contain" />
                    {{ form.processing ? 'Iniciando pago...' : 'Pagar con BioPago BDV' }}
                </button>

            </form>

            <p class="text-xs text-center text-muted-foreground">
                Serás redirigido al portal seguro del Banco de Venezuela para completar el pago.
            </p>

        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useBcvStore } from '@/stores/bcv'
import { useCheckoutStore } from '@/stores/checkout'
import { storeToRefs } from 'pinia'
import axios from 'axios'

const bcvStore  = useBcvStore()
const { bcv }   = storeToRefs(bcvStore)

const checkout  = useCheckoutStore()

const amountBs = computed(() => {
    if (!bcv.value || checkout.amountUsd <= 0) return ''
    return (checkout.amountUsd * parseFloat(String(bcv.value))).toFixed(2)
})

const page = usePage()
const user = page.props.auth?.user as any

const form = useForm({
    idLetter:    'V' as 'V' | 'E' | 'J',
    idNumber:    '',
    email:       user?.email ?? '',
    cellphone:   '',
    amount:      '',
    invoice_ids: checkout.invoiceIds,
})

const submit = async () => {
    if (checkout.amountUsd > 0 && amountBs.value) {
        form.amount = amountBs.value
    }
    form.invoice_ids = checkout.invoiceIds

    form.processing = true
    form.clearErrors()

    try {
        const { data } = await axios.post(route('bdv.ipg2.start'), form.data())
        window.location.href = data.urlPayment
    } catch (error) {
        if (error.response?.status === 422) {
            form.errors = error.response.data.errors
        } else {
            form.errors = { bdv: error.response?.data?.message ?? 'Error al conectar con BDV' }
        }
    } finally {
        form.processing = false
    }
}
</script>
