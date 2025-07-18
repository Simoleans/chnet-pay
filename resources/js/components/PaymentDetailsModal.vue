<script setup>
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    open: Boolean,
    payment: Object,
})

const emit = defineEmits(['update:open', 'paymentUpdated'])

const isUpdatingVerification = ref(false)
const localVerificationStatus = ref(false)

// Computed para manejar el estado de verificación sin mutar el prop
const isVerified = computed(() => {
    return props.payment?.verify_payments ?? localVerificationStatus.value
})

const openImage = () => {
    if (props.payment?.image_path) {
        window.open(`/storage/${props.payment.image_path}`, '_blank')
    }
}

const toggleVerification = () => {
    if (!props.payment?.id || isUpdatingVerification.value) return

    isUpdatingVerification.value = true

    router.patch(`/payments/${props.payment.id}/verify`, {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            // Actualizar el estado local
            localVerificationStatus.value = !localVerificationStatus.value

            // Emitir evento para actualizar la lista padre
            emit('paymentUpdated')

            console.log('Verificación actualizada exitosamente')
        },
        onError: (errors) => {
            console.error('Error al actualizar la verificación:', errors)
        },
        onFinish: () => {
            isUpdatingVerification.value = false
        }
    })
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Detalles del Pago</DialogTitle>
                <DialogDescription>
                    Información completa del pago seleccionado
                </DialogDescription>
            </DialogHeader>

            <div v-if="payment" class="space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-muted-foreground">Referencia:</span>
                        <p class="font-mono">{{ payment.reference || 'Sin referencia' }}</p>
                    </div>
                    <div>
                        <span class="font-medium text-muted-foreground">ID Pago:</span>
                        <p class="font-mono">#{{ payment.id }}</p>
                    </div>
                    <div>
                        <span class="font-medium text-muted-foreground">Cliente:</span>
                        <p>{{ payment.user_name }}</p>
                        <p class="text-xs text-muted-foreground">Código: {{ payment.user_code }}</p>
                    </div>
                    <div>
                        <span class="font-medium text-muted-foreground">Monto:</span>
                        <p class="font-bold text-green-600">${{ Number(payment.amount).toFixed(2) }}</p>
                        <p class="text-xs text-muted-foreground">
                            Bs. {{ Number(payment.amount_bs).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                        </p>
                    </div>
                    <div>
                        <span class="font-medium text-muted-foreground">Banco:</span>
                        <p>{{ payment.bank }}</p>
                    </div>
                    <div>
                        <span class="font-medium text-muted-foreground">Teléfono:</span>
                        <p>{{ payment.phone }}</p>
                    </div>
                    <div>
                        <span class="font-medium text-muted-foreground">Cédula/RIF:</span>
                        <p>{{ payment.id_number }}</p>
                    </div>
                    <div>
                        <span class="font-medium text-muted-foreground">Fecha de Pago:</span>
                        <p>{{ payment.payment_date }}</p>
                    </div>
                    <div>
                        <span class="font-medium text-muted-foreground">Período:</span>
                        <p>{{ payment.invoice_period }}</p>
                    </div>
                    <div>
                        <span class="font-medium text-muted-foreground">Registrado:</span>
                        <p>{{ payment.created_at }}</p>
                    </div>
                </div>

                <!-- Sección de verificación -->
                <div class="border-t pt-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-medium text-muted-foreground">Estado de Verificación:</span>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="flex items-center gap-2">
                                    <div
                                        :class="[
                                            'w-3 h-3 rounded-full',
                                            isVerified ? 'bg-green-500' : 'bg-red-500'
                                        ]"
                                    ></div>
                                    <span :class="[
                                        'text-sm font-medium',
                                        isVerified ? 'text-green-600' : 'text-red-600'
                                    ]">
                                        {{ isVerified ? 'Verificado' : 'Sin verificar' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <Button
                            :variant="isVerified ? 'destructive' : 'default'"
                            size="sm"
                            :disabled="isUpdatingVerification"
                            @click="toggleVerification"
                        >
                            {{ isUpdatingVerification ? 'Actualizando...' :
                               isVerified ? 'Marcar como no verificado' : 'Marcar como verificado' }}
                        </Button>
                    </div>
                </div>

                <div v-if="payment.image_path" class="border-t pt-4">
                    <span class="font-medium text-muted-foreground">Comprobante:</span>
                    <div class="mt-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openImage"
                        >
                            📎 Ver Comprobante
                        </Button>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <DialogClose asChild>
                    <Button variant="outline">Cerrar</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
