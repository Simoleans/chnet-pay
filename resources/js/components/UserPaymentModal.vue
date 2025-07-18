<script setup lang="ts">
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useNotifications } from '@/composables/useNotifications';
import { useBcvStore } from '@/stores/bcv';
import { storeToRefs } from 'pinia';
import axios from 'axios';

const { notify } = useNotifications();

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

interface Props {
    open?: boolean;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'openReportModal'): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
});

const emit = defineEmits<Emits>();

// Usar usePage para acceder a los datos del usuario
const page = usePage();

// Usar el store de BCV
const bcvStore = useBcvStore();
const { bcv } = storeToRefs(bcvStore);

// Estados para el pago
const paymentLoading = ref(false);
const paymentError = ref(false);
const showReferenceInput = ref(false);
const referenceNumber = ref('');
const paymentAmount = ref('');
const showReportLink = ref(false);

// Watcher para reiniciar estados cuando se abre el modal
watch(() => props.open, (newVal) => {
    if (newVal) {
        resetStates();
    }
});

const resetStates = () => {
    paymentError.value = false;
    showReferenceInput.value = false;
    referenceNumber.value = '';
    paymentAmount.value = '';
    showReportLink.value = false;
};

const copyPaymentReference = async () => {
    console.log('Intentando copiar...', { bcv: bcv.value, user: page.props.auth?.user });

    if (bcv.value && page.props.auth?.user?.plan?.price) {
        const total = (parseFloat(page.props.auth.user.plan.price) * parseFloat(bcv.value)).toFixed(2);

        // Formato mejorado de los datos bancarios
        const bankingData = `📧 CHNET - Datos para Pago Movil

            Banco: 0191
            RIF: J125697857
            Teléfono: 04120355541
            Monto: ${total} Bs`;

        console.log('Datos a copiar:', bankingData);

        try {
            // Intentar usar la API moderna del Clipboard
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(bankingData);
                notify({
                    message: 'Datos copiados correctamente',
                    type: 'success',
                    duration: 1500,
                });
            } else {
                // Fallback para navegadores que no soportan clipboard API
                const textArea = document.createElement('textarea');
                textArea.value = bankingData;
                textArea.style.position = 'fixed';
                textArea.style.left = '-9999px';
                textArea.style.top = '0';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                const successful = document.execCommand('copy');
                document.body.removeChild(textArea);

                if (successful) {
                    notify({
                        message: '✅ Datos bancarios copiados (método compatible)',
                        type: 'success',
                        duration: 2000,
                    });
                } else {
                    throw new Error('Método de copia no disponible');
                }
            }
        } catch (err) {
            console.error('Error al copiar:', err);

            // Mostrar modal con los datos para copiar manualmente
            notify({
                message: '⚠️ No se pudo copiar automáticamente. Revisa la consola para copiar manualmente.',
                type: 'error',
                duration: 4000,
            });

            // Log los datos en consola para que el usuario pueda copiarlos
            console.log('📋 COPIA ESTOS DATOS MANUALMENTE:');
            console.log('=====================================');
            console.log(bankingData);
            console.log('=====================================');

            // También mostrar un alert como último recurso
            alert(`No se pudo copiar automáticamente. Copia estos datos manualmente:\n\n${bankingData}`);
        }
    } else {
        notify({
            message: '❌ No hay datos disponibles para copiar. Verifica que tengas un plan asignado y que la tasa BCV esté cargada.',
            type: 'error',
            duration: 3000,
        });
    }
};

const handleReportManually = () => {
    emit('update:open', false);
    emit('openReportModal');
};

