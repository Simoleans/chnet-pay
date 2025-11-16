<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref,computed } from 'vue';
import ReportPaymentModal from '../components/ReportPaymentModal.vue';
import UserPaymentModal from '../components/UserPaymentModal.vue';
import PaymentDetailsModal from '../components/PaymentDetailsModal.vue';
import PaymentReceiptModal from '../components/PaymentReceiptModal.vue';

// Components
import { Button } from '@/components/ui/button';

import { useBcvStore } from '@/stores/bcv';
import { useBanksStore } from '@/stores/banks';
import { storeToRefs } from 'pinia';

// Props para recibir los pagos del usuario y datos de admin
const props = defineProps({
    user_payments: {
        type: Array,
        default: () => []
    },
    user_contract: {
        type: Object,
        default: () => null
    },
    user_plan: {
        type: Object,
        default: () => null
    },
    total_clients: {
        type: Number,
        default: 0
    },
    contract_stats: {
        type: Object,
        default: () => ({})
    },
    admin_payments: {
        type: Array,
        default: () => []
    }
});

const page = usePage()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Mi CHNET',
        href: '/dashboard',
    },
];



// Usar el store de BCV
const bcvStore = useBcvStore()
const { bcv, date, loading, error } = storeToRefs(bcvStore)

// Usar el store de bancos
const banksStore = useBanksStore()

// Cargar bancos al montar el componente
banksStore.loadBanks()

// Estado para los modales
const showReportPaymentModal = ref(false)
const showUserPaymentModal = ref(false)
const showPaymentDetailsModal = ref(false)
const showReceiptModal = ref(false)
const selectedPayment = ref<any>(null)

// Columnas para la tabla de pagos
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

// Funciones para usuario normal
const copyCode = () => {
    if (page.props.auth.user?.code) {
        navigator.clipboard.writeText(page.props.auth.user.code)
        alert('¡Código de abonado copiado!')
    }
}

const formatDate = (dateString: string | null) => {
    if (!dateString) return 'N/A'
    const date = new Date(dateString)
    return date.toLocaleDateString('es-VE', { year: 'numeric', month: 'long', day: 'numeric' })
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

const getStateLabel = (state: string) => {
    const stateLabels: { [key: string]: string } = {
        'enabled': '✅ Activo',
        'alerted': '⚠️ Alertado',
        'disabled': '❌ Deshabilitado',
        'degraded': '⬇️ Degradado'
    }
    return stateLabels[state] || state
}

const getMapUrl = () => {
    if (!props.user_contract || !props.user_contract.latitude || !props.user_contract.longitude) {
        return ''
    }
    const lat = parseFloat(props.user_contract.latitude)
    const lng = parseFloat(props.user_contract.longitude)
    return `https://www.google.com/maps?q=${lat},${lng}`
}

const reloadBcvRate = async () => {
    await bcvStore.$reloadBcvAmount()
}

// Computed para el link de pago
const paymentLink = computed(() => {
    const baseUrl = window.location.origin
    return `${baseUrl}/pagar/${page.props.auth.user?.code || ''}`
})

// Funciones para link de pago
const copyPaymentLink = () => {
    if (page.props.auth.user?.code) {
        navigator.clipboard.writeText(paymentLink.value)
        alert('¡Link de pago copiado al portapapeles!')
    }
}

const sharePaymentLink = async () => {
    const link = paymentLink.value
    const text = `Paga tu servicio CHNET aquí: ${link}`

    // Verificar si el navegador soporta Web Share API
    if (navigator.share) {
        try {
            await navigator.share({
                title: 'Link de Pago CHNET',
                text: text,
                url: link
            })
        } catch (err: any) {
            // Si el usuario cancela el share, no hacemos nada
            if (err.name !== 'AbortError') {
                console.error('Error al compartir:', err)
                // Fallback: copiar al portapapeles
                navigator.clipboard.writeText(link)
                alert('Link copiado al portapapeles. Puedes compartirlo manualmente.')
            }
        }
    } else {
        // Fallback para navegadores que no soportan Web Share API
        navigator.clipboard.writeText(link)
        alert('Link copiado al portapapeles. Puedes compartirlo manualmente.')
    }
}

// Funciones para manejar los modales
const handleOpenReportModal = () => {
    showReportPaymentModal.value = true;
}

// Funciones para manejar los pagos
const viewPayment = (payment: any) => {
    selectedPayment.value = payment
    showPaymentDetailsModal.value = true
}

const viewReceipt = (payment: any) => {
    if (payment.image_path) {
        selectedPayment.value = payment
        showReceiptModal.value = true
    }
}

// Helper para resolver valores genéricos por clave
const getValue = (item: any, key: string) => {
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
            return item[key]
    }
}

