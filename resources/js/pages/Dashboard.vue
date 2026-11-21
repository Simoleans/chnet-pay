<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { useCheckoutStore } from '@/stores/checkout';
import { useNotifications } from '@/composables/useNotifications';

// Modals
import ReportPaymentModal from '../components/ReportPaymentModal.vue';
import UserPaymentBankSelector from '../components/UserPaymentBankSelector.vue';
import UserPaymentModal from '../components/UserPaymentModal.vue';
import UserPaymentBdvModal from '../components/UserPaymentBdvModal.vue';
import PaymentDetailsModal from '../components/PaymentDetailsModal.vue';
import PaymentReceiptModal from '../components/PaymentReceiptModal.vue';

// Admin Components
import BcvRateCard from '../components/Dashboard/Admin/BcvRateCard.vue';
import TotalClientsCard from '../components/Dashboard/Admin/TotalClientsCard.vue';
import ContractStatsCards from '../components/Dashboard/Admin/ContractStatsCards.vue';
import RecentPaymentsTable from '../components/Dashboard/Admin/RecentPaymentsTable.vue';

// User Components
// import PaymentLinkCard from '../components/Dashboard/User/PaymentLinkCard.vue';
// import ContractCard from '../components/Dashboard/User/ContractCard.vue'; // reemplazado por AccountInfo
// import PlanCard from '../components/Dashboard/User/PlanCard.vue'; // reemplazado por AccountInfo
import UserPaymentsTable from '../components/Dashboard/User/UserPaymentsTable.vue';
// Vista del cliente (nuevo orden): saldo → facturas → pagos | resumen de cuenta
import BalanceHero from '../components/Dashboard/User/BalanceHero.vue';
import InvoiceList from '../components/Dashboard/User/InvoiceList.vue';
import AccountInfo from '../components/Dashboard/User/AccountInfo.vue';

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
    },
    bdv_ipg2_payments: {
        type: Object,
        default: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        })
    },
    total_amount_plan: {
        type: Number,
        default: 0
    }
});

const page = usePage()

type InvoicingFirmIds = {
    empresa_1?: string;
    empresa_2?: string;
    empresa_3?: string;
    empresa_4?: string;
};

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
const showBdvPaymentModal  = ref(false)
const showPaymentDetailsModal = ref(false)
const showReceiptModal = ref(false)
const showPendingInvoiceAlert = ref(props.show_pending_invoice_alert || false)
const selectedPayment = ref<any>(null)
const selectedInvoice = ref<any>(null)

const selectedInvoiceFirmId = computed(() => {
    if (selectedInvoice.value?.invoicing_firm_id) {
        return String(selectedInvoice.value.invoicing_firm_id);
    }

    const firmIds = Array.from(
        new Set(
            (selectedInvoice.value?.invoices || [])
                .map((invoice: any) => invoice.invoicing_firm_id)
                .filter(Boolean)
                .map(String)
        )
    );

    return firmIds.length === 1 ? firmIds[0] : null;
});

const isBdvPaymentDisabled = computed(() => {
    const firmIds = (page.props.invoicingFirmIds as InvoicingFirmIds | undefined) || {};
    const empresa2FirmId = firmIds.empresa_2;
    const empresa3FirmId = firmIds.empresa_3;
    const empresa4FirmId = firmIds.empresa_4;

    return Boolean(empresa2FirmId && selectedInvoiceFirmId.value === String(empresa2FirmId)) || Boolean(empresa3FirmId && selectedInvoiceFirmId.value === String(empresa3FirmId)) || Boolean(empresa4FirmId && selectedInvoiceFirmId.value === String(empresa4FirmId))
    ;
});

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

const checkoutStore = useCheckoutStore()
const { notify }    = useNotifications()

// Mostrar notificación flash de retorno BioPago BDV
onMounted(() => {
    const flash = page.props.flash as any
    if (flash?.type && flash?.message) {
        notify({
            type:     flash.type,
            message:  flash.message,
            duration: flash.type === 'success' ? 6000 : 5000,
        })
    }
})

const openPaymentModal = (invoice?: any) => {
    selectedInvoice.value = invoice || null

    // Guardar en store para que BioPago checkout lo lea sin depender de props
    if (invoice) {
        const ids = invoice.invoice_ids
            ? invoice.invoice_ids.map(String)
            : (invoice.id != null ? [String(invoice.id)] : [])

        checkoutStore.set({
            amountUsd:  parseFloat(String(invoice.amount ?? 0)),
            invoiceIds: ids,
            invoices:   invoice.invoices ?? [],
            clientId:   String(invoice.client_id ?? ''),
        })
    } else if (props.user_plan?.price) {
        checkoutStore.set({
            amountUsd:  parseFloat(String(props.user_plan.price)),
            invoiceIds: [],
            invoices:   [],
        })
    }

    showBankSelector.value = true
}

