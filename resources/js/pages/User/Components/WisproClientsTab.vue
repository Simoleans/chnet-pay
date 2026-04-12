<template>
    <div class="w-full">
        <div class="flex flex-col gap-4 mb-4 md:flex-row md:justify-between md:items-center">
            <h2 class="text-xl font-semibold">Clientes de Wispro</h2>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <!-- Botón de sincronización general (solo para admin) -->
                <button
                    v-if="$page.props.auth.user.role === 1"
                    @click="syncAllClients"
                    :disabled="isSyncing"
                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 text-sm"
                >
                    <svg v-if="isSyncing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="hidden sm:inline">{{ isSyncing ? 'Sincronizando...' : 'Sincronizar Todos' }}</span>
                    <span class="sm:hidden">{{ isSyncing ? 'Sincronizando...' : 'Sincronizar' }}</span>
                </button>

                <!-- Control de registros por página -->
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600 whitespace-nowrap">Registros:</label>
                    <select
                        v-model="wisproPerPage"
                        @change="changeWisproPerPage"
                        class="flex-1 sm:flex-none px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Buscador por número de abonado -->
        <div class="mb-4 flex gap-2">
            <input
                v-model="wisproSearch"
                @input="submitWisproSearch"
                type="text"
                placeholder="Buscar por número de abonado..."
                class="flex-1 p-2 border rounded-md dark:text-black"
            />
            <Button
                v-if="wisproSearch"
                variant="outline"
                @click="clearWisproSearch"
                class="whitespace-nowrap"
            >
                Limpiar
            </Button>
        </div>

        <!-- Información de paginación de Wispro -->
        <div v-if="wispro_clients && wispro_clients.meta" class="mb-4 p-3 bg-blue-50 rounded-lg overflow-x-auto">
            <p class="text-sm text-blue-700 whitespace-nowrap">
                <strong>Total:</strong> {{ wispro_clients.meta.pagination?.total_records || 0 }} |
                <strong>Página:</strong> {{ wispro_clients.meta.pagination?.current_page || 1 }} de {{ wispro_clients.meta.pagination?.total_pages || 1 }} |
                <strong>Por página:</strong> {{ wispro_clients.meta.pagination?.per_page || 0 }}
            </p>
        </div>

        <!-- Tabla de clientes Wispro -->
        <div class="w-full overflow-auto rounded-xl border bg-background shadow-sm">
            <table class="min-w-max w-full text-sm text-left border-collapse">
                <thead class="border-b bg-muted">
                    <tr>
                        <th
                            v-for="column in wisproColumns"
                            :key="column.key"
                            class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="client in wisproClientsData"
                        :key="client.id"
                        class="border-b transition-colors hover:bg-muted/50"
                    >
                        <td class="px-4 py-3">
                            <div class="max-w-xs truncate" :title="client.name">
                                {{ client.name }}
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ client.national_identification_number }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="max-w-xs truncate" :title="client.email">
                                {{ client.email || 'N/A' }}
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ client.phone_mobile || 'N/A' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="max-w-xs truncate" :title="client.address">
                                {{ client.address || 'N/A' }}
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ client.zone_name || 'N/A' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="router.visit(route('users.show-wispro', client.id))"
                                >
                                    Ver
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    :disabled="isDeleting === getWisproId(client)"
                                    @click="deleteWisproClient(client)"
                                >
                                    {{ isDeleting === getWisproId(client) ? 'Borrando...' : 'Borrar' }}
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mensaje si no hay datos de Wispro -->
        <div v-if="!wisproClientsData || wisproClientsData.length === 0" class="text-center py-8 text-gray-500">
            No se encontraron clientes de Wispro.
        </div>

        <!-- Paginación de Wispro -->
        <div v-if="wispro_clients && wispro_clients.meta && wispro_clients.meta.pagination"
             class="flex flex-col gap-3 px-4 py-3 bg-white border-t border-gray-200 sm:px-6 mt-4">
            <!-- Información de paginación en móvil -->
            <span class="text-sm text-gray-700 text-center sm:text-left">
                Página
                <span class="font-medium">{{ wispro_clients.meta.pagination.current_page }}</span>
                de
                <span class="font-medium">{{ wispro_clients.meta.pagination.total_pages }}</span>
                <span class="hidden sm:inline">
                    ({{ wispro_clients.meta.pagination.total_records }} registros)
                </span>
            </span>

            <!-- Botones de paginación con scroll horizontal -->
            <div class="overflow-x-auto">
                <div class="flex gap-1 justify-center sm:justify-end min-w-max">
                    <button
                        @click="previousWisproPage"
                        :disabled="wispro_clients.meta.pagination.current_page === 1"
                        class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span class="hidden sm:inline">← Anterior</span>
                        <span class="sm:hidden">←</span>
                    </button>

                    <!-- Botones de páginas -->
                    <div class="flex gap-1">
                        <button
                            v-for="page in visibleWisproPages"
                            :key="page"
                            @click="goToWisproPage(page)"
                            :class="{
                                'bg-blue-600 text-white': page === wispro_clients.meta.pagination.current_page,
                                'bg-white text-gray-500 hover:bg-gray-50': page !== wispro_clients.meta.pagination.current_page
                            }"
                            class="relative inline-flex items-center px-3 py-2 text-sm font-medium border border-gray-300 rounded-md transition-colors"
                        >
                            {{ page }}
                        </button>
                    </div>

                    <button
                        @click="nextWisproPage"
                        :disabled="wispro_clients.meta.pagination.current_page === wispro_clients.meta.pagination.total_pages"
                        class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span class="hidden sm:inline">Siguiente →</span>
                        <span class="sm:hidden">→</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { debounce } from 'lodash'
