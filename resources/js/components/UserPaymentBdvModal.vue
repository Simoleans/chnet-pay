<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useNotifications } from '@/composables/useNotifications';
import { useBcvStore } from '@/stores/bcv';
import { useBanksStore } from '@/stores/banks';
import { storeToRefs } from 'pinia';
import axios from 'axios';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface Props {
    open?: boolean;
    userPlan?: {
        name: string;
        price: number;
    } | null;
    selectedInvoice?: {
        id: number;
        amount: number;
        invoice_number?: string;
        client_id?: number | string;
    } | null;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    userPlan: null,
    selectedInvoice: null,
});

const emit = defineEmits<Emits>();

const { notify } = useNotifications();
const page = usePage();

// Tasa BCV
const bcvStore = useBcvStore();
const { bcv } = storeToRefs(bcvStore);

// Bancos (misma fuente que el modal BNC)
const banksStore = useBanksStore();
const { banks, loading: banksLoading, error: banksError } = storeToRefs(banksStore as any);

// Datos de pago móvil de la empresa (destino)
const paymentMobile = computed(() => {
    return (page.props.paymentBdv as {
        name?: string;
        banco?: string;
        tlf?: string;
        rif?: string;
    } | undefined) || {};
});

const paymentMobileTlf  = computed(() => paymentMobile.value.tlf  ?? '');
const paymentMobileName = computed(() => paymentMobile.value.name ?? '');
const paymentMobileRif  = computed(() => paymentMobile.value.rif  ?? '');
const paymentMobileBanco = computed(() => paymentMobile.value.banco ?? '');

// Monto sugerido en Bs
const suggestedAmountBs = computed(() => {
    const amountToPay = props.selectedInvoice?.amount || props.userPlan?.price;
    if (bcv.value && amountToPay) {
        return (parseFloat(String(amountToPay)) * parseFloat(String(bcv.value))).toFixed(2);
    }
    return '';
});

const PHONE_PREFIXES = ['0412', '0414', '0424', '0426', '0416'];

// ── Estados del formulario P2P ───────────────────────────────────────────────
const loading       = ref(false);
const showP2PForm   = ref(false);

// Campos
const p2pCedula          = ref('');
const p2pTelefonoPrefix  = ref('0412');
const p2pTelefonoNumber  = ref('');
const p2pBancoOrigen     = ref('');
const p2pReferencia      = ref('');
const p2pFecha           = ref('');
const p2pImporte         = ref('');

// ── Helpers de fecha ─────────────────────────────────────────────────────────
const setToday = () => {
    p2pFecha.value = new Date().toISOString().split('T')[0];
};
const setYesterday = () => {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    p2pFecha.value = d.toISOString().split('T')[0];
};

// ── Copiar datos bancarios ───────────────────────────────────────────────────
const copyPaymentData = async () => {
    const amountToPay = props.selectedInvoice?.amount || props.userPlan?.price;
    if (!bcv.value || !amountToPay) {
        notify({
            message: 'No hay datos disponibles para copiar. Verifica que tengas un plan asignado y la tasa BCV cargada.',
            type: 'error',
            duration: 3000,
        });
        return;
    }

    const total = (parseFloat(String(amountToPay)) * parseFloat(String(bcv.value))).toFixed(2);
    const text = `📧 ${paymentMobileName.value} - Datos para Pago Móvil

Banco: ${paymentMobileBanco.value}
RIF: ${paymentMobileRif.value}
Teléfono: ${paymentMobileTlf.value}
Monto: ${total} Bs`;

    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            if (!ok) throw new Error('execCommand falló');
        }
        notify({ message: 'Datos copiados correctamente', type: 'success', duration: 1500 });
    } catch {
        alert(`Copia estos datos manualmente:\n\n${text}`);
    }
};

// ── Reset ────────────────────────────────────────────────────────────────────
const resetStates = () => {
    loading.value            = false;
    showP2PForm.value        = false;
    p2pCedula.value          = '';
    p2pTelefonoPrefix.value  = '0412';
    p2pTelefonoNumber.value  = '';
    p2pBancoOrigen.value     = '';
    p2pReferencia.value      = '';
    p2pFecha.value           = '';
    p2pImporte.value         = '';
};

