<template>
    <AppLayout>
        <Head :title="`Cliente: ${user.name}`" />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-semibold">{{ user.name }}</h1>
                    <p class="text-sm text-gray-500">
                        {{ isWispro ? 'Cliente de Wispro' : 'Cliente Local' }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="goBack">
                        ← Volver
                    </Button>

                    <!-- Botones según estado -->
                    <template v-if="isWispro">
                        <!-- Cliente Wispro NO está en BD local -->
                        <Button
                            v-if="!existsInLocal"
                            @click="syncClient"
                            :disabled="isSyncing"
                            class="bg-blue-600 hover:bg-blue-700"
                        >
                            {{ isSyncing ? 'Sincronizando...' : '📥 Sincronizar Cliente' }}
                        </Button>

                        <!-- Cliente Wispro SÍ está en BD local -->
                        <Button
                            v-else
                            @click="updateWisproClient"
                            :disabled="isUpdating"
                            class="bg-green-600 hover:bg-green-700"
                        >
                            {{ isUpdating ? 'Actualizando...' : '🔄 Actualizar Cliente' }}
                        </Button>
                    </template>

                    <!-- Cliente Local - Mostrar modal de edición completo -->
                    <Button
                        v-else
                        @click="openEditModal"
                        class="bg-green-600 hover:bg-green-700"
                    >
                        ✏️ Editar Cliente
                    </Button>
                </div>
            </div>

            <!-- Información del Cliente -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                    <h2 class="text-lg font-semibold mb-4">Información Personal</h2>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-500">Nombre</p>
                            <p class="font-medium">{{ user.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Email</p>
                            <p class="font-medium">{{ user.email || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Teléfono</p>
                            <p class="font-medium">{{ user.phone || user.phone_mobile || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Cédula</p>
                            <p class="font-medium">{{ user.id_number || user.national_identification_number || 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                    <h2 class="text-lg font-semibold mb-4">Información de Servicio</h2>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-500">Código</p>
                            <p class="font-medium">{{ user.code || user.custom_id || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Zona</p>
                            <p class="font-medium">{{ user.zone || user.zone_name || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Dirección</p>
                            <p class="font-medium">{{ user.address || user.street || 'N/A' }}</p>
                        </div>
                        <div v-if="!isWispro">
                            <p class="text-sm text-gray-500">Estado</p>
                            <span :class="[
                                'px-2 py-1 text-xs rounded',
                                user.status ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                            ]">
                                {{ user.status ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información del Contrato y Plan -->
            <div v-if="contract" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                    <h2 class="text-lg font-semibold mb-4">📋 Contrato</h2>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-500">Fecha de Inicio</p>
                            <p class="font-medium">{{ formatDate(contract.start_date) }}</p>
                        </div>
                        <div v-if="contract.ip && isAdmin">
                            <p class="text-sm text-gray-500">IP</p>
                            <p class="font-medium">{{ contract.ip }}</p>
                        </div>
                        <div v-if="contract.state">
                            <p class="text-sm text-gray-500">Estado del Servicio</p>
                            <span :class="[
                                'px-2 py-1 text-xs rounded font-semibold',
                                contract.state === 'enabled' ? 'bg-green-100 text-green-800' :
                                contract.state === 'alerted' ? 'bg-yellow-100 text-yellow-800' :
                                contract.state === 'disabled' ? 'bg-red-100 text-red-800' :
                                'bg-gray-100 text-gray-800'
                            ]">
                                {{ getStateLabel(contract.state) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                    <h2 class="text-lg font-semibold mb-4">📦 Plan Contratado</h2>
                    <div v-if="plan" class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-500">Nombre del Plan</p>
                            <p class="font-medium text-lg">{{ plan.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Precio</p>
                            <div class="space-y-1">
                                <p class="font-medium text-2xl text-green-600">
                                    ${{ formatPrice(plan.price) }} USD
                                </p>
                                <p v-if="bcvStore.bcv" class="font-medium text-lg text-blue-600">
                                    Bs. {{ formatPriceBs(plan.price) }}
                                </p>
                                <p v-if="bcvStore.date" class="text-xs text-gray-400">
                                    Tasa BCV: {{ bcvStore.bcv }} ({{ bcvStore.date }})
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">
                        No hay plan asignado
                    </div>
                </div>
            </div>

            <!-- Mapa de Ubicación -->
            <div v-if="contract && contract.latitude && contract.longitude" class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                <h2 class="text-lg font-semibold mb-4">📍 Ubicación</h2>
                <div class="w-full h-96 rounded-lg overflow-hidden">
                    <iframe
                        :src="getMapUrl()"
                        width="100%"
                        height="100%"
                        frameborder="0"
                        style="border:0"
                    ></iframe>
                </div>
                <div class="mt-2 text-sm text-gray-500">
                    Coordenadas: {{ contract.latitude }}, {{ contract.longitude }}
                    <a
                        :href="`https://www.google.com/maps?q=${contract.latitude},${contract.longitude}`"
                        target="_blank"
                        class="ml-2 text-blue-600 hover:underline"
                    >
                        Ver en Google Maps →
                    </a>
                </div>
            </div>

            <!-- Tablas de Pagos y Facturas (solo si existe en local o es cliente local) -->
            <div v-if="!isWispro || existsInLocal" class="space-y-4">
                <!-- Tabla de Pagos -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                    <h2 class="text-lg font-semibold mb-4">Historial de Pagos</h2>

                    <div v-if="payments && payments.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monto</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Banco</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="payment in payments" :key="payment.id">
                                    <td class="px-4 py-3">{{ payment.reference }}</td>
                                    <td class="px-4 py-3">${{ payment.amount }}</td>
                                    <td class="px-4 py-3">{{ payment.payment_date }}</td>
                                    <td class="px-4 py-3">{{ payment.bank }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="[
                                            'px-2 py-1 text-xs rounded',
                                            payment.verify_payments ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                        ]">
                                            {{ payment.verify_payments ? 'Verificado' : 'Pendiente' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">
                        No hay pagos registrados
                    </div>
                </div>

                <!-- Tabla de Facturas -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                    <h2 class="text-lg font-semibold mb-4">Historial de Facturas</h2>

                    <div v-if="invoices && invoices.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monto Total</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pagado</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="invoice in invoices" :key="invoice.id">
                                    <td class="px-4 py-3">{{ invoice.code }}</td>
                                    <td class="px-4 py-3">{{ invoice.period }}</td>
                                    <td class="px-4 py-3">${{ invoice.amount_due }}</td>
                                    <td class="px-4 py-3">${{ invoice.amount_paid }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="[
                                            'px-2 py-1 text-xs rounded',
                                            invoice.status === 'paid' ? 'bg-green-100 text-green-800' :
                                            invoice.status === 'partial' ? 'bg-yellow-100 text-yellow-800' :
                                            'bg-red-100 text-red-800'
                                        ]">
                                            {{
                                                invoice.status === 'paid' ? 'Pagado' :
                                                invoice.status === 'partial' ? 'Parcial' :
                                                'Pendiente'
                                            }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">
                        No hay facturas registradas
                    </div>
                </div>
            </div>

            <!-- Mensaje si es Wispro y NO está en local -->
            <div v-if="isWispro && !existsInLocal" class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            Este cliente no está sincronizado en la base de datos local.
                            Sincronízalo para poder registrar pagos y facturas.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Edición (solo para clientes locales) -->
        <EditUser
            v-if="!isWispro && showEditModal"
            :userData="user"
            ref="editUserRef"
        />
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { ref, computed } from 'vue'
import EditUser from './Components/EditUser.vue'
import { useBcvStore } from '@/stores/bcv'

interface Props {
    user: any
    localUser?: any
    payments: any[]
    invoices: any[]
    contract?: any
    plan?: any
    isWispro: boolean
    existsInLocal: boolean
}

const props = defineProps<Props>()

const page = usePage()
const isAdmin = computed(() => (page.props.auth as any)?.isAdmin || false)
const bcvStore = useBcvStore()

const isSyncing = ref(false)
const isUpdating = ref(false)
const showEditModal = ref(false)
const editUserRef = ref(null)

const goBack = () => {
    router.visit(route('users.index'))
}

const syncClient = async () => {
    if (!confirm('¿Deseas sincronizar este cliente en la base de datos local?')) {
        return
    }

    isSyncing.value = true

    router.post(route('users.sync-wispro-single', props.user.id), {}, {
        onSuccess: () => {
            isSyncing.value = false
        },
        onError: () => {
            isSyncing.value = false
        }
    })
}

const updateWisproClient = async () => {
    // Mostrar alert de confirmación con los datos que se van a sincronizar
    const message = `¿Deseas actualizar este cliente con los datos de Wispro?

📝 Datos que se sincronizarán:
• Nombre: ${props.user.name}
• Email: ${props.user.email}
• Teléfono: ${props.user.phone_mobile || 'N/A'}
• Dirección: ${props.user.street || props.user.address || 'N/A'}

Esta acción actualizará tanto Wispro como la base de datos local.`

    if (!confirm(message)) {
        return
    }

    isUpdating.value = true

    // Usar la ruta de update-client que ya maneja la sincronización
    router.put(route('users.update-client', props.localUser.id), {
        name: props.user.name,
        email: props.user.email,
        phone: props.user.phone_mobile || '',
        address: props.user.street || props.user.address || ''
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isUpdating.value = false
        },
        onError: (errors: any) => {
            console.error('Error al actualizar:', errors)
            isUpdating.value = false
        }
    })
}

const openEditModal = () => {
    // Solo para clientes locales
    if (!props.isWispro) {
        showEditModal.value = true
        if (editUserRef.value) {
            (editUserRef.value as any).openModal()
        }
    }
}

// Funciones de formateo
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
    if (!priceUsd || !bcvStore.bcv) return '0.00'
    const priceBs = priceUsd * parseFloat(bcvStore.bcv)
    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(priceBs)
}

const getStateLabel = (state: string) => {
    const stateLabels: { [key: string]: string } = {
        'enabled': '✅ Activo',
        'alerted': '⚠️ Alertado',
        'disabled': '❌ Deshabilitado'
    }
    return stateLabels[state] || state
}

const getMapUrl = () => {
    if (!props.contract || !props.contract.latitude || !props.contract.longitude) {
        return ''
    }

    const lat = parseFloat(props.contract.latitude)
    const lng = parseFloat(props.contract.longitude)
    const delta = 0.01

    const minLng = lng - delta
    const minLat = lat - delta
    const maxLng = lng + delta
    const maxLat = lat + delta

    // bbox format: minLng,minLat,maxLng,maxLat
    return `https://www.openstreetmap.org/export/embed.html?bbox=${minLng},${minLat},${maxLng},${maxLat}&layer=mapnik&marker=${lat},${lng}`
}
</script>

