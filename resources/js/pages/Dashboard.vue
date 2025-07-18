<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import ReportPaymentModal from '../components/ReportPaymentModal.vue';
import UserPaymentModal from '../components/UserPaymentModal.vue';
import { useForm } from '@inertiajs/vue3';

// Components
import { Button } from '@/components/ui/button';

import { useBcvStore } from '@/stores/bcv';
import { useBanksStore } from '@/stores/banks';
import { storeToRefs } from 'pinia';


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

// Usar usePage para acceder a los datos del usuario
const page = usePage()

// Estado para los modales
const showReportPaymentModal = ref(false)
const showUserPaymentModal = ref(false)

const payFee = () => {
    form.post(route('pay-fee'));
}

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
                        <div class="mt-4" v-if="$page.props.auth.user.plan_id && bcv">
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
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <PlaceholderPattern />
                <!-- <pre>{{ $page.props.auth.user}}</pre> -->
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
    </AppLayout>
</template>