import { useNotifications } from '@/composables/useNotifications'
import axios from 'axios'

const { notify } = useNotifications()

interface WisproClient {
    id: string
    id_wispro?: string
    public_id: number
    custom_id: string
    name: string
    email: string
    phone_mobile: string
    phone_mobile_verified: boolean
    address: string
    national_identification_number: string
    zone_name: string
    link_mobile_login: string
}

interface WisproResponse {
    status: number
    meta: {
        object: string
        pagination: {
            total_records: number
            total_pages: number
            per_page: number
            current_page: number
        }
    }
    data: WisproClient[]
}

const props = defineProps<{
    wispro_clients: WisproResponse
    filters: {
        wispro_search?: string
    }
    pagination: {
        current_page: number
    }
}>()

const wisproSearch = ref(props.filters?.wispro_search || '')
const wisproPerPage = ref(20)
const isSyncing = ref(false)
const isDeleting = ref<string | null>(null)

// Columnas para la tabla de Wispro
const wisproColumns = [
    { key: 'name', label: 'Nombre' },
    { key: 'national_identification_number', label: 'Cédula' },
    { key: 'email', label: 'Email' },
    { key: 'phone_mobile', label: 'Teléfono' },
    { key: 'address', label: 'Dirección' },
    { key: 'zone_name', label: 'Zona' },
    { key: 'actions', label: 'Opciones' },
]

// Datos de clientes Wispro
const wisproClientsData = computed((): WisproClient[] => {
    return props.wispro_clients?.data || []
})

// Páginas visibles para la paginación de Wispro
const visibleWisproPages = computed(() => {
    if (!props.wispro_clients?.meta?.pagination) return []

    const { current_page, total_pages } = props.wispro_clients.meta.pagination
    const pages = []
    const maxVisible = 5

    let start = Math.max(1, current_page - Math.floor(maxVisible / 2))
    const end = Math.min(total_pages, start + maxVisible - 1)

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1)
    }

    for (let i = start; i <= end; i++) {
        pages.push(i)
    }

    return pages
})

// Métodos de paginación para Wispro
const previousWisproPage = () => {
    if (props.wispro_clients?.meta?.pagination?.current_page && props.wispro_clients.meta.pagination.current_page > 1) {
        const currentPage = props.wispro_clients.meta.pagination.current_page - 1
        navigateToWisproPage(currentPage)
    }
}

const nextWisproPage = () => {
    if (props.wispro_clients?.meta?.pagination) {
        const { current_page, total_pages } = props.wispro_clients.meta.pagination
        if (current_page < total_pages) {
            navigateToWisproPage(current_page + 1)
        }
    }
}

const goToWisproPage = (page: number) => {
    if (page !== props.wispro_clients?.meta?.pagination?.current_page) {
        navigateToWisproPage(page)
    }
}

const navigateToWisproPage = (page: number) => {
    router.get(route('users.index'), {
        page: props.pagination?.current_page || 1,
        wispro_page: page,
        wispro_per_page: wisproPerPage.value,
        wispro_search: wisproSearch.value,
    }, {
        preserveState: true,
        replace: true,
    })
}

