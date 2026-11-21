<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pago BioPago BDV" />

        <div class="relative mx-auto flex w-full max-w-lg flex-1 flex-col gap-6 p-4 pb-32 sm:pb-8">
            <!-- Capa de espera mientras se crea el pago en el BDV -->
            <div
                v-if="form.processing"
                class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 bg-background/95 px-8 text-center backdrop-blur-sm"
                aria-live="polite"
            >
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-foreground text-background">
                    <Loader2 class="h-8 w-8 animate-spin" />
                </span>
                <div>
                    <p class="text-lg font-bold">Conectando con el Banco de Venezuela</p>
                    <p class="mt-1 text-[15px] text-muted-foreground">En unos segundos te llevaremos a su página segura. No cierres esta pantalla.</p>
                </div>
            </div>

            <!-- Cabecera -->
            <div class="flex items-center gap-3">
                <Link
                    :href="route('dashboard')"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-muted transition hover:bg-muted/70"
                    aria-label="Volver"
                >
                    <ArrowLeft class="h-5 w-5" />
                </Link>
                <div class="min-w-0 flex-1">
                    <h1 class="text-xl leading-tight font-bold">Botón de pago BDV</h1>
                    <p class="text-[13px] text-muted-foreground">Paga con tu tarjeta en la página segura del banco</p>
                </div>
                <img src="/img/bdv.webp" alt="BDV" class="h-10 w-10 shrink-0 rounded-full bg-white object-contain p-1 ring-1 ring-border" />
            </div>

            <!-- Monto -->
            <AmountHero
                v-if="checkout.amountUsd > 0"
                :amount-bs="amountBs"
                :amount-usd="checkout.amountUsd"
                :rate="bcv"
                :label="checkout.invoiceIds.length > 1 ? 'Total de tus facturas' : 'Total a pagar'"
                :invoices-count="checkout.invoiceIds.length"
            />

            <!-- Cómo funciona: reduce la incertidumbre del redireccionamiento -->
            <ol class="grid grid-cols-3 gap-2 text-center">
                <li v-for="(s, i) in ['Completa tus datos', 'Paga en la página del BDV', 'Vuelves aquí con tu pago listo']" :key="i" class="rounded-xl bg-muted/60 px-2 py-3">
                    <span class="mx-auto mb-1.5 flex h-7 w-7 items-center justify-center rounded-full bg-foreground text-[13px] font-bold text-background">{{ i + 1 }}</span>
                    <span class="block text-[13px] leading-tight font-medium">{{ s }}</span>
                </li>
            </ol>

            <!-- Error general del servidor (queda fijo) -->
            <div
                v-if="errorOf('bdv')"
                role="alert"
                class="rounded-2xl border-2 border-red-300 bg-red-50 p-4 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100"
            >
                <p class="text-[15px] font-bold">No pudimos iniciar el pago</p>
                <p class="mt-0.5 text-[15px]">{{ errorOf('bdv') }}</p>
                <p class="mt-1 text-[13px] opacity-80">Revisa tus datos e inténtalo de nuevo.</p>
            </div>

            <!-- Formulario -->
            <form id="bdv-checkout-form" class="flex flex-col gap-5" @submit.prevent="submit">
                <!-- ¿Quién paga? (mismo campo form.payerType) -->
                <fieldset class="space-y-2">
                    <legend class="mb-2 text-[15px] font-semibold">¿Quién paga?</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" :class="segClass(form.payerType === 'natural')" @click="form.payerType = 'natural'">
                            <User class="h-4 w-4" /> Persona
                        </button>
                        <button type="button" :class="segClass(form.payerType === 'juridica')" @click="form.payerType = 'juridica'">
                            <Building2 class="h-4 w-4" /> Empresa
                        </button>
                    </div>
                </fieldset>

                <!-- RIF empresa (solo jurídica) -->
                <div v-if="form.payerType === 'juridica'" class="space-y-1.5 rounded-2xl border bg-muted/30 p-4">
                    <label for="rifNumber" class="text-[15px] font-semibold">RIF de la empresa</label>
                    <div class="flex gap-2">
                        <!-- El backend solo acepta "J" -->
                        <span class="flex h-12 w-14 shrink-0 items-center justify-center rounded-xl border border-input bg-background text-[15px] font-semibold">J</span>
                        <input
                            id="rifNumber"
                            v-model="form.rifNumber"
                            type="text"
                            inputmode="numeric"
                            placeholder="Número de RIF"
                            :class="[inputClass('rifNumber'), 'min-w-0 flex-1 tabular-nums']"
                            @input="onRifNumberInput"
                        />
                    </div>
                    <p v-if="errorOf('rifLetter')" class="text-[13px] font-medium text-destructive">{{ errorOf('rifLetter') }}</p>
                    <p v-if="errorOf('rifNumber')" class="text-[13px] font-medium text-destructive">{{ errorOf('rifNumber') }}</p>
                </div>

                <!-- Cédula -->
                <div class="space-y-1.5">
                    <label for="idNumber" class="text-[15px] font-semibold">
                        {{ form.payerType === 'juridica' ? 'Cédula del representante' : 'Tu cédula' }}
                    </label>
                    <div class="flex gap-2">
                        <div class="grid w-[6.5rem] shrink-0 grid-cols-2 gap-1 rounded-xl border border-input bg-background p-1">
                            <button
                                v-for="l in ['V', 'E']"
                                :key="l"
                                type="button"
                                :class="['rounded-lg text-[15px] font-semibold transition', form.idLetter === l ? 'bg-foreground text-background' : 'hover:bg-muted']"
                                @click="form.idLetter = l as 'V' | 'E'"
                            >
                                {{ l }}
                            </button>
                        </div>
                        <input
                            id="idNumber"
                            v-model="form.idNumber"
                            type="text"
                            inputmode="numeric"
                            placeholder="12345678"
                            :class="[inputClass('idNumber'), 'min-w-0 flex-1 tabular-nums']"
                            @input="onIdNumberInput"
                        />
                    </div>
                    <p v-if="form.payerType === 'juridica'" class="text-[13px] text-muted-foreground">La persona que hace el pago ante el banco.</p>
                    <p v-if="errorOf('idLetter')" class="text-[13px] font-medium text-destructive">{{ errorOf('idLetter') }}</p>
                    <p v-if="errorOf('idNumber')" class="text-[13px] font-medium text-destructive">{{ errorOf('idNumber') }}</p>
                </div>

                <!-- Correo -->
                <div class="space-y-1.5">
                    <label for="email" class="text-[15px] font-semibold">Correo electrónico</label>
                    <input id="email" v-model="form.email" type="email" autocomplete="email" placeholder="ejemplo@correo.com" :class="inputClass('email')" />
                    <p class="text-[13px] text-muted-foreground">El banco te enviará el comprobante a este correo.</p>
                    <p v-if="errorOf('email')" class="text-[13px] font-medium text-destructive">{{ errorOf('email') }}</p>
                </div>

                <!-- Teléfono -->
                <div class="space-y-1.5">
                    <label for="cellphone" class="text-[15px] font-semibold">Teléfono celular</label>
                    <input
                        id="cellphone"
                        v-model="form.cellphone"
                        type="tel"
                        inputmode="numeric"
                        autocomplete="tel"
                        placeholder="04121234567"
                        :class="[inputClass('cellphone'), 'tabular-nums']"
                        @input="onCellphoneInput"
                    />
                    <p class="text-[13px] text-muted-foreground">11 números, empezando por 04. Ej: 04121234567</p>
                    <p v-if="errorOf('cellphone')" class="text-[13px] font-medium text-destructive">{{ errorOf('cellphone') }}</p>
                </div>

                <!-- Monto manual (solo si no viene de facturas) -->
                <div v-if="checkout.amountUsd <= 0" class="space-y-1.5">
                    <label for="amount" class="text-[15px] font-semibold">Monto (Bs)</label>
                    <div class="relative">
                        <input
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            :class="[inputClass('amount'), 'pr-12']"
                        />
                        <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-sm font-semibold text-muted-foreground">Bs</span>
                    </div>
                    <p v-if="errorOf('amount')" class="text-[13px] font-medium text-destructive">{{ errorOf('amount') }}</p>
                </div>
            </form>

            <p class="flex items-center justify-center gap-1.5 text-center text-[13px] text-muted-foreground">
                <Lock class="h-3.5 w-3.5" />
                Tus datos de tarjeta se ingresan solo en la página del Banco de Venezuela.
            </p>

            <!-- Botón: fijo abajo en móvil, normal en escritorio -->
            <div
                class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))] backdrop-blur sm:static sm:border-0 sm:bg-transparent sm:p-0"
            >
                <button
                    type="submit"
                    form="bdv-checkout-form"
                    :disabled="form.processing || (checkout.amountUsd > 0 && !amountBs)"
                    class="mx-auto flex h-14 w-full max-w-lg items-center justify-center gap-2 rounded-2xl bg-primary px-4 text-base font-bold text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <Lock class="h-5 w-5" />
                    Ir a pagar al BDV
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted } from 'vue'
import { useBcvStore } from '@/stores/bcv'
import { useCheckoutStore } from '@/stores/checkout'
import { storeToRefs } from 'pinia'
import axios from 'axios'
// ── Solo UI ──
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, Building2, Loader2, Lock, User } from 'lucide-vue-next'
import AmountHero from '@/components/payment/AmountHero.vue'

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
    payerType:   'natural' as 'natural' | 'juridica',
    idLetter:    'V' as 'V' | 'E',
    idNumber:    '',
    rifLetter:   'J' as 'J' | 'G' | 'E' | 'V' | 'C' | 'P',
    rifNumber:   '',
    email:       user?.email ?? '',
    cellphone:   '',
    amount:      '',
    invoice_ids: checkout.invoiceIds,
})

