<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import ReportPaymentModal from '../components/ReportPaymentModal.vue';
import UserPaymentModal from '../components/UserPaymentModal.vue';
import PaymentDetailsModal from '../components/PaymentDetailsModal.vue';
import PaymentReceiptModal from '../components/PaymentReceiptModal.vue';
import { useForm } from '@inertiajs/vue3';

// Components
import { Button } from '@/components/ui/button';

import { useBcvStore } from '@/stores/bcv';
import { useBanksStore } from '@/stores/banks';
import { storeToRefs } from 'pinia';

// Props para recibir los pagos del usuario
const props = defineProps({
    user_payments: {
        type: Array,
        default: () => []
    }
});


const form = useForm({
    client: '',
});

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



const reloadBcvRate = async () => {
    await bcvStore.$reloadBcvAmount()
}

// Funciones para manejar los modales
const openUserPaymentModal = () => {
    showUserPaymentModal.value = true;
}

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
            <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Tarjeta Tasa BCV -->
                <div class="relative min-h-[200px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div class="p-4 h-full flex flex-col justify-between">
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Tasa BCV</h3>
                            <div v-if="loading" class="text-sm text-gray-500">Cargando...</div>
                            <div v-else-if="error" class="text-sm text-red-500">{{ error }}</div>
                            <div v-else class="space-y-2">
                                <p class="text-2xl font-bold">{{ bcv ? `${bcv} Bs` : 'No disponible' }}</p>
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

                <!-- Tarjeta Mi Plan -->
                <div class="relative min-h-[200px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern v-if="!$page.props.auth.user?.plan_id" />
                    <div v-else class="p-4 h-full flex flex-col justify-between">
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Mi Plan</h3>
                            <div class="space-y-2">
                                <p class="text-2xl font-bold">{{ $page.props.auth.user.plan.name }}</p>
                                <div class="space-y-1">
                                    <p class="text-sm text-gray-500">
                                        <span class="font-medium">Velocidad:</span>
                                        {{ $page.props.auth.user.plan.mbps ? `${$page.props.auth.user.plan.mbps} Mbps` : '-' }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <span class="font-medium">Precio:</span>
                                        ${{ $page.props.auth.user.plan.price }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <span class="font-medium">Tipo:</span>
                                        {{ $page.props.auth.user.plan.type }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4" v-if="$page.props.auth.user.plan_id && bcv && $page.props.auth.user.due > 0">
                            <div class="flex gap-2">
                                <Button
                                    @click="openUserPaymentModal"
                                    class="flex-1"
                                    size="sm"
                                >
                                    Pagar Plan
                                </Button>

                                <Button
                                    @click="handleOpenReportModal"
                                    class="flex-1"
                                    size="sm"
                                    variant="outline"
                                >
                                    Reportar pago
                                </Button>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Tercera Tarjeta -->
                <div class="relative min-h-[200px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <!-- <PlaceholderPattern /> -->
                    <div class="p-4">
                        <h3 class="text-lg font-semibold mb-2">Mi Abonado CHNET</h3>
                        <div class="space-y-2">
                            <p class="text-2xl font-bold">{{ $page.props.auth.user.code }}</p>
                            <div class="space-y-1">
                                <p class="text-sm text-gray-500">
                                    <span class="font-medium">Zona:</span>
                                    {{ $page.props.auth.user.zone.name }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    <span class="font-medium">Dirección:</span>
                                    {{ $page.props.auth.user.address }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Tabla de pagos para usuarios con role = 0 -->
            <div v-if="$page.props.auth.user.role === 0" class="flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="p-4">
                    <div class="mb-4">
                        <h2 class="text-xl font-semibold">Mis Pagos Recientes</h2>
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
            <!-- Espacio para administradores -->
            <div v-else class="relative min-h-[300px] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <PlaceholderPattern />
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center text-muted-foreground">
                        <p class="text-lg">Panel de Administrador</p>
                        <p class="text-sm">Utiliza el menú lateral para acceder a las opciones de administración</p>
                    </div>
                </div>
            </div>
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
