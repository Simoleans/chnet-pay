<template>
    <div class="w-full">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Administradores / Trabajadores</h2>
            <CreateUser />
        </div>

        <!-- Buscador de administradores -->
        <div class="mb-4 flex gap-2">
            <input
                v-model="adminSearch"
                @input="submitAdminSearch"
                type="text"
                placeholder="Buscar administrador por nombre o cédula..."
                class="flex-1 p-2 border rounded-md dark:text-black"
            />
            <Button
                v-if="adminSearch"
                variant="outline"
                @click="clearAdminSearch"
                class="whitespace-nowrap"
            >
                Limpiar
            </Button>
        </div>

        <!-- Información de paginación -->
        <div v-if="paginationAdmins" class="mb-4 p-3 bg-purple-50 rounded-lg">
            <p class="text-sm text-purple-700">
                <strong>Total de administradores:</strong> {{ paginationAdmins.total || 0 }} |
                <strong>Página:</strong> {{ paginationAdmins.current_page || 1 }} de {{ paginationAdmins.last_page || 1 }} |
                <strong>Mostrando:</strong> {{ paginationAdmins.from || 0 }} - {{ paginationAdmins.to || 0 }}
            </p>
        </div>

        <!-- Tabla de administradores -->
        <div class="w-full overflow-auto rounded-xl border bg-background shadow-sm">
            <table class="min-w-max w-full text-sm text-left border-collapse">
                <thead class="border-b bg-muted">
                    <tr>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Cédula</th>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Nombre</th>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Email</th>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Teléfono</th>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Estado</th>
                        <th class="px-4 py-2 text-muted-foreground text-sm font-medium whitespace-nowrap">Opciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="admin in admins"
                        :key="admin.id"
                        class="border-b transition-colors hover:bg-muted/50"
                    >
                        <td class="px-4 py-3 whitespace-nowrap">{{ admin.id_number }}</td>
                        <td class="px-4 py-3">
                            <div class="max-w-xs truncate" :title="admin.name">
                                {{ admin.name }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="max-w-xs truncate" :title="admin.email">
                                {{ admin.email }}
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ admin.phone || 'N/A' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span :class="[
                                'px-2 py-1 text-xs rounded font-semibold',
                                admin.status ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                            ]">
                                {{ admin.status ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <EditUser :userData="admin" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mensaje si no hay datos -->
        <div v-if="admins && admins.length === 0" class="text-center py-8 text-gray-500">
            No se encontraron administradores.
        </div>

        <!-- Paginación -->
        <div v-if="paginationAdmins && paginationAdmins.last_page > 1" class="flex justify-center mt-4 gap-2">
            <Button
                @click="previousAdminPage"
                :disabled="paginationAdmins.current_page === 1"
                variant="outline"
            >
                Anterior
            </Button>
            <div class="flex gap-1">
                <Button
                    v-for="page in visibleAdminPages"
                    :key="page"
                    @click="goToAdminPage(page)"
                    :variant="page === paginationAdmins.current_page ? 'default' : 'outline'"
                    size="sm"
                >
                    {{ page }}
                </Button>
            </div>
            <Button
                @click="nextAdminPage"
                :disabled="paginationAdmins.current_page === paginationAdmins.last_page"
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
    admins: any[]
    filters: {
        admin_search?: string
    }
    paginationAdmins: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number
        to: number
    }
}>()

const adminSearch = ref(props.filters?.admin_search || '')

// Páginas visibles para la paginación de admins
const visibleAdminPages = computed(() => {
    if (!props.paginationAdmins) return []

    const { current_page, last_page } = props.paginationAdmins
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

const submitAdminSearch = debounce(() => {
    router.get(route('users.index'), {
        admin_search: adminSearch.value,
        admin_page: 1,
    }, {
        preserveState: true,
        replace: true,
    })
}, 500)

const clearAdminSearch = () => {
    adminSearch.value = ''
    submitAdminSearch()
}

const previousAdminPage = () => {
    if (props.paginationAdmins?.current_page && props.paginationAdmins?.current_page > 1) {
        navigateToAdminPage(props.paginationAdmins.current_page - 1)
    }
}

const nextAdminPage = () => {
    if (props.paginationAdmins?.current_page && props.paginationAdmins?.last_page && props.paginationAdmins.current_page < props.paginationAdmins.last_page) {
        navigateToAdminPage(props.paginationAdmins.current_page + 1)
    }
}

const goToAdminPage = (page: number) => {
    if (page !== props.paginationAdmins?.current_page) {
        navigateToAdminPage(page)
    }
}

const navigateToAdminPage = (page: number) => {
    router.get(route('users.index'), {
        admin_search: adminSearch.value,
        admin_page: page,
    }, {
        preserveState: true,
        replace: true,
    })
}
</script>

