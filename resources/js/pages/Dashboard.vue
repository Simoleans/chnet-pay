<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

// Modals
import ReportPaymentModal from '../components/ReportPaymentModal.vue';
import UserPaymentBankSelector from '../components/UserPaymentBankSelector.vue';
import UserPaymentModal from '../components/UserPaymentModal.vue';
import PaymentDetailsModal from '../components/PaymentDetailsModal.vue';
import PaymentReceiptModal from '../components/PaymentReceiptModal.vue';

// Admin Components
import BcvRateCard from '../components/Dashboard/Admin/BcvRateCard.vue';
import TotalClientsCard from '../components/Dashboard/Admin/TotalClientsCard.vue';
import ContractStatsCards from '../components/Dashboard/Admin/ContractStatsCards.vue';
import RecentPaymentsTable from '../components/Dashboard/Admin/RecentPaymentsTable.vue';

// User Components
import PaymentLinkCard from '../components/Dashboard/User/PaymentLinkCard.vue';
import ContractCard from '../components/Dashboard/User/ContractCard.vue';
import PlanCard from '../components/Dashboard/User/PlanCard.vue';
import UserPaymentsTable from '../components/Dashboard/User/UserPaymentsTable.vue';
import WisproInvoicesTable from './User/Components/WisproInvoicesTable.vue';

// UI Components
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

import { useBanksStore } from '@/stores/banks';

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
    user_invoices: {
        type: Array,
        default: () => []
    },
    pending_invoices_count: {
        type: Number,
        default: 0
    },
    show_pending_invoice_alert: {
        type: Boolean,
        default: false
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

// Usar el store de bancos
const banksStore = useBanksStore()

// Cargar bancos al montar el componente
banksStore.loadBanks()

// Estado para los modales
const showReportPaymentModal = ref(false)
const showBankSelector = ref(false)
const showUserPaymentModal = ref(false)
const showPaymentDetailsModal = ref(false)
const showReceiptModal = ref(false)
const showPendingInvoiceAlert = ref(props.show_pending_invoice_alert || false)
const selectedPayment = ref<any>(null)
const selectedInvoice = ref<any>(null)

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

const openPaymentModal = (invoice?: any) => {
    selectedInvoice.value = invoice || null
    showBankSelector.value = true
}

const handleBankSelected = (bank: 'bnc' | 'bdv') => {
    if (bank === 'bnc') {
        showUserPaymentModal.value = true
    }
    // BDV: pendiente de implementar
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
                    <BcvRateCard />
                    <TotalClientsCard :total-clients="total_clients" />
                </div>

                <!-- Cards de Contratos por Estado (4 cards) -->
                <ContractStatsCards :contract-stats="contract_stats" />

                <!-- Tabla de Pagos para Admin -->
                <RecentPaymentsTable
                    :payments="admin_payments"
                    @view-payment="viewPayment"
                    @view-receipt="viewReceipt"
                />
            </template>

            <!-- Vista para USUARIO NORMAL (role = 0) -->
            <template v-else>
                <!-- Cards superiores: Tasa BCV + Link de Pago -->
                <div class="grid gap-4 grid-cols-1 md:grid-cols-2">
                    <BcvRateCard />
                    <PaymentLinkCard />
                </div>

                <!-- Cards del medio: Mi Contrato + Mi Plan -->
                <div class="grid gap-4 grid-cols-1 md:grid-cols-2">
                    <ContractCard :user-contract="user_contract" />
                    <PlanCard
                        :user-plan="user_plan"
                        :pending-invoices-count="pending_invoices_count"
                    />
                </div>

                <!-- Tabla de Facturas de Wispro -->
                <div v-if="user_invoices && user_invoices.length > 0" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div class="p-4">
                        <WisproInvoicesTable
                            :invoices="user_invoices"
                            @open-payment-modal="openPaymentModal"
                        />
                    </div>
                </div>

                <!-- Tabla de pagos para usuarios con role = 0 -->
                <UserPaymentsTable
                    :payments="user_payments"
                    @view-payment="viewPayment"
                    @view-receipt="viewReceipt"
                />
            </template>
        </div>

        <!-- Selector de banco -->
        <UserPaymentBankSelector
            v-model:open="showBankSelector"
            @select-bank="handleBankSelected"
        />

        <!-- Modal de pago del usuario (BNC) -->
        <UserPaymentModal
            v-model:open="showUserPaymentModal"
            :user-plan="user_plan"
            :selected-invoice="selectedInvoice"
            @openReportModal="handleOpenReportModal"
        />

        <!-- Modal para reportar pago -->
        <ReportPaymentModal
            v-model:open="showReportPaymentModal"
            :plan-price="user_plan?.price ? String(user_plan.price) : '0'"
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

        <!-- Modal de Alerta de Facturas Pendientes -->
        <Dialog v-model:open="showPendingInvoiceAlert">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-yellow-600">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        ¡Tienes facturas pendientes!
                    </DialogTitle>
                    <DialogDescription>
                        <div class="space-y-3 mt-4">
                            <div class="bg-yellow-50 dark:bg-yellow-950/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">
                                    Tienes <span class="font-bold text-yellow-700 dark:text-yellow-400">{{ pending_invoices_count }}</span>
                                    {{ pending_invoices_count === 1 ? 'factura pendiente' : 'facturas pendientes' }} de pago.
                                </p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    Por favor, realiza el pago lo antes posible para evitar la suspensión del servicio.
                                </p>
                            </div>

                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <p class="font-semibold mb-2">📋 Puedes ver los detalles de tus facturas más abajo en el dashboard.</p>
                                <ul class="list-disc list-inside space-y-1 text-xs">
                                    <li>Revisa las fechas de vencimiento</li>
                                    <li>Verifica los montos a pagar</li>
                                    <li>Usa el link de pago para realizar tu pago</li>
                                </ul>
                            </div>
                        </div>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button @click="showPendingInvoiceAlert = false" class="w-full bg-yellow-600 hover:bg-yellow-700">
                        Entendido
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