</script>

<template>
    <Head title="Mi CHNET" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Vista para ADMIN (role = 1) -->
            <template v-if="$page.props.auth.user.role === 1">
                <!-- Cards superiores: Tasa BCV + Total Clientes -->
                <div class="grid gap-4 grid-cols-1 sm:grid-cols-2">
                    <!-- Tarjeta Tasa BCV -->
                    <div class="relative min-h-[200px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div class="p-4 h-full flex flex-col justify-between">
                            <div>
                                <h3 class="text-lg font-semibold mb-2">💵 Tasa BCV</h3>
                                <div v-if="loading" class="text-sm text-gray-500">Cargando...</div>
                                <div v-else-if="error" class="text-sm text-red-500">{{ error }}</div>
                                <div v-else class="space-y-2">
                                    <p class="text-3xl font-bold">{{ bcv ? `${bcv} Bs` : 'No disponible' }}</p>
                                    <p class="text-sm text-gray-500">{{ date ? `Fecha: ${date}` : '' }}</p>
                                </div>
                            </div>
                            <div v-if="!loading && !error" class="flex gap-2 mt-4">
                                <Button @click="reloadBcvRate" size="sm" variant="outline" :disabled="loading" class="flex-1">
                                    Actualizar
                                </Button>
                                <Button as="a" href="https://www.bcv.org.ve/" target="_blank" size="sm" variant="outline" class="flex-1">
                                    Verificar
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta Total Clientes -->
                    <div class="relative min-h-[200px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div class="p-4 h-full flex flex-col justify-between">
                            <div>
                                <h3 class="text-lg font-semibold mb-2">👥 Total Clientes</h3>
                                <div class="space-y-2">
                                    <p class="text-3xl font-bold">{{ total_clients }}</p>
                                    <p class="text-sm text-gray-500">Clientes registrados en el sistema local</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cards de Contratos por Estado (4 cards) -->
                <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
                    <!-- Contratos Activos (Enabled) -->
                    <div class="relative min-h-[150px] overflow-hidden rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/20">
                        <div class="p-4">
                            <h3 class="text-sm font-semibold mb-2 text-green-700 dark:text-green-300">✅ Activos</h3>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ contract_stats.enabled || 0 }}</p>
                            <p class="text-xs text-gray-500 mt-1">Contratos habilitados</p>
                        </div>
                    </div>

                    <!-- Contratos Alertados -->
                    <div class="relative min-h-[150px] overflow-hidden rounded-xl border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950/20">
                        <div class="p-4">
                            <h3 class="text-sm font-semibold mb-2 text-yellow-700 dark:text-yellow-300">⚠️ Alertados</h3>
                            <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ contract_stats.alerted || 0 }}</p>
                            <p class="text-xs text-gray-500 mt-1">Contratos con alertas</p>
                        </div>
                    </div>

                    <!-- Contratos Degradados -->
                    <div class="relative min-h-[150px] overflow-hidden rounded-xl border border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-950/20">
                        <div class="p-4">
                            <h3 class="text-sm font-semibold mb-2 text-orange-700 dark:text-orange-300">⬇️ Degradados</h3>
                            <p class="text-3xl font-bold text-orange-600 dark:text-orange-400">{{ contract_stats.degraded || 0 }}</p>
                            <p class="text-xs text-gray-500 mt-1">Contratos degradados</p>
                        </div>
                    </div>

                    <!-- Contratos Deshabilitados -->
                    <div class="relative min-h-[150px] overflow-hidden rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/20">
                        <div class="p-4">
                            <h3 class="text-sm font-semibold mb-2 text-red-700 dark:text-red-300">❌ Deshabilitados</h3>
                            <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ contract_stats.disabled || 0 }}</p>
                            <p class="text-xs text-gray-500 mt-1">Contratos deshabilitados</p>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Pagos para Admin -->
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

                        <div v-if="props.admin_payments && props.admin_payments.length > 0" class="w-full overflow-auto rounded-xl border bg-background shadow-sm">
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
                                        v-for="(item, i) in props.admin_payments"
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

            <!-- Vista para USUARIO NORMAL (role = 0) -->
            <template v-else>
                <!-- Cards superiores: Tasa BCV + Link de Pago -->
                <div class="grid gap-4 grid-cols-1 md:grid-cols-2">
                    <!-- Card Tasa BCV -->
                    <div class="relative min-h-[200px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div class="p-4 h-full flex flex-col justify-between">
                            <div>
                                <h3 class="text-lg font-semibold mb-2">💵 Tasa BCV</h3>
                                <div v-if="loading" class="text-sm text-gray-500">Cargando...</div>
                                <div v-else-if="error" class="text-sm text-red-500">{{ error }}</div>
                                <div v-else class="space-y-2">
                                    <p class="text-3xl font-bold">{{ bcv ? `${bcv} Bs` : 'No disponible' }}</p>
                                    <p class="text-sm text-gray-500">{{ date ? `Fecha: ${date}` : '' }}</p>
                                </div>
                            </div>
                            <div v-if="!loading && !error" class="flex gap-2 mt-4">
                                <Button @click="reloadBcvRate" size="sm" variant="outline" :disabled="loading" class="flex-1">
                                    Actualizar
                                </Button>
                                <Button as="a" href="https://www.bcv.org.ve/" target="_blank" size="sm" variant="outline" class="flex-1">
                                    Verificar
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Card Link de Pago -->
                    <div class="relative min-h-[200px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-gradient-to-br from-purple-50 to-blue-50 dark:from-purple-950/30 dark:to-blue-950/30">
                        <div class="p-4 h-full flex flex-col justify-between">
                            <div>
                                <h3 class="text-lg font-semibold mb-3">🔗 Link de Pago</h3>
                                <div class="text-center">
                                    <!-- QR Code usando API pública -->
                                    <div class="bg-white p-3 rounded-lg inline-block mb-3">
                                        <img
                                            :src="`https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${paymentLink}`"
                                            alt="QR Code"
                                            class="w-[120px] h-[120px]"
                                        />
                                    </div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                        Escanea para pagar
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Button
                                    @click="copyPaymentLink"
                                    variant="outline"
                                    size="sm"
                                    class="w-full"
                                >
                                    📋 Copiar Link
                                </Button>
                                <Button
                                    @click="sharePaymentLink"
                                    variant="default"
                                    size="sm"
                                    class="w-full bg-blue-600 hover:bg-blue-700"
                                >
                                    📤 Compartir
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cards del medio: Mi Contrato + Mi Plan -->
                <div class="grid gap-4 grid-cols-1 md:grid-cols-2">
                    <!-- Card Mi Contrato -->
                    <div class="relative min-h-[250px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div class="p-4 h-full flex flex-col justify-between">
                            <div>
                                <h3 class="text-lg font-semibold mb-3">📋 Mi Contrato</h3>
                                <div v-if="user_contract" class="space-y-3">
                                    <div>
                                        <p class="text-xs text-gray-500">Estado del Servicio</p>
                                        <span :class="[
                                            'px-2 py-1 text-xs rounded font-semibold inline-block mt-1',
                                            user_contract.state === 'enabled' ? 'bg-green-100 text-green-800' :
                                            user_contract.state === 'alerted' ? 'bg-yellow-100 text-yellow-800' :
                                            user_contract.state === 'disabled' ? 'bg-red-100 text-red-800' :
                                            user_contract.state === 'degraded' ? 'bg-orange-100 text-orange-800' :
                                            'bg-gray-100 text-gray-800'
                                        ]">
                                            {{ getStateLabel(user_contract.state) }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Fecha de Inicio</p>
                                        <p class="font-medium">{{ formatDate(user_contract.start_date) }}</p>
                                    </div>
                                    <div v-if="user_contract.latitude && user_contract.longitude">
                                        <Button
                                            as="a"
                                            :href="getMapUrl()"
                                            target="_blank"
                                            variant="outline"
                                            size="sm"
                                            class="w-full flex items-center justify-center gap-2"
                                        >
                                            📍 Ver Ubicación en Maps
                                        </Button>
                                    </div>
                                </div>
                                <div v-else class="text-center py-8 text-gray-500">
                                    <p>No hay contrato disponible</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Mi Plan -->
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
                                    <!-- Plan y Precio -->
                                    <div v-if="user_plan" class="space-y-2">
                                        <div>
                                            <p class="text-xs text-gray-500">Plan Contratado</p>
                                            <p class="font-medium text-lg">{{ user_plan.name }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Precio</p>
                                            <p class="font-medium text-xl text-green-600">
                                                ${{ formatPrice(user_plan.price) }} USD
                                            </p>
                                            <p v-if="bcv" class="font-medium text-md text-blue-600">
                                                Bs. {{ formatPriceBs(user_plan.price) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div v-else class="text-gray-500">
                                        <p>No hay plan asignado</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de pagos para usuarios con role = 0 -->
                <div class="flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div class="p-4">
                        <div class="mb-4">
                            <h2 class="text-xl font-semibold">💳 Mis Pagos</h2>
                        </div>

                    <!-- Tabla responsive -->
                    <div v-if="props.user_payments && props.user_payments.length > 0" class="w-full overflow-auto rounded-xl border bg-background shadow-sm">
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
                                    v-for="(item, i) in props.user_payments"
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

                    <!-- Mensaje cuando no hay pagos -->
                    <div v-else class="text-center py-12 text-muted-foreground">
                        <div class="flex flex-col items-center gap-2">
                            <div class="text-4xl">💳</div>
                            <p class="text-lg font-medium">No tienes pagos registrados</p>
                            <p class="text-sm">Tus pagos aparecerán aquí una vez que realices uno</p>
                        </div>
                    </div>
                </div>
            </div>
            </template>
        </div>

        <!-- Modal de pago del usuario -->
        <UserPaymentModal
            v-model:open="showUserPaymentModal"
            @openReportModal="handleOpenReportModal"
        />

        <!-- Modal para reportar pago -->
        <ReportPaymentModal
            v-model:open="showReportPaymentModal"
            :plan-price="bcv && $page.props.auth.user?.plan?.price ?
                (parseFloat($page.props.auth.user.plan.price) * parseFloat(bcv)).toFixed(2) :
                '0'"
            :user-id="$page.props.auth.user?.id"
        />

        <!-- Modales para pagos -->
        <PaymentDetailsModal
            v-if="selectedPayment"
            v-model:open="showPaymentDetailsModal"
            :payment="selectedPayment"
            :showAdminActions="false"
        />

        <PaymentReceiptModal
            v-if="selectedPayment"
            v-model:open="showReceiptModal"
            :payment="selectedPayment"
        />
    </AppLayout>
</template>