// ── Abrir sección P2P ────────────────────────────────────────────────────────
const openP2PForm = () => {
    showP2PForm.value = true;

    if (!banks.value || banks.value.length === 0) {
        banksStore.loadBanks();
    }

    // Pre-cargar cédula del usuario
    const user = page.props.auth?.user as any;
    p2pCedula.value = user?.id_number ?? '';

    // Pre-cargar fecha de hoy
    if (!p2pFecha.value) {
        p2pFecha.value = new Date().toISOString().split('T')[0];
    }

    // Pre-cargar monto
    const amountToPay = props.selectedInvoice?.amount || props.userPlan?.price;
    if (bcv.value && amountToPay) {
        p2pImporte.value = (parseFloat(String(amountToPay)) * parseFloat(String(bcv.value))).toFixed(2);
    }
};

// ── Validar y enviar ─────────────────────────────────────────────────────────
const submitP2P = async () => {
    // Validaciones básicas
    if (!p2pCedula.value.trim()) {
        notify({ message: 'Ingrese su cédula (ej: V23795133)', type: 'error', duration: 2500 });
        return;
    }
    if (!/^\d{7}$/.test(p2pTelefonoNumber.value.trim())) {
        notify({ message: 'El teléfono debe tener exactamente 7 dígitos después del prefijo', type: 'error', duration: 2500 });
        return;
    }
    const telefonoPagadorFull = p2pTelefonoPrefix.value + p2pTelefonoNumber.value.trim();
    if (!p2pBancoOrigen.value) {
        notify({ message: 'Seleccione el banco de origen', type: 'error', duration: 2500 });
        return;
    }
    if (!/^\d{4}$/.test(p2pReferencia.value.trim())) {
        notify({ message: 'La referencia debe tener exactamente 4 dígitos', type: 'error', duration: 2500 });
        return;
    }
    if (!p2pFecha.value) {
        notify({ message: 'Seleccione la fecha del pago', type: 'error', duration: 2500 });
        return;
    }
    if (!p2pImporte.value || parseFloat(p2pImporte.value) <= 0) {
        notify({ message: 'El monto no es válido', type: 'error', duration: 2500 });
        return;
    }
    if (!paymentMobileTlf.value) {
        notify({ message: 'No hay teléfono destino configurado. Contacte al administrador.', type: 'error', duration: 3000 });
        return;
    }

    loading.value = true;
    try {
        const payload: Record<string, any> = {
            cedulaPagador:   p2pCedula.value.trim().toUpperCase(),
            telefonoPagador: telefonoPagadorFull,
            telefonoDestino: paymentMobileTlf.value.trim(),
            referencia:      p2pReferencia.value.trim(),
            fechaPago:       p2pFecha.value,
            importe:         parseFloat(p2pImporte.value).toFixed(2),
            bancoOrigen:     p2pBancoOrigen.value,
            reqCed:          false,
        };

        // Agregar invoice_id y client_id si hay factura seleccionada
        if (props.selectedInvoice?.id) {
            payload.invoice_id = String(props.selectedInvoice.id);
        }
        const user = page.props.auth?.user as any;
        if (props.selectedInvoice?.client_id) {
            payload.client_id = String(props.selectedInvoice.client_id);
        } else if (user?.id_wispro) {
            payload.client_id = String(user.id_wispro);
        }

        const res = await axios.post('/api/bdv/verify', payload);

        if (res.data?.success) {
            const mensaje = res.data?.already_registered
                ? 'Este pago ya fue verificado anteriormente. No puede registrarlo dos veces.'
                : 'Su pago ha sido verificado y registrado correctamente.';

            notify({
                message: mensaje,
                type: res.data?.already_registered ? 'warning' : 'success',
                duration: 3500,
            });
            resetStates();
            emit('update:open', false);
            setTimeout(() => window.location.reload(), 1500);
        } else {
            notify({
                message: res.data?.message || 'El banco no pudo verificar el pago.',
                type: 'warning',
                duration: 4000,
            });
        }
    } catch (e: any) {
        const msg =
            e?.response?.data?.message ??
            e?.response?.data?.error ??
            e?.message ??
            'Error al verificar el pago con el banco.';
        notify({ message: msg, type: 'error', duration: 3500 });
    } finally {
        loading.value = false;
    }
};

