<script setup lang="ts">
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useNotifications } from '@/composables/useNotifications';
import { useBcvStore } from '@/stores/bcv';
import { storeToRefs } from 'pinia';
import axios from 'axios';
import { useBanksStore } from '@/stores/banks';
import { computed } from 'vue';

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

// Store de bancos
const banksStore = useBanksStore();
const { banks, loading: banksLoading, error: banksError } = storeToRefs(banksStore as any);

// Estados para el pago
const paymentLoading = ref(false);
const paymentError = ref(false);
const showReferenceInput = ref(false);
const referenceNumber = ref('');
const paymentAmount = ref('');
const showReportLink = ref(false);
// Estados para validación manual
const manualBankCode = ref('0191');
const manualPhone = ref('');
// Estados para C2P
const showC2PSection = ref(false);
const c2pBankCode = ref('');
const c2pToken = ref('');
const c2pId = ref('');
const c2pPhone = ref('');

const suggestedAmountBs = computed(() => {
    if (bcv.value && page.props.auth?.user?.plan?.price) {
        return (parseFloat(page.props.auth.user.plan.price) * parseFloat(bcv.value)).toFixed(2);
    }
    return '';
});

// Watcher para reiniciar estados cuando se abre el modal
watch(() => props.open, (newVal) => {
    if (newVal) {
        resetStates();
        // Cargar bancos si no están cargados
        if (!banks.value || banks.value.length === 0) {
            banksStore.loadBanks();
        }
        // Inicializar banco por defecto si existe
        if (banks.value && banks.value.length > 0) {
            c2pBankCode.value = String(banks.value[0].Code || '');
        }
    }
});

// Watcher para formatear la cédula automáticamente (eliminar guiones)
watch(c2pId, (newVal: string) => {
    if (newVal) {
        // Eliminar guiones y formatear a VXXXX o EXXXX
        const formatted = newVal.trim().toUpperCase().replace(/-/g, '');
        if (formatted !== newVal) {
            c2pId.value = formatted;
        }
    }
});

const resetStates = () => {
    paymentError.value = false;
    showReferenceInput.value = false;
    referenceNumber.value = '';
    paymentAmount.value = '';
    showReportLink.value = false;
    manualBankCode.value = '0191';
    manualPhone.value = '';
    showC2PSection.value = false;
};

const openC2PSection = () => {
    showC2PSection.value = true;
    if (!banks.value || banks.value.length === 0) {
        banksStore.loadBanks();
    }
    // precargar datos del usuario
    c2pId.value = page.props.auth?.user?.id_number || '';
    //c2pPhone.value = page.props.auth?.user?.phone || '';
    showReferenceInput.value = false;
    // cargar/actualizar BCV
    if (typeof (bcvStore as any).$reloadBcvAmount === 'function') {
        (bcvStore as any).$reloadBcvAmount();
    }
};

