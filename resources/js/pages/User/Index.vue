<template>
    <AppLayout>
        <Head title="Usuarios" />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex justify-between flex-col md:lg:flex-row">
                <h1 class="text-2xl font-semibold">Usuarios</h1>
                <div class="flex gap-2">
                    <CreateUser :zones="zones" :plans="plans" />
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-between gap-4">
                <input
                    v-model="search"
                    @input="submit"
                    type="text"
                    placeholder="Buscar por nombre, código o cédula..."
                    class="w-full sm:w-1/2 p-2 border rounded-md dark:text-black"
                />
                <div class="flex w-full justify-end items-center gap-2">
                    <Button variant="outline" @click="restoreFilters">Restaurar Filtros</Button>
                </div>
            </div>

                        <!-- Clientes de Wispro -->
            <div class="w-full mt-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Clientes de Wispro</h2>

                    <!-- Control de registros por página -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-600">Registros por página:</label>
                        <select
                            v-model="wisproPerPage"
                            @change="changeWisproPerPage"
                            class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <!-- Información de paginación de Wispro -->
                <div v-if="wispro_clients && wispro_clients.meta" class="mb-4 p-3 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-700">
                        <strong>Total de registros:</strong> {{ wispro_clients.meta.pagination?.total_records || 0 }} |
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
                                <!-- <td class="px-4 py-3 whitespace-nowrap">
                                    {{ client.public_id }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ client.custom_id }}
                                </td> -->
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
                                <!-- <td class="px-4 py-3 whitespace-nowrap">
                                    <span :class="{
                                        'bg-green-100 text-green-800 px-2 py-1 rounded text-xs': client.phone_mobile_verified,
                                        'bg-red-100 text-red-800 px-2 py-1 rounded text-xs': !client.phone_mobile_verified
                                    }">
                                        {{ client.phone_mobile_verified ? 'Verificado' : 'No verificado' }}
                                    </span>
                                </td> -->
                                <!-- <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex gap-2">
                                        <button
                                            @click="viewWisproClient(client)"
                                            class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-sm"
                                        >
                                            Ver
                                        </button>
                                    </div>
                                </td> -->
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
                     class="flex items-center justify-between px-4 py-3 bg-white border-t border-gray-200 sm:px-6 mt-4">
                    <div class="flex items-center justify-between flex-1 sm:justify-end">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-700">
                                Mostrando página
                                <span class="font-medium">{{ wispro_clients.meta.pagination.current_page }}</span>
                                de
                                <span class="font-medium">{{ wispro_clients.meta.pagination.total_pages }}</span>
                                ({{ wispro_clients.meta.pagination.total_records }} registros totales)
                            </span>
                            <div class="flex gap-1">
                                <button
                                    @click="previousWisproPage"
                                    :disabled="wispro_clients.meta.pagination.current_page === 1"
                                    class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    ← Anterior
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
                                    Siguiente →
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


<!--             <div class="w-full mt-8">
                <h2 class="text-xl font-semibold mb-4">Usuarios Locales</h2>
            </div>


            <div class="w-full overflow-auto rounded-xl border bg-background shadow-sm">
                <table class="min-w-max w-full text-sm text-left border-collapse">
                    <thead class="border-b bg-muted">
                        <tr>
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(item, i) in data"
                            :key="i"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td v-for="column in columns" :key="column.key" class="px-4 py-3 whitespace-nowrap">
                                <template v-if="column.key === 'actions'">
                                    <div class="flex gap-2">
                                        <button
                                            @click="viewUser(item)"
                                            class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-sm"
                                        >
                                            Ver
                                        </button>
                                        <EditUser :user-data="item" :zones="zones" :plans="plans" />
                                    </div>
                                </template>
                                <template v-else>
                                    {{ item[column.key] }}
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>


            <div v-if="data && data.length === 0" class="text-center py-8 text-gray-500">
                No se encontraron usuarios.
            </div>


            <div class="flex items-center justify-between px-4 py-3 bg-white border-t border-gray-200 sm:px-6">
                <div class="flex items-center justify-between flex-1 sm:justify-end">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-700">
                            Mostrando
                            <span class="font-medium">{{ pagination.from }}</span>
                            a
                            <span class="font-medium">{{ pagination.to }}</span>
                            de
                            <span class="font-medium">{{ pagination.total }}</span>
                            resultados
                        </span>
                        <div class="flex gap-1">
                            <button
                                @click="previousPage"
                                :disabled="pagination.current_page === 1"
                                class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Anterior
                            </button>
                            <button
                                @click="nextPage"
                                :disabled="pagination.current_page === pagination.last_page"
                                class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Siguiente
                            </button>
                        </div>
                    </div>
                </div>
            </div> -->
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { debounce } from 'lodash'
import CreateUser from './Components/CreateUser.vue'
import EditUser from './Components/EditUser.vue'

