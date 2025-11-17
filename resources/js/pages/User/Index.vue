<template>
    <AppLayout>
        <Head title="Usuarios" />
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex justify-between flex-col md:lg:flex-row">
                <h1 class="text-2xl font-semibold">Usuarios</h1>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 overflow-x-auto">
                <nav class="-mb-px flex space-x-8 min-w-max px-1">
                    <button
                        @click="activeTab = 'sistema'"
                        :class="[
                            activeTab === 'sistema'
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition'
                        ]"
                    >
                        Clientes Sistema
                    </button>
                    <button
                        @click="activeTab = 'wispro'"
                        :class="[
                            activeTab === 'wispro'
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition'
                        ]"
                    >
                        Clientes Wispro
                    </button>
                    <button
                        @click="activeTab = 'admins'"
                        :class="[
                            activeTab === 'admins'
                                ? 'border-purple-500 text-purple-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition'
                        ]"
                    >
                        Administradores
                    </button>
                </nav>
            </div>

            <!-- Tab: Clientes Sistema -->
            <LocalClientsTab
                v-if="activeTab === 'sistema'"
                :data="data"
                :filters="filters"
                :pagination="pagination"
            />

            <!-- Tab: Clientes de Wispro -->
            <WisproClientsTab
                v-if="activeTab === 'wispro'"
                :wispro_clients="wispro_clients"
                :filters="filters"
                :pagination="pagination"
            />

            <!-- Tab: Administradores -->
            <AdminsTab
                v-if="activeTab === 'admins'"
                :admins="admins"
                :filters="filters"
                :paginationAdmins="paginationAdmins"
            />
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import LocalClientsTab from './Components/LocalClientsTab.vue'
import WisproClientsTab from './Components/WisproClientsTab.vue'
import AdminsTab from './Components/AdminsTab.vue'

interface WisproClient {
    id: string
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
    data: any[]
    admins: any[]
    filters: {
        local_search?: string
        wispro_search?: string
        admin_search?: string
    }
    pagination: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number
        to: number
    }
    paginationAdmins: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number
        to: number
    }
    wispro_clients: WisproResponse
}>()

const activeTab = ref('sistema')
</script>