const sendC2P = async () => {
    try {
        if (!c2pBankCode.value || !c2pToken.value || !c2pId.value || !c2pPhone.value) {
            notify({ message: 'Complete los datos del C2P', type: 'error', duration: 2000 });
            return;
        }

        // Validación de cédula: V00000000 o E00000000 (sin guiones)
        const idPattern = /^[VE][0-9]+$/i;
        const normalized = c2pId.value.trim().toUpperCase().replace(/[^VE0-9]/g, '');
        if (!idPattern.test(normalized)) {
            notify({ message: 'La cédula debe ser en formato V00000000 o E00000000', type: 'error', duration: 2500 });
            return;
        }
        c2pId.value = normalized;

        // Validación de teléfono: 10 dígitos, luego agregamos el 58
        let phoneDigits = c2pPhone.value.replace(/\D/g, '');

        // Si comienza con 0, quitarlo (0412 -> 412)
        if (phoneDigits.startsWith('0')) {
            phoneDigits = phoneDigits.substring(1);
        }

        if (!/^\d{10}$/.test(phoneDigits)) {
            notify({ message: `Teléfono inválido. Debe tener 10 dígitos (recibido: ${phoneDigits.length}). Ejemplo: 4120355541 o 04120355541`, type: 'error', duration: 3000 });
            return;
        }
        // Agregar el prefijo 58 automáticamente
        const phoneWithPrefix = '58' + phoneDigits;

        // Calcular monto exacto como en el otro modal
        const hasPrice = !!page.props.auth?.user?.plan?.price;
        const hasBcv = bcv.value !== null && bcv.value !== undefined;
        if (!hasPrice || !hasBcv) {
            notify({ message: 'No se pudo calcular el monto. Verifique su plan o la tasa BCV.', type: 'error', duration: 2500 });
            return;
        }
        const amountStr = (parseFloat(page.props.auth.user.plan.price) * parseFloat(String(bcv.value))).toFixed(2);

        const res = await axios.post('/api/bnc/send-c2p', {
            debtor_bank_code: parseInt(c2pBankCode.value, 10),
            token: c2pToken.value,
            amount: parseFloat(amountStr),
            debtor_id: c2pId.value,
            debtor_phone: phoneWithPrefix,
        });

        if (res.data?.success) {
            notify({ message: res.data.message || 'C2P enviado', type: 'success', duration: 2500 });
            emit('update:open', false);
            resetStates();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            notify({ message: res.data?.error || res.data?.message || 'No se pudo enviar C2P', type: 'error', duration: 3000 });
        }
    } catch (e: any) {
        const msg = e?.response?.data?.message ?? e?.response?.data?.error ?? e?.message ?? 'Error al enviar C2P';
        notify({ message: msg, type: 'error', duration: 3000 });
    }
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
    showC2PSection.value = false;

    // Cargar bancos si no están cargados
    if (!banks.value || banks.value.length === 0) {
        banksStore.loadBanks();
    }

    // Precargar teléfono del usuario si existe
    if (page.props.auth?.user?.phone && !manualPhone.value) {
        manualPhone.value = page.props.auth.user.phone;
    }

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
                amount: parseFloat(paymentAmount.value),
                bank: manualBankCode.value,
                phone: manualPhone.value
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

                setTimeout(() => {
                    window.location.reload();
                }, 1500);
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
    const trimmedRef = referenceNumber.value.trim();

    if (!trimmedRef) {
        notify({
            message: 'Por favor ingrese los últimos 5 números de la referencia',
            type: 'error',
            duration: 2000,
        });
        return;
    }

    if (!/^\d{5}$/.test(trimmedRef)) {
        notify({
            message: 'La referencia debe tener exactamente 5 números',
            type: 'error',
            duration: 2000,
        });
        return;
    }

    await checkPayment();
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
        <DialogContent class="sm:max-w-lg md:max-w-2xl max-h-[90vh] overflow-y-auto">
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

                        <!-- <Button
                            @click="checkPayment"
                            size="sm"
                            :disabled="paymentLoading || !bcv || !$page.props.auth.user?.plan?.price"
                            class="w-full"
                        >
                            {{ paymentLoading ? 'Verificando...' : 'Ya pagué' }}
                        </Button> -->
                        <Button
                            @click="checkPayment"
                            size="sm"
                            :disabled="paymentLoading  || !bcv || !$page.props.auth.user?.plan?.price || showC2PSection"
                            class="w-full"
                        >
                            {{ paymentLoading ? 'Verificando...' : 'Ya pagué' }}
                        </Button>

                        <!-- <Button
                            @click="openC2PSection"
                            size="sm"
                            variant="outline"
                            :disabled="!bcv || !$page.props.auth.user?.plan?.price"
                            class="w-full"
                        >
                            Pagar C2P
                        </Button> -->
                        <Button
                            @click="openC2PSection"
                            size="sm"
                            variant="outline"
                            :disabled="!bcv || !$page.props.auth.user?.plan?.price"
                            class="w-full"
                        >
                            Pagar C2P
                        </Button>

                        <div v-if="showReferenceInput" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <!-- Columna izquierda -->
                                <div class="space-y-3">
                                    <div class="space-y-2">
                                        <label for="manualBankCode" class="text-sm font-medium">Banco emisor</label>
                                        <select v-model="manualBankCode" id="manualBankCode" class="w-full border rounded-md p-2 bg-background text-sm">
                                            <option value="" disabled>Seleccione un banco</option>
                                            <option v-for="b in banks" :key="b.Code" :value="String(b.Code).padStart(4, '0')">
                                                {{ String(b.Code).padStart(4, '0') }} - {{ b.Name }}
                                            </option>
                                        </select>
                                        <p v-if="banksLoading" class="text-xs text-muted-foreground">Cargando bancos...</p>
                                        <p v-if="banksError" class="text-xs text-red-500">{{ banksError }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <label for="manualPhone" class="text-sm font-medium">Teléfono</label>
                                        <Input
                                            id="manualPhone"
                                            v-model="manualPhone"
                                            placeholder="04120355541"
                                            class="w-full text-sm"
                                            type="text"
                                        />
                                        <p class="text-xs text-muted-foreground">Teléfono registrado en el pago móvil</p>
                                    </div>
                                </div>

                                <!-- Columna derecha -->
                                <div class="space-y-3">
                                    <div class="space-y-2">
                                        <label for="referenceNumber" class="text-sm font-medium">Últimos 5 números de la referencia</label>
                                        <Input
                                            id="referenceNumber"
                                            v-model="referenceNumber"
                                            placeholder="Ingrese los últimos 5 números"
                                            class="w-full text-sm"
                                            type="text"
                                            maxlength="5"
                                            pattern="[0-9]{5}"
                                        />
<!--                                         <p class="text-xs text-muted-foreground">Solo los últimos 5 dígitos</p> -->
                                    </div>

                                    <div class="space-y-2">
                                        <label for="paymentAmount" class="text-sm font-medium">Monto pagado (Bs)</label>
                                        <Input
                                            id="paymentAmount"
                                            type="number"
                                            v-model="paymentAmount"
                                            :placeholder="bcv && $page.props.auth?.user?.plan?.price ?
                                                `Monto sugerido: ${(parseFloat($page.props.auth.user.plan.price) * parseFloat(bcv)).toFixed(2)} Bs` :
                                                'Ingrese el monto en bolívares'"
                                            class="w-full text-sm"
                                            step="0.01"
                                            min="0"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <Button
                                    @click="submitReference"
                                    size="sm"
                                    :disabled="!referenceNumber.trim() || referenceNumber.trim().length < 4 || !paymentAmount || parseFloat(paymentAmount) <= 0 || !manualBankCode || !manualPhone.trim()"
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

                        <div v-if="showC2PSection" class="border rounded-md p-3 mt-2">
                            <p class="font-medium mb-3">Datos C2P</p>

                            <!-- Layout de dos columnas para optimizar espacio -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                                <!-- Columna izquierda -->
                                <div class="space-y-3">
                                    <div class="space-y-1">
                                        <label class="text-sm font-medium">Banco emisor</label>
                                        <select v-model="c2pBankCode" class="w-full border rounded-md p-2 bg-background text-sm">
                                            <option value="" disabled>Seleccione un banco</option>
                                            <option v-for="b in banks" :key="b.Code" :value="String(b.Code)">
                                                {{ b.Code }} - {{ b.Name }}
                                            </option>
                                        </select>
                                        <p v-if="banksLoading" class="text-xs text-muted-foreground">Cargando bancos...</p>
                                        <p v-if="banksError" class="text-xs text-red-500">{{ banksError }}</p>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-sm font-medium">Cédula/RIF</label>
                                        <Input v-model="c2pId" placeholder="V00000000 o E00000000" class="w-full uppercase text-sm" />
                                        <p class="text-xs text-muted-foreground">Formato: V o E seguido de números</p>
                                    </div>


                                </div>

                                <!-- Columna derecha -->
                                <div class="space-y-3">
                                    <div class="space-y-1">
                                        <label class="text-sm font-medium flex items-center gap-2">Teléfono - <p class="text-xs text-muted-foreground">10 dígitos (con o sin 0)</p></label>
                                        <Input v-model="c2pPhone" placeholder="4120355541 o 04120355541" class="w-full text-sm" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-sm font-medium flex items-center gap-2">Token - <p class="text-xs text-muted-foreground">Código enviado por tu banco</p></label>
                                        <Input v-model="c2pToken" placeholder="Código de verificación" class="w-full text-sm" />

                                    </div>

                                </div>
                            </div>

                            <!-- Botón centrado al final del formulario C2P -->
                            <div class="mt-4">
                                <Button
                                    @click="sendC2P"
                                    size="sm"
                                    class="w-full"
                                    :disabled="!c2pToken || !c2pBankCode || !($page.props.auth?.user?.plan?.price && bcv)"
                                >
                                    Enviar C2P
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
