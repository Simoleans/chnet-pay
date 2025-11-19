<template>
    <div class="w-full">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Clientes del Sistema Local</h2>
            <CreateUser />
        </div>

        <!-- Buscador local por código o cédula -->
        <div class="mb-4 flex gap-2">
            <input
                v-model="localSearch"
                @input="submitLocalSearch"
                type="text"
                placeholder="Buscar por código|cédula|nombre|email..."
                class="flex-1 p-2 border rounded-md dark:text-black"
            />
            <Button
                v-if="localSearch"
                variant="outline"
                @click="clearLocalSearch"
                class="whitespace-nowrap"
            >
                Limpiar
            </Button>
        </div>

        <!-- Información de paginación -->
        <div v-if="pagination" class="mb-4 p-3 bg-green-50 rounded-lg">
            <p class="text-sm text-green-700">
                <strong>Total de registros:</strong> {{ pagination.total || 0 }} |
                <strong>Página:</strong> {{ pagination.current_page || 1 }} de {{ pagination.last_page || 1 }} |
                <strong>Mostrando:</strong> {{ pagination.from || 0 }} - {{ pagination.to || 0 }}
            </p>
        </div>

        <!-- Tabla de usuarios locales -->
        <div class="w-full overflow-auto rounded-xl border bg-background shadow-sm">
            <table class="min-w-max w-full text-sm text-left border-collapse">
                <thead class="border-b bg-muted">
                    <tr>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Cédula</th>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Código</th>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Nombre</th>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Email</th>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Teléfono</th>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Zona</th>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in data"
                        :key="user.id"
                        class="border-b transition-colors hover:bg-muted/50"
                    >
                        <td class="px-4 py-3 whitespace-nowrap">{{ user.id_number }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ user.code }}</td>
                        <td class="px-4 py-3">
                            <div class="max-w-xs truncate" :title="user.name">
                                {{ user.name }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="max-w-xs truncate" :title="user.email">
                                {{ user.email }}
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ user.phone }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ user.zone }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="router.visit(route('users.show', user.id))"
                                >
                                    Ver
                                </Button>
                                <EditUser :userData="user" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mensaje si no hay datos -->
        <div v-if="data && data.length === 0" class="text-center py-8 text-gray-500">
            No se encontraron usuarios.
        </div>

        <!-- Paginación -->
        <div v-if="pagination && pagination.last_page > 1" class="flex justify-center mt-4 gap-2">
            <Button
                @click="previousLocalPage"
                :disabled="pagination.current_page === 1"
                variant="outline"
            >
                Anterior
            </Button>
            <div class="flex gap-1">
                <Button
                    v-for="page in visibleLocalPages"
                    :key="page"
                    @click="goToLocalPage(page)"
                    :variant="page === pagination.current_page ? 'default' : 'outline'"
                    size="sm"
                >
                    {{ page }}
                </Button>
            </div>
            <Button
                @click="nextLocalPage"
                :disabled="pagination.current_page === pagination.last_page"
                variant="outline"
            >
                Siguiente
            </Button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { debounce } from 'lodash'
import CreateUser from './CreateUser.vue'
import EditUser from './EditUser.vue'

const props = defineProps<{
    data: any[]
    filters: {
        local_search?: string
    }
    pagination: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number
        to: number
    }
}>()

const localSearch = ref(props.filters?.local_search || '')

// Páginas visibles para la paginación local
const visibleLocalPages = computed(() => {
    if (!props.pagination) return []

    const { current_page, last_page } = props.pagination
    const pages = []
    const maxVisible = 5

    let start = Math.max(1, current_page - Math.floor(maxVisible / 2))
    const end = Math.min(last_page, start + maxVisible - 1)

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1)
    }

    for (let i = start; i <= end; i++) {
        pages.push(i)
    }

    return pages
})

const submitLocalSearch = debounce(() => {
    router.get(route('users.index'), {
        local_search: localSearch.value,
        page: 1,
    }, {
        preserveState: true,
        replace: true,
    })
}, 500)

const clearLocalSearch = () => {
    localSearch.value = ''
    submitLocalSearch()
}

const previousLocalPage = () => {
    if (props.pagination?.current_page && props.pagination?.current_page > 1) {
        navigateToLocalPage(props.pagination.current_page - 1)
    }
}

const nextLocalPage = () => {
    if (props.pagination?.current_page && props.pagination?.last_page && props.pagination.current_page < props.pagination.last_page) {
        navigateToLocalPage(props.pagination.current_page + 1)
    }
}

const goToLocalPage = (page: number) => {
    if (page !== props.pagination?.current_page) {
        navigateToLocalPage(page)
    }
}

const navigateToLocalPage = (page: number) => {
    router.get(route('users.index'), {
        local_search: localSearch.value,
        page: page,
    }, {
        preserveState: true,
        replace: true,
    })
}
</script>

