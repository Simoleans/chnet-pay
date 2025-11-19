<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button
                @click="openModal"
                :class="buttonClass"
                :variant="buttonVariant"
            >
                {{ buttonText }}
            </Button>
        </DialogTrigger>
        <DialogContent class="max-w-xl">
            <DialogHeader class="space-y-3">
                <DialogTitle>Editar Cliente</DialogTitle>
                <DialogDescription>
                    Modifica los datos del cliente. 
                    {{ isWisproClient ? '(Este cliente se sincronizará con Wispro)' : '(Cliente local)' }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-6" @submit.prevent="submitForm">
                <div class="grid grid-cols-1 gap-4">
                    <!-- Nombre -->
                    <div class="space-y-2">
                        <Label for="edit-name">Nombre Completo</Label>
                        <Input
                            type="text"
                            id="edit-name"
                            v-model="form.name"
                            placeholder="Ingrese el nombre completo"
                            required
                        />
                    </div>

                    <!-- Email -->
                    <div class="space-y-2">
                        <Label for="edit-email">Email</Label>
                        <Input
                            type="email"
                            id="edit-email"
                            v-model="form.email"
                            placeholder="usuario@ejemplo.com"
                            required
                        />
                    </div>

                    <!-- Teléfono -->
                    <div class="space-y-2">
                        <Label for="edit-phone">Teléfono</Label>
                        <Input
                            type="text"
                            id="edit-phone"
                            v-model="form.phone"
                            placeholder="+58 412-1234567 (Recomendable usar +58)"
                        />
                        <p class="text-xs text-gray-500">Es recomendable incluir el código de país +58</p>
                    </div>

                    <!-- Dirección -->
                    <div class="space-y-2">
                        <Label for="edit-address">Dirección</Label>
                        <textarea
                            id="edit-address"
                            v-model="form.address"
                            class="w-full p-2 border rounded-md"
                            rows="3"
                            placeholder="Ingrese la dirección completa"
                            required
                        ></textarea>
                    </div>

                    <!-- Password (solo para clientes locales) -->
                    <div v-if="!isWisproClient" class="space-y-2">
                        <Label for="edit-password">Nueva Contraseña (opcional)</Label>
                        <Input
                            type="password"
                            id="edit-password"
                            v-model="form.password"
                            placeholder="Dejar vacío para no cambiar"
                        />
                        <p class="text-xs text-gray-500">Solo se puede cambiar para clientes locales</p>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" @click="closeModal">Cancelar</Button>
                    </DialogClose>
                    <Button variant="default" type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Actualizando...' : 'Actualizar Cliente' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogClose, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ref, computed } from 'vue'
import { useNotifications } from '@/composables/useNotifications'

interface User {
    id: number
    name: string
    email: string
    phone: string | null
    address: string | null
    id_wispro: string | null  // Para identificar si es cliente de Wispro
}

const props = defineProps<{
    userData: User
    buttonText?: string
    buttonVariant?: 'default' | 'outline' | 'secondary' | 'ghost' | 'link' | 'destructive'
    buttonClass?: string
}>()

const buttonText = computed(() => props.buttonText || 'Editar')
const buttonVariant = computed(() => props.buttonVariant || 'default')
const buttonClass = computed(() => props.buttonClass || 'bg-green-600 hover:bg-green-700')

const { notify } = useNotifications()
const isOpen = ref(false)

// Detectar si es un cliente de Wispro (tiene id_wispro)
const isWisproClient = computed(() => {
    return props.userData.id_wispro !== null && props.userData.id_wispro !== undefined && props.userData.id_wispro !== ''
})

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    password: ''
})

const openModal = () => {
    // Llenar el formulario con los datos del usuario
    form.name = props.userData.name
    form.email = props.userData.email
    form.phone = props.userData.phone || ''
    form.address = props.userData.address || ''
    form.password = '' // Siempre vacío para edición
    isOpen.value = true
}

const closeModal = () => {
    isOpen.value = false
    form.reset()
}

const submitForm = () => {
    // Usar la ruta update-client que maneja tanto locales como Wispro
    form.put(route('users.update-client', props.userData.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            // Verificar si hay un mensaje de éxito en el flash
            const successMessage = page.props?.flash?.success
            
            if (successMessage) {
                notify({
                    message: successMessage,
                    type: 'success',
                    duration: 4000,
                })
            } else {
                // Mensaje personalizado según el tipo de cliente
                if (isWisproClient.value) {
                    notify({
                        message: '✅ Cliente actualizado exitosamente en el sistema y sincronizado con Wispro',
                        type: 'success',
                        duration: 4000,
                    })
                } else {
                    notify({
                        message: '✅ Cliente actualizado exitosamente en el sistema',
                        type: 'success',
                        duration: 3000,
                    })
                }
            }
            closeModal()
        },
        onError: (errors) => {
            console.error('Error al actualizar:', errors)
            
            // Mostrar mensaje de error
            const errorMessage = errors.wispro || errors.error || 'Error al actualizar el cliente'
            notify({
                message: `❌ ${errorMessage}`,
                type: 'error',
                duration: 4000,
            })
        }
    })
}
</script>