const changeWisproPerPage = () => {
    router.get(route('users.index'), {
        page: props.pagination?.current_page || 1,
        wispro_page: 1,
        wispro_per_page: wisproPerPage.value,
        wispro_search: wisproSearch.value,
    }, {
        preserveState: true,
        replace: true,
    })
}

// Búsqueda por número de abonado en Wispro
const submitWisproSearch = debounce(() => {
    router.get(route('users.index'), {
        page: props.pagination?.current_page || 1,
        wispro_page: 1,
        wispro_per_page: wisproPerPage.value,
        wispro_search: wisproSearch.value,
    }, {
        preserveState: true,
        replace: true,
    })
}, 500)

const clearWisproSearch = () => {
    wisproSearch.value = ''
    submitWisproSearch()
}

const getWisproId = (client: WisproClient): string => {
    return client.id_wispro || client.id
}

const deleteWisproClient = async (client: WisproClient) => {
    const wisproId = getWisproId(client)

    if (!wisproId) {
        notify({
            message: '❌ No se encontró el id_wispro del cliente.',
            type: 'error',
            duration: 5000,
        })
        return
    }

    const confirmed = confirm(
        `¿Seguro que deseas borrar a "${client.name}"?\n\nEsta acción también eliminará el cliente en Wispro y no se puede deshacer.`
    )

    if (!confirmed) {
        return
    }

    isDeleting.value = wisproId

    try {
        const response = await axios.delete(route('users.delete-wispro', wisproId))

        notify({
            message: response.data?.message || '✅ Cliente eliminado en Wispro.',
            type: 'success',
            duration: 5000,
        })

        router.reload({ only: ['wispro_clients'] })
    } catch (error: any) {
        const errorMessage = error.response?.data?.message || '❌ Error al eliminar el cliente en Wispro.'

        notify({
            message: errorMessage,
            type: 'error',
            duration: 5000,
        })
    } finally {
        isDeleting.value = null
    }
}

// Sincronizar todos los clientes de Wispro
const syncAllClients = async () => {
    const totalRecords = props.wispro_clients?.meta?.pagination?.total_records || 0
    const estimatedMinutesFirst = Math.ceil(totalRecords / 100)
    const estimatedMinutesNext = Math.ceil(totalRecords / 300)

    if (!confirm(`¿Deseas sincronizar TODOS los clientes de Wispro?\n\n📊 Total de registros: ${totalRecords.toLocaleString()}\n⏱️ Primera vez: ~${estimatedMinutesFirst} minutos\n⏱️ Siguientes veces: ~${estimatedMinutesNext} minutos\n\n🔄 El proceso se ejecutará en segundo plano.\n💡 Podrás seguir usando el sistema mientras se sincroniza.\n📊 Revisa los logs para ver el progreso.\n\n¿Continuar?`)) {
        return
    }

    isSyncing.value = true

    notify({
        message: '🔄 Iniciando sincronización... Por favor espera.',
        type: 'warning',
        duration: 3000,
    })

    try {
        const response = await axios.post('/api/users/sync-wispro-all')
        const data = response.data

        if (data.success) {
            notify({
                message: `✅ ${data.message}`,
                type: 'success',
                duration: 6000,
            })

            console.log('🚀 Sincronización iniciada en segundo plano')
            console.log('📊 Para ver el progreso, revisa los logs en: storage/logs/laravel.log')
        } else {
            notify({
                message: `❌ Error: ${data.message}`,
                type: 'error',
                duration: 5000,
            })
        }
    } catch (error: any) {
        console.error('Error al sincronizar:', error)

        let errorMessage = '❌ Error al iniciar sincronización.'

        if (error.response) {
            if (error.response.status === 419) {
                errorMessage = '❌ La sesión ha expirado. Por favor recarga la página.'
            } else if (error.response.status === 403) {
                errorMessage = '❌ No tienes permisos para realizar esta acción.'
            } else if (error.response.status === 409) {
                errorMessage = '⚠️ Ya hay una sincronización en progreso. Por favor espera.'
            } else if (error.response.data?.message) {
                errorMessage = `❌ ${error.response.data.message}`
            }
        } else if (error.request) {
            errorMessage = '❌ Error de conexión. Verifica tu internet.'
        }

        notify({
            message: errorMessage,
            type: 'error',
            duration: 5000,
        })
    } finally {
        isSyncing.value = false
    }
}
</script>

