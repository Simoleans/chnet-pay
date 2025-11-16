<template>
    <AppLayout>
        <Head title="Lista de Planes" />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex justify-between flex-col md:lg:flex-row">
                <h1 class="text-2xl font-semibold">Lista de Planes</h1>
            </div>

            <!-- Tabla responsive -->
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
                            v-for="plan in plans"
                            :key="plan.id"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td class="px-4 py-3">
                                {{ plan.name }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ formatPrice(plan.price) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mensaje si no hay datos -->
            <div v-if="plans && plans.length === 0" class="text-center py-8 text-gray-500">
                No se encontraron planes.
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'

// Definir interfaz para los planes (simplificada)
interface Plan {
    id: string
    name: string
    price: number
}

// Props
const props = defineProps<{
    plans?: Plan[]
}>()

// Formatear precio en dólares
const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(price)
}

// Columnas de la tabla
const columns = [
    { key: 'name', label: 'Nombre' },
    { key: 'price', label: 'Precio' }
]
</script>