interface WisproClient {
    id: string;
    public_id: number;
    custom_id: string;
    name: string;
    email: string;
    phone_mobile: string;
    phone_mobile_verified: boolean;
    address: string;
    national_identification_number: string;
    zone_name: string;
    link_mobile_login: string;
}

interface WisproResponse {
    status: number;
    meta: {
        object: string;
        pagination: {
            total_records: number;
            total_pages: number;
            per_page: number;
            current_page: number;
        };
    };
    data: WisproClient[];
}

const props = defineProps<{
    data: any[];
    filters: { search?: string };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    zones: any[];
    plans: any[];
    wispro_clients: WisproResponse;
}>()

const columns = [
    { key: 'id_number', label: 'Cédula' },
    { key: 'code', label: 'Código' },
    { key: 'name', label: 'Nombre' },
    { key: 'actions', label: 'Opciones' },
]

// Columnas para la tabla de Wispro
const wisproColumns = [
    //{ key: 'public_id', label: 'ID Público' },
    //{ key: 'custom_id', label: 'ID Personalizado' },
    { key: 'name', label: 'Nombre' },
    { key: 'national_identification_number', label: 'Cédula' },
    { key: 'email', label: 'Email' },
    { key: 'phone_mobile', label: 'Teléfono' },
    { key: 'address', label: 'Dirección' },
    { key: 'zone_name', label: 'Zona' },
    //{ key: 'phone_mobile_verified', label: 'Verificado' },
    //{ key: 'actions', label: 'Acciones' },
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
    const maxVisible = 5 // Máximo 5 botones de página visibles

    let start = Math.max(1, current_page - Math.floor(maxVisible / 2))
    const end = Math.min(total_pages, start + maxVisible - 1)

    // Ajustar inicio si estamos cerca del final
    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1)
    }

    for (let i = start; i <= end; i++) {
        pages.push(i)
    }

    return pages
})

const search = ref(props.filters?.search || '')
const wisproPerPage = ref(20) // Registros por página para Wispro

const restoreFilters = () => {
    search.value = ''
    submit()
}

const submit = debounce(() => {
    router.get(route('users.index'), {
        search: search.value,
        page: 1, // Resetear a la primera página al filtrar
    }, {
        preserveState: true,
        replace: true,
    })
}, 700)

const previousPage = () => {
    if (props.pagination?.current_page && props.pagination.current_page > 1) {
        router.get(route('users.index'), {
            search: search.value,
            page: props.pagination.current_page - 1,
        }, {
            preserveState: true,
            replace: true,
        })
    }
}

const nextPage = () => {
    if (props.pagination?.current_page && props.pagination?.last_page && props.pagination.current_page < props.pagination.last_page) {
        router.get(route('users.index'), {
            search: search.value,
            page: props.pagination.current_page + 1,
        }, {
            preserveState: true,
            replace: true,
        })
    }
}

const viewUser = (user: any) => {
    // Aquí puedes implementar la lógica para ver el usuario
    // Por ejemplo, navegar a una página de detalles o abrir un modal
    console.log('Ver usuario:', user)
    // router.visit(route('users.show', user.id))
}

// Métodos para Wispro
const viewWisproClient = (client: WisproClient) => {
    console.log('Ver cliente de Wispro:', client)
    // Aquí puedes implementar la lógica para ver el cliente de Wispro
    // Por ejemplo, mostrar un modal con más detalles
}

const openLink = (url: string) => {
    window.open(url, '_blank')
}

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
        search: search.value,
        page: props.pagination?.current_page || 1, // Mantener página actual de usuarios locales
        wispro_page: page, // Nueva página para Wispro
        wispro_per_page: wisproPerPage.value, // Registros por página para Wispro
    }, {
        preserveState: true,
        replace: true,
    })
}

const changeWisproPerPage = () => {
    // Al cambiar registros por página, volver a la página 1
    router.get(route('users.index'), {
        search: search.value,
        page: props.pagination?.current_page || 1,
        wispro_page: 1,
        wispro_per_page: wisproPerPage.value,
    }, {
        preserveState: true,
        replace: true,
    })
}
</script>