// UI: saludo y atajo a la lista de facturas
const firstName = computed(() => String((page.props.auth as any)?.user?.name ?? '').trim().split(/\s+/)[0] || '');
const scrollToInvoices = () => {
    showPendingInvoiceAlert.value = false;
    document.getElementById('mis-facturas')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const handleBankSelected = (bank: 'bnc' | 'bdv') => {
    if (bank === 'bnc') {
        showUserPaymentModal.value = true
    } else if (bank === 'bdv') {
        if (isBdvPaymentDisabled.value) {
            notify({
                message: 'Banco de Venezuela no disponible para esta empresa',
                type: 'warning',
                duration: 2500,
            })
            return
        }

        showBdvPaymentModal.value = true
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
                    <BcvRateCard />
                    <TotalClientsCard :total-clients="total_clients" />
                </div>

                <!-- Cards de Contratos por Estado (4 cards) -->
                <ContractStatsCards :contract-stats="contract_stats" />

                <!-- Tabla de Pagos para Admin -->
                <RecentPaymentsTable
                    :payments="admin_payments"
                    :bdv-ipg2-payments="bdv_ipg2_payments"
                    @view-payment="viewPayment"
                    @view-receipt="viewReceipt"
                />
            </template>

            <!-- Vista para USUARIO NORMAL (role = 0) -->
            <!-- Orden pensado para el cliente: 1) ¿cuánto debo? 2) facturas 3) pagos; a un lado, su cuenta -->
            <template v-else>
                <!-- Más ancho en escritorio para no dejar espacio en blanco -->
                <div class="mx-auto w-full max-w-[1400px] space-y-6">
                    <header>
                        <p class="text-[15px] text-muted-foreground">Hola{{ firstName ? ',' : '' }}</p>
                        <h1 class="text-2xl font-bold">{{ firstName || 'Bienvenido' }}</h1>
                    </header>

                    <!-- lg: izquierda = saldo + facturas | derecha = cuenta + historial de pagos -->
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start xl:grid-cols-[minmax(0,1fr)_440px]">
                        <div class="min-w-0 space-y-8">
                            <!-- Mismo evento que antes: abre el selector de banco con la factura elegida -->
                            <BalanceHero
                                :invoices="user_invoices"
                                :can-pay="$page.props.auth.user.role === 0"
                                @pay="openPaymentModal"
                                @see-invoices="scrollToInvoices"
                            />
                            <InvoiceList :invoices="user_invoices" :can-pay="$page.props.auth.user.role === 0" @pay="openPaymentModal" />
                        </div>

                        <aside class="min-w-0 space-y-8">
                            <div class="space-y-3">
                                <h2 class="text-lg font-bold lg:sr-only">Mi cuenta</h2>
                                <AccountInfo :user-plan="(user_plan as any)" :user-contract="(user_contract as any)" />
                            </div>
                            <UserPaymentsTable :payments="(user_payments as any)" @view-payment="viewPayment" @view-receipt="viewReceipt" />
                        </aside>
                    </div>
                </div>
            </template>
        </div>

        <!-- Selector de banco -->
        <UserPaymentBankSelector
            v-model:open="showBankSelector"
            :disable-bdv-payment="isBdvPaymentDisabled"
            @select-bank="handleBankSelected"
        />

        <!-- Modal de pago del usuario (BNC) -->
        <UserPaymentModal
            v-model:open="showUserPaymentModal"
            :user-plan="user_plan"
            :selected-invoice="selectedInvoice"
            @openReportModal="handleOpenReportModal"
        />

        <!-- Modal de pago del usuario (BDV) -->
        <UserPaymentBdvModal
            v-model:open="showBdvPaymentModal"
            :user-plan="user_plan"
            :selected-invoice="selectedInvoice"
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

        <!-- Aviso de facturas pendientes (al entrar) -->
        <Dialog v-model:open="showPendingInvoiceAlert">
            <DialogContent class="sm:max-w-md sm:rounded-3xl">
                <DialogHeader class="items-center text-center">
                    <span class="mb-2 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-950/50">
                        <svg class="h-7 w-7" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <DialogTitle class="text-xl font-bold">
                        Tienes {{ pending_invoices_count }} {{ pending_invoices_count === 1 ? 'factura pendiente' : 'facturas pendientes' }}
                    </DialogTitle>
                    <DialogDescription class="text-[15px]">
                        Paga lo antes posible para evitar la suspensión del servicio.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex-col gap-2 sm:flex-col">
                    <Button class="h-12 w-full rounded-xl text-base font-bold" @click="scrollToInvoices">Ver mis facturas</Button>
                    <Button variant="ghost" class="h-11 w-full text-[15px] text-muted-foreground" @click="showPendingInvoiceAlert = false">Ahora no</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
