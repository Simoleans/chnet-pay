<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button variant="default" @click="isOpen = true">Crear Trabajador</Button>
        </DialogTrigger>
        <DialogContent class="max-w-2xl">
            <DialogHeader class="space-y-3">
                <DialogTitle>Crear Nuevo Trabajador</DialogTitle>
                <DialogDescription>
                    Crea un nuevo trabajador/administrador del sistema.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-6" @submit.prevent="submitForm">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="name">Nombre Completo</Label>
                        <Input
                            type="text"
                            id="name"
                            v-model="form.name"
                            placeholder="Ingrese el nombre completo"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="email">Email</Label>
                        <Input
                            type="email"
                            id="email"
                            v-model="form.email"
                            placeholder="usuario@ejemplo.com"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="phone">Teléfono</Label>
                        <Input
                            type="text"
                            id="phone"
                            v-model="form.phone"
                            placeholder="Ingrese el teléfono"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="nationality">Nacionalidad</Label>
                        <select
                            id="nationality"
                            v-model="form.nationality"
                            class="w-full p-2 border rounded-md"
                            required
                        >
                            <option value="V">V - Venezolano</option>
                            <option value="E">E - Extranjero</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <Label for="id_number">Número de Cédula</Label>
                        <Input
                            type="text"
                            id="id_number"
                            v-model="form.id_number"
                            placeholder="12345678"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="password">Contraseña</Label>
                        <Input
                            type="password"
                            id="password"
                            v-model="form.password"
                            placeholder="Contraseña"
                            required
                        />
                    </div>

                    <div class="space-y-2 col-span-2">
                        <Label for="address">Dirección</Label>
                        <textarea
                            id="address"
                            v-model="form.address"
                            class="w-full p-2 border rounded-md"
                            rows="3"
                            placeholder="Ingrese la dirección completa"
                        ></textarea>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" @click="closeModal">Cancelar</Button>
                    </DialogClose>
                    <Button variant="default" type="submit" :disabled="form.processing">
                        Crear Trabajador
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
import { ref } from 'vue'

const isOpen = ref(false)

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    nationality: 'V', // Por defecto venezolano
    id_number: '',
    password: '',
    role: 1, // Por defecto admin/trabajador
    status: true // Por defecto activo
})

const closeModal = () => {
    isOpen.value = false
    form.reset()
}

const submitForm = () => {
    console.log('Datos a enviar:', form.data())
    form.post(route('users.store'), {
        onSuccess: () => {
            closeModal()
        },
    })
}
</script>