// ── Ciclo de vida ────────────────────────────────────────────────────────────
watch(() => props.open, (val: boolean) => {
    if (!val) resetStates();
});

const handleOpenChange = (open: boolean) => {
    emit('update:open', open);
    if (!open) resetStates();
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-lg md:max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <img src="/img/bdv.webp" alt="BDV" class="h-8 w-8 object-contain" />
                    <div>
                        <DialogTitle>Pagar con Banco de Venezuela</DialogTitle>
                        <DialogDescription>Verificación de pago móvil (P2P)</DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Datos de destino (empresa) -->
                <div class="space-y-1 text-sm">
                    <p class="font-medium">🏦 {{ paymentMobileName }}</p>
                    <p><span class="font-medium">👤 RIF:</span> {{ paymentMobileRif }}</p>
                    <p><span class="font-medium">📞 Teléfono destino:</span> {{ paymentMobileTlf }}</p>
                </div>

                <!-- Copiar datos bancarios -->
                <Button
                    @click="copyPaymentData"
                    size="sm"
                    variant="outline"
                    :disabled="!bcv || !(selectedInvoice?.amount || userPlan?.price)"
                    class="w-full"
                >
                    📋 Copiar datos bancarios
                </Button>

                <!-- Monto a pagar -->
                <div class="bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-950/20 dark:to-rose-950/20 border-2 border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="flex flex-col items-center justify-center space-y-1">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">💰 Monto Exacto a Pagar</p>
                        <div v-if="bcv && (selectedInvoice?.amount || userPlan?.price)" class="text-center">
                            <p class="text-4xl font-bold text-red-600 dark:text-red-400">
                                {{ suggestedAmountBs }} Bs
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                (${{ parseFloat(String(selectedInvoice?.amount || userPlan?.price || 0)).toFixed(2) }} USD × {{ parseFloat(String(bcv)).toFixed(2) }} Bs)
                            </p>
                        </div>
                        <div v-else class="text-center">
                            <p class="text-lg text-gray-500">Calculando monto...</p>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <!-- Reportar Pago Móvil -->
                    <Button
                        @click="openP2PForm"
                        size="lg"
                        :variant="showP2PForm ? 'default' : 'secondary'"
                        :disabled="!bcv || !(selectedInvoice?.amount || userPlan?.price)"
                        :class="[
                            'w-full h-14 text-base font-semibold transition-all',
                            showP2PForm
                                ? 'ring-2 ring-primary shadow-lg scale-105'
                                : 'border-2 border-muted-foreground/30 hover:border-primary/50',
                        ]"
                    >
                        <span class="flex items-center justify-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            Reportar Pago Móvil
                        </span>
                    </Button>

                    <!-- C2P — Deshabilitado -->
                    <div class="relative">
                        <span class="absolute -top-2 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-amber-100 dark:bg-amber-950/60 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-400 shadow-sm ring-1 ring-amber-200 dark:ring-amber-800 z-10">
                            Próximamente
                        </span>
                        <Button
                            size="lg"
                            variant="secondary"
                            disabled
                            class="w-full h-14 text-base font-semibold opacity-50 cursor-not-allowed border-2 border-muted-foreground/30"
                        >
                            <span class="flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                                    <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd" />
                                </svg>
                                Pagar C2P
                            </span>
                        </Button>
                    </div>
                </div>

                <!-- Formulario P2P -->
                <div v-if="showP2PForm" class="border rounded-lg p-4 space-y-4">
                    <p class="font-medium text-sm">Datos del pago móvil P2P</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <!-- Columna izquierda -->
                        <div class="space-y-3">
                            <!-- Cédula pagador -->
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Cédula pagador</label>
                                <Input
                                    v-model="p2pCedula"
                                    placeholder="V23795133"
                                    class="w-full uppercase text-sm"
                                />
                                <p class="text-xs text-muted-foreground">Formato: V o E seguido de números</p>
                            </div>

                            <!-- Teléfono pagador -->
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Teléfono pagador</label>
                                <div class="flex gap-2">
                                    <select
                                        v-model="p2pTelefonoPrefix"
                                        class="border rounded-md p-2 bg-background text-sm w-24 shrink-0"
                                    >
                                        <option v-for="p in PHONE_PREFIXES" :key="p" :value="p">{{ p }}</option>
                                    </select>
                                    <Input
                                        v-model="p2pTelefonoNumber"
                                        placeholder="1234567"
                                        class="flex-1 text-sm"
                                        type="text"
                                        maxlength="7"
                                        inputmode="numeric"
                                        pattern="[0-9]{7}"
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">7 dígitos — número registrado en tu pago móvil</p>
                            </div>

                            <!-- Banco origen -->
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Banco de origen</label>
                                <select
                                    v-model="p2pBancoOrigen"
                                    class="w-full border rounded-md p-2 bg-background text-sm"
                                >
                                    <option value="" disabled>Seleccione su banco</option>
                                    <option
                                        v-for="b in banks"
                                        :key="b.Code"
                                        :value="String(b.Code).padStart(4, '0')"
                                    >
                                        {{ String(b.Code).padStart(4, '0') }} - {{ b.Name }}
                                    </option>
                                </select>
                                <p v-if="banksLoading" class="text-xs text-muted-foreground">Cargando bancos...</p>
                                <p v-if="banksError" class="text-xs text-red-500">{{ banksError }}</p>
                            </div>
                        </div>

                        <!-- Columna derecha -->
                        <div class="space-y-3">
                            <!-- Referencia -->
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Últimos 4 dígitos de la referencia</label>
                                <Input
                                    v-model="p2pReferencia"
                                    placeholder="Ej: 2908"
                                    class="w-full text-sm"
                                    type="text"
                                    maxlength="4"
                                    pattern="[0-9]{4}"
                                    inputmode="numeric"
                                />
                                <p class="text-xs text-muted-foreground">Solo los últimos 4 dígitos del comprobante</p>
                            </div>

                            <!-- Fecha -->
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Fecha del pago</label>
                                <Input
                                    v-model="p2pFecha"
                                    type="date"
                                    class="w-full text-sm"
                                />
                                <div class="flex gap-2">
                                    <Button @click="setToday" size="sm" variant="outline" type="button" class="flex-1 text-xs">
                                        Hoy
                                    </Button>
                                    <Button @click="setYesterday" size="sm" variant="outline" type="button" class="flex-1 text-xs">
                                        Ayer
                                    </Button>
                                </div>
                            </div>

                            <!-- Monto -->
                            <div class="space-y-1">
                                <label class="text-sm font-medium">Monto (Bs)</label>
                                <Input
                                    v-model="p2pImporte"
                                    type="text"
                                    readonly
                                    class="w-full font-bold bg-green-50 dark:bg-green-950/10 border-green-300 dark:border-green-700 text-center text-lg cursor-not-allowed"
                                />
                                <div class="bg-yellow-50 dark:bg-yellow-950/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-2">
                                    <p class="text-xs text-yellow-800 dark:text-yellow-300 font-medium">
                                        ⚠️ Paga exactamente este monto para que la verificación sea exitosa.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Botón enviar -->
                    <Button
                        @click="submitP2P"
                        class="w-full"
                        :disabled="
                            loading ||
                            !p2pCedula.trim() ||
                            !/^\d{7}$/.test(p2pTelefonoNumber.trim()) ||
                            !p2pBancoOrigen ||
                            !/^\d{4}$/.test(p2pReferencia.trim()) ||
                            !p2pFecha ||
                            !p2pImporte ||
                            parseFloat(p2pImporte) <= 0
                        "
                    >
                        <span v-if="loading">Verificando con el banco...</span>
                        <span v-else>Verificar Pago con BDV</span>
                    </Button>
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