const onIdNumberInput = () => {
    form.idNumber = form.idNumber.replace(/\D/g, '').slice(0, 9)
}

const onRifNumberInput = () => {
    form.rifNumber = form.rifNumber.replace(/\D/g, '').slice(0, 10)
}

onMounted(() => {
    if (!checkout.amountUsd || checkout.amountUsd <= 0) {
        router.visit(route('dashboard'))
    }
})

const submit = async () => {
    if (checkout.amountUsd > 0 && amountBs.value) {
        form.amount = amountBs.value
    }
    form.invoice_ids = checkout.invoiceIds

    form.processing = true
    form.clearErrors()

    try {
        const payload = { ...form.data() }
        delete (payload as { payerType?: string }).payerType
        if (form.payerType !== 'juridica') {
            delete (payload as { rifLetter?: string }).rifLetter
            delete (payload as { rifNumber?: string }).rifNumber
        }

        const { data } = await axios.post(route('bdv.ipg2.start'), payload)
        //new window
        //window.open(data.urlPayment, '_blank')
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

// ── Ayudas solo de interfaz (no cambian el payload) ─────────────────────────
// Nota: los campos numéricos NO usan maxlength: el navegador cortaba ANTES de limpiar
// ("0412-765-4321" quedaba en 9 dígitos). Ahora se limpia y luego se recorta.
// Teléfono: solo dígitos, máximo 11 (mismo formato 04XXXXXXXXX que valida el backend)
const onCellphoneInput = () => {
    form.cellphone = form.cellphone.replace(/\D/g, '').slice(0, 11)
}

const errorOf = (field: string) => (form.errors as Record<string, string | undefined>)[field]

const inputClass = (field?: string) => [
    'h-12 w-full rounded-xl border bg-background px-3 text-[15px] outline-none transition focus:border-foreground focus:ring-2 focus:ring-foreground/10',
    field && errorOf(field) ? 'border-destructive' : 'border-input',
]

const segClass = (active: boolean) => [
    'flex h-12 items-center justify-center gap-2 rounded-xl border-2 text-[15px] font-semibold transition',
    active ? 'border-foreground bg-foreground text-background' : 'border-input bg-background hover:bg-muted',
]

const breadcrumbs = [
    { title: 'Mi CHNET', href: '/dashboard' },
    { title: 'Botón de pago BDV', href: '#' },
]
</script>