const checkPayment = async () => {
    paymentLoading.value = true;
    paymentError.value = false;
    showReferenceInput.value = true;
    showReportLink.value = false;

    // Si no hay monto, usar el del plan
    if (!paymentAmount.value && bcv.value && page.props.auth?.user?.plan?.price) {
        paymentAmount.value = (parseFloat(page.props.auth.user.plan.price) * parseFloat(bcv.value)).toFixed(2);
    }

    try {
        if (referenceNumber.value.trim()) {
            console.log('LOG:: Validando referencia:', referenceNumber.value);
            console.log('LOG:: Monto esperado:', paymentAmount.value);

            const res = await axios.post('/api/bnc/validate-and-store-payment', {
                reference: referenceNumber.value,
                amount: parseFloat(paymentAmount.value)
            });

            console.log('LOG:: Respuesta de validación:', res.data);

            if (res.data.success) {
                notify({
                    message: res.data.message,
                    type: 'success',
                    duration: 2000,
                });
                resetStates();
                emit('update:open', false);
            } else {
                showReportLink.value = res.data.showReportLink;
                notify({
                    message: res.data.message,
                    type: 'warning',
                    duration: 4000,
                });
            }
        }
    } catch (err: any) {
        console.error('LOG:: Error al validar referencia:', err);
        paymentError.value = true;
        notify({
            message: err.response?.data?.error || 'Error al validar la referencia. Por favor, intente nuevamente.',
            type: 'error',
            duration: 3000,
        });
    } finally {
        paymentLoading.value = false;
    }
};

const submitReference = async () => {
    if (referenceNumber.value.trim()) {
        await checkPayment();
    } else {
        notify({
            message: 'Por favor ingrese un número de referencia válido',
            type: 'error',
            duration: 2000,
        });
    }
};

const handleOpenChange = (open: boolean) => {
    emit('update:open', open);
    if (!open) {
        resetStates();
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Pagar Plan</DialogTitle>
                <DialogDescription>
                    Datos para realizar el pago de tu plan
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4">
                <input type="hidden" :value="$page.props.auth.user?.id" />

                <div class="space-y-2">
                    <p class="font-medium">🏦 Banco Nacional de Crédito</p>
                    <p class="text-sm"><span class="font-medium">👤 RIF:</span> J-12569785-7</p>
                    <p class="text-sm"><span class="font-medium">📞 Teléfono:</span> 0412-0355541</p>
                    <p class="text-sm">
                        <span class="font-medium">💰 Monto a pagar: </span>
                        <span class="text-lg font-bold">
                            {{ bcv && $page.props.auth.user?.plan?.price ?
                                `${(parseFloat($page.props.auth.user.plan.price) * parseFloat(bcv)).toFixed(2)} Bs` :
                                'Calculando...'
                            }}
                        </span>
                    </p>

                    <div class="mt-3 space-y-2">
                        <Button
                            @click="copyPaymentReference"
                            size="sm"
                            variant="outline"
                            :disabled="!bcv || !$page.props.auth.user?.plan?.price"
                            class="w-full"
                        >
                            Copiar datos bancarios
                        </Button>

                        <Button
                            @click="checkPayment"
                            size="sm"
                            :disabled="paymentLoading || !bcv || !$page.props.auth.user?.plan?.price"
                            class="w-full"
                        >
                            {{ paymentLoading ? 'Verificando...' : 'Ya pagué' }}
                        </Button>

                        <div v-if="showReferenceInput" class="space-y-4">
                            <div class="space-y-2">
                                <label for="referenceNumber" class="text-sm font-medium">Número de referencia:</label>
                                <Input
                                    id="referenceNumber"
                                    v-model="referenceNumber"
                                    placeholder="Ingrese el número de referencia del pago"
                                    class="w-full"
                                />
                            </div>

                            <div class="space-y-2">
                                <label for="paymentAmount" class="text-sm font-medium">Monto pagado (Bs):</label>
                                <Input
                                    id="paymentAmount"
                                    type="number"
                                    v-model="paymentAmount"
                                    :placeholder="bcv && $page.props.auth?.user?.plan?.price ?
                                        `Monto sugerido: ${(parseFloat($page.props.auth.user.plan.price) * parseFloat(bcv)).toFixed(2)} Bs` :
                                        'Ingrese el monto en bolívares'"
                                    class="w-full"
                                    step="0.01"
                                    min="0"
                                />
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <Button
                                    @click="submitReference"
                                    size="sm"
                                    :disabled="!referenceNumber.trim() || !paymentAmount || parseFloat(paymentAmount) <= 0"
                                    class="flex-1"
                                >
                                    Verificar Pago
                                </Button>
                                <Button
                                    v-if="showReportLink"
                                    @click="handleReportManually"
                                    size="sm"
                                    variant="link"
                                    class="text-blue-500 hover:text-blue-700"
                                >
                                    Reportar manualmente
                                </Button>
                            </div>
                        </div>
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
