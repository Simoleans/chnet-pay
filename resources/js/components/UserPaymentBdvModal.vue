<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useBcvStore } from '@/stores/bcv';
import { useBanksStore } from '@/stores/banks';
import { storeToRefs } from 'pinia';
import axios from 'axios';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AmountHero from '@/components/payment/AmountHero.vue';
import CopyRow from '@/components/payment/CopyRow.vue';
import RefInput from '@/components/payment/RefInput.vue';
import DateChips from '@/components/payment/DateChips.vue';
import BankSelect from '@/components/payment/BankSelect.vue';
import AmountConfirm from '@/components/payment/AmountConfirm.vue';
import InlineAlert from '@/components/payment/InlineAlert.vue';
import SuccessView from '@/components/payment/SuccessView.vue';
import VerifyingOverlay from '@/components/payment/VerifyingOverlay.vue';
import type { PaymentAlert, PaymentSuccess } from '@/components/payment/types';
import { usePersistedForm } from '@/composables/usePersistedForm';
import { localIsoDate } from '@/lib/dates';
import { formatBs } from '@/lib/money';
import { ArrowLeft, KeyRound, ShieldCheck, Smartphone } from 'lucide-vue-next';
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
        id?: number | string;
        invoice_ids?: string[];
        amount?: number;
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

// ── Alertas DENTRO de la hoja (UI) ──────────────────────────────────────────
// Errores/avisos fijos hasta "Entendido"; éxito con pantalla propia.
// La firma de notify() es la misma, así el resto del código no cambia.
const paymentAlert = ref<PaymentAlert | null>(null);
const paymentSuccess = ref<PaymentSuccess | null>(null);
let alertTimer: ReturnType<typeof setTimeout> | null = null;
const notify = ({ message, type = 'success' }: { message: string; type?: PaymentAlert['type']; duration?: number }) => {
    if (alertTimer) clearTimeout(alertTimer);
    paymentAlert.value = { type, message };
    if (type === 'success' || type === 'info') {
        alertTimer = setTimeout(() => (paymentAlert.value = null), 2500);
    }
};
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

const invoiceIdsForWispro = computed((): string[] => {
    const inv = props.selectedInvoice;
    if (!inv) return [];
    if (inv.invoice_ids && inv.invoice_ids.length > 0) {
        return inv.invoice_ids.map((id) => String(id));
    }
    if (inv.id != null && inv.id !== '') {
        return [String(inv.id)];
    }
    return [];
});

const isBatchInvoicePay = computed(() => invoiceIdsForWispro.value.length > 1);

const amountUsdPrimary = computed(() => {
    if (props.selectedInvoice?.amount != null && props.selectedInvoice.amount !== '') {
        return parseFloat(String(props.selectedInvoice.amount));
    }
    if (props.userPlan?.price != null) {
        return parseFloat(String(props.userPlan.price));
    }
    return 0;
});

// Monto sugerido en Bs
const suggestedAmountBs = computed(() => {
    const amountToPay = amountUsdPrimary.value;
    if (bcv.value && amountToPay) {
        return (parseFloat(String(amountToPay)) * parseFloat(String(bcv.value))).toFixed(2);
    }
    return '';
});

const PHONE_PREFIXES = ['0412', '0414', '0424', '0426', '0416','0422'];

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

const parsePaymentAmount = (value: string) => {
    const amount = Number(value.trim().replace(',', '.'));
    return Number.isFinite(amount) ? amount : 0;
};

const enteredP2PAmountBs = computed(() => {
    return parsePaymentAmount(p2pImporte.value);
});

// ── Helpers de fecha ─────────────────────────────────────────────────────────
// Fechas locales (antes toISOString = UTC: desde las 8 pm daba el día siguiente)
const setToday = () => {
    p2pFecha.value = localIsoDate(0);
};
const setYesterday = () => {
    p2pFecha.value = localIsoDate(-1);
};

// ── Copiar datos bancarios ───────────────────────────────────────────────────
const copyPaymentData = async () => {
    const amountToPay = amountUsdPrimary.value;
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
    const shouldPrefillP2PAmount = !showP2PForm.value && !p2pImporte.value;

    showP2PForm.value = true;

    if (!banks.value || banks.value.length === 0) {
        banksStore.loadBanks();
    }

    // Pre-cargar cédula del usuario
    const user = page.props.auth?.user as any;
    p2pCedula.value = user?.id_number ?? '';

    // Pre-cargar fecha de hoy
    if (!p2pFecha.value) {
        p2pFecha.value = localIsoDate(0); // fecha local (antes UTC)
    }

    // Precargar solo al abrir; luego se respeta el monto escrito por el usuario.
    const amountToPay = amountUsdPrimary.value;
    if (shouldPrefillP2PAmount && bcv.value && amountToPay) {
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
    if (!/^\d{5}$/.test(p2pReferencia.value.trim())) {
        notify({ message: 'La referencia debe tener exactamente 5 dígitos', type: 'error', duration: 2500 });
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
    // Se permite reportar el monto ingresado por el usuario, aunque sea menor al monto oficial.
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
            importe:         enteredP2PAmountBs.value.toFixed(2),
            bancoOrigen:     p2pBancoOrigen.value,
            reqCed:          false,
        };

        const ids = invoiceIdsForWispro.value;
        if (ids.length === 1) {
            payload.invoice_id = ids[0];
        } else if (ids.length > 1) {
            payload.invoice_ids = ids;
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

            // UI: pantalla de resultado; la recarga ocurre cuando la persona toca "Listo"
            paymentSuccess.value = {
                title: res.data?.already_registered ? 'Este pago ya estaba registrado' : '¡Pago verificado!',
                message: mensaje,
                tone: res.data?.already_registered ? 'warning' : 'success',
                amountBs: payload.importe,
                reference: payload.referencia,
                date: payload.fechaPago,
            };
            persisted.clear();
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
    // UI: no se puede cerrar mientras el banco está respondiendo
    if (!open && loading.value) return;
    // UI: si el pago ya se verificó, al cerrar refrescamos facturas
    if (!open && paymentSuccess.value) {
        window.location.reload();
        return;
    }
    emit('update:open', open);
    if (!open) resetStates();
};
// ── Ayudas solo de interfaz (no modifican payloads ni endpoints) ─────────────
const goBack = () => {
    showP2PForm.value = false;
};

// Sin maxlength en el HTML: así al pegar "765-4321" se limpia primero y luego se recorta.
// Solo dígitos en el teléfono (la referencia la maneja <RefInput>)
const onlyDigits = (v: string, max: number) => v.replace(/\D/g, '').slice(0, max);
watch(p2pTelefonoNumber, (v) => { const c = onlyDigits(v, 7); if (c !== v) p2pTelefonoNumber.value = c; });

const canSubmitP2P = computed(() =>
    !loading.value &&
    !!p2pCedula.value.trim() &&
    /^\d{7}$/.test(p2pTelefonoNumber.value.trim()) &&
    !!p2pBancoOrigen.value &&
    /^\d{5}$/.test(p2pReferencia.value.trim()) &&
    !!p2pFecha.value &&
    !!p2pImporte.value &&
    enteredP2PAmountBs.value > 0
);

const amountLabel = computed(() =>
    isBatchInvoicePay.value ? 'Total de tus facturas' : props.selectedInvoice ? 'Total de la factura' : 'Total de tu plan'
);

const fieldBase =
    'h-12 rounded-xl border border-input bg-background px-3 text-[15px] shadow-xs outline-none transition focus:border-foreground focus:ring-2 focus:ring-foreground/10';
// ── Recordar lo escrito si la persona sale a la app del banco (localStorage) ──
const persistKey = () => {
    const uid = (page.props.auth as any)?.user?.id;
    if (!uid) return null;
    return `chnet:pm:bdv:${uid}:${invoiceIdsForWispro.value.join('-') || 'plan'}`;
};
const persisted = usePersistedForm({
    key: persistKey,
    enabled: () => props.open && !paymentSuccess.value,
    sources: [showP2PForm, p2pCedula, p2pTelefonoPrefix, p2pTelefonoNumber, p2pBancoOrigen, p2pReferencia, p2pFecha, p2pImporte],
    snapshot: () => ({
        showP2PForm: showP2PForm.value,
        p2pCedula: p2pCedula.value,
        p2pTelefonoPrefix: p2pTelefonoPrefix.value,
        p2pTelefonoNumber: p2pTelefonoNumber.value,
        p2pBancoOrigen: p2pBancoOrigen.value,
        p2pReferencia: p2pReferencia.value,
        p2pFecha: p2pFecha.value,
        p2pImporte: p2pImporte.value,
    }),
});

watch(() => props.open, (isOpen) => {
    if (!isOpen) return;
    paymentAlert.value = null;
    paymentSuccess.value = null;
    const saved = persisted.load();
    if (!saved) return;
    if (!banks.value || banks.value.length === 0) banksStore.loadBanks();
    p2pCedula.value = saved.p2pCedula ?? '';
    p2pTelefonoPrefix.value = saved.p2pTelefonoPrefix ?? '0412';
    p2pTelefonoNumber.value = saved.p2pTelefonoNumber ?? '';
    p2pBancoOrigen.value = saved.p2pBancoOrigen ?? '';
    p2pReferencia.value = saved.p2pReferencia ?? '';
    p2pFecha.value = saved.p2pFecha ?? '';
    p2pImporte.value = saved.p2pImporte ?? '';
    showP2PForm.value = !!saved.showP2PForm;
});

// "Cancelar" sí olvida lo escrito; la X solo cierra (se conserva 30 min)
const cancelAndForget = () => {
    persisted.clear();
    handleOpenChange(false);
};

const fieldClass =
    'h-12 w-full rounded-xl border border-input bg-background px-3 text-[15px] shadow-xs outline-none transition focus:border-foreground focus:ring-2 focus:ring-foreground/10';
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <!-- Tocar fuera ya NO cierra la hoja (evita perder lo escrito) -->
        <DialogContent
            @open-auto-focus.prevent
            @pointer-down-outside.prevent
            @interact-outside.prevent
            class="flex max-h-[92dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-md sm:rounded-3xl max-sm:top-auto max-sm:bottom-0 max-sm:max-w-full max-sm:translate-y-0 max-sm:rounded-t-3xl max-sm:rounded-b-none max-sm:border-x-0 max-sm:border-b-0"
        >
            <!-- Punto 9: capa de espera mientras el BDV responde -->
            <VerifyingOverlay v-if="loading" />

            <!-- ═════════ Pantalla de resultado ═════════ -->
            <template v-if="paymentSuccess">
                <DialogHeader class="sr-only">
                    <DialogTitle>{{ paymentSuccess.title }}</DialogTitle>
                    <DialogDescription>{{ paymentSuccess.message }}</DialogDescription>
                </DialogHeader>
                <div class="overflow-y-auto px-5 pt-8 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                    <SuccessView v-bind="paymentSuccess" @done="handleOpenChange(false)" />
                </div>
            </template>

            <template v-else>
                <!-- Encabezado -->
                <DialogHeader class="shrink-0 space-y-3 px-5 pt-5 pb-4 text-left">
                    <div class="mx-auto -mt-2 mb-1 h-1.5 w-10 rounded-full bg-muted sm:hidden" />
                    <div class="flex items-center gap-3 pr-8">
                        <button
                            v-if="showP2PForm"
                            type="button"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-muted text-foreground transition hover:bg-muted/70"
                            aria-label="Volver"
                            @click="goBack"
                        >
                            <ArrowLeft class="h-5 w-5" />
                        </button>
                        <img v-else src="/img/bdv.webp" alt="BDV" class="h-10 w-10 shrink-0 rounded-full bg-white object-contain p-1 ring-1 ring-border" />
                        <div class="min-w-0">
                            <DialogTitle class="text-xl leading-tight font-bold">
                                {{ showP2PForm ? 'Confirma tu pago' : 'Paga con Pago Móvil' }}
                            </DialogTitle>
                            <DialogDescription class="text-[13px]">
                                {{ showP2PForm ? 'Verificamos tu pago directamente con el BDV' : 'Banco de Venezuela (BDV)' }}
                            </DialogDescription>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="h-1.5 flex-1 rounded-full bg-foreground" />
                        <div :class="['h-1.5 flex-1 rounded-full transition-colors', showP2PForm ? 'bg-foreground' : 'bg-muted']" />
                    </div>
                    <div class="-mt-1 flex justify-between text-[13px] font-medium text-muted-foreground">
                        <span :class="!showP2PForm && 'font-semibold text-foreground'">Paso 1: Paga</span>
                        <span :class="showP2PForm && 'font-semibold text-foreground'">Paso 2: Confirma</span>
                    </div>
                </DialogHeader>

                <!-- Contenido -->
                <div class="flex-1 space-y-5 overflow-y-auto px-5 pb-5">
                    <InlineAlert v-if="paymentAlert" :alert="paymentAlert" @dismiss="paymentAlert = null" />

                    <!-- ───────────── PASO 1: PAGAR ───────────── -->
                    <template v-if="!showP2PForm">
                        <AmountHero
                            :amount-bs="suggestedAmountBs"
                            :amount-usd="amountUsdPrimary"
                            :rate="bcv"
                            :label="amountLabel"
                            :invoices-count="invoiceIdsForWispro.length"
                        />

                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-[15px] font-semibold">Haz un Pago Móvil a</p>
                                <button
                                    type="button"
                                    class="text-[13px] font-semibold text-foreground/70 underline underline-offset-4 hover:text-foreground disabled:opacity-40"
                                    :disabled="!bcv || !amountUsdPrimary"
                                    @click="copyPaymentData"
                                >
                                    Copiar todo
                                </button>
                            </div>
                            <div class="divide-y overflow-hidden rounded-2xl border bg-card">
                                <CopyRow label="Banco" :value="paymentMobileBanco || 'Banco de Venezuela (0102)'" :copy-value="paymentMobileBanco || '0102'" />
                                <CopyRow label="Teléfono" :value="paymentMobileTlf" mono />
                                <CopyRow label="RIF / Cédula" :value="paymentMobileRif" mono />
                                <!-- Se MUESTRA 5.911,50 pero se COPIA 5911.50 -->
                                <CopyRow
                                    label="Monto exacto"
                                    :value="suggestedAmountBs ? `${formatBs(suggestedAmountBs)} Bs` : ''"
                                    :copy-value="suggestedAmountBs"
                                    mono
                                />
                            </div>
                            <p v-if="paymentMobileName" class="mt-2 px-1 text-[13px] text-muted-foreground">
                                Titular: <span class="font-medium text-foreground">{{ paymentMobileName }}</span>
                            </p>
                        </div>

                        <div class="flex gap-2.5 rounded-xl bg-amber-50 px-3.5 py-3 text-[13px] text-amber-950 dark:bg-amber-950/30 dark:text-amber-100">
                            <ShieldCheck class="mt-0.5 h-4 w-4 shrink-0" />
                            <p>
                                <span class="font-semibold">Antes de pagar,</span> verifica que estos datos correspondan a tu factura. Paga el
                                monto exacto para que la verificación sea automática.
                            </p>
                        </div>

                        <!-- C2P: deshabilitado -->
                        <div class="flex w-full items-center gap-3 rounded-2xl border border-dashed px-4 py-3.5 opacity-60">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted">
                                <KeyRound class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[15px] font-semibold">Pagar con C2P</span>
                                <span class="block text-[13px] text-muted-foreground">Débito con clave de pago</span>
                            </span>
                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[13px] font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                Próximamente
                            </span>
                        </div>
                    </template>

                    <!-- ───────────── PASO 2: VERIFICAR P2P ───────────── -->
                    <template v-else>
                        <div class="flex items-center justify-between rounded-xl bg-muted px-4 py-3 text-[15px]">
                            <span class="text-muted-foreground">Monto a pagar</span>
                            <span class="font-bold tabular-nums">{{ formatBs(suggestedAmountBs) }} Bs</span>
                        </div>

                        <!-- Referencia (BDV pide 5) -->
                        <div class="space-y-1.5">
                            <label for="p2pReferencia" class="text-[15px] font-semibold">Últimos 5 números de la referencia</label>
                            <p class="text-[13px] text-muted-foreground">
                                <span class="font-semibold text-foreground">Referencia:</span> el número que aparece en el comprobante que te dio tu
                                banco.
                            </p>
                            <RefInput id="p2pReferencia" v-model="p2pReferencia" :length="5" />
                        </div>

                        <!-- Banco origen (Punto 7) -->
                        <div class="space-y-1.5">
                            <label for="p2pBancoOrigen" class="text-[15px] font-semibold">¿Desde qué banco pagaste?</label>
                            <BankSelect id="p2pBancoOrigen" v-model="p2pBancoOrigen" :banks="banks" :loading="banksLoading" :error="banksError" pad />
                        </div>

                        <!-- Cédula -->
                        <div class="space-y-1.5">
                            <label class="text-[15px] font-semibold">Cédula de quien pagó</label>
                            <input v-model="p2pCedula" placeholder="V12345678" :class="[fieldClass, 'uppercase']" />
                            <p class="text-[13px] text-muted-foreground">V o E seguido de los números</p>
                        </div>

                        <!-- Teléfono -->
                        <div class="space-y-1.5">
                            <label class="text-[15px] font-semibold">Teléfono desde el que pagaste</label>
                            <div class="flex gap-2">
                                <select v-model="p2pTelefonoPrefix" :class="[fieldBase, 'w-[5.5rem] shrink-0 px-2']" aria-label="Código">
                                    <option v-for="p in PHONE_PREFIXES" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <input
                                    v-model="p2pTelefonoNumber"
                                    placeholder="1234567"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]{7}"
                                    aria-label="Número"
                                    :class="[fieldBase, 'min-w-0 flex-1 tabular-nums']"
                                />
                            </div>
                        </div>

                        <!-- Fecha -->
                        <div class="space-y-1.5">
                            <label for="p2pFecha" class="text-[15px] font-semibold">¿Cuándo pagaste?</label>
                            <DateChips id="p2pFecha" v-model="p2pFecha" />
                        </div>

                        <!-- Monto (Punto 6) -->
                        <div class="space-y-1.5">
                            <p class="text-[15px] font-semibold">¿Cuánto pagaste?</p>
                            <AmountConfirm v-model="p2pImporte" :expected="suggestedAmountBs" />
                        </div>
                    </template>
                </div>

                <!-- Pie con acción principal -->
                <div class="shrink-0 border-t bg-background px-5 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <Button v-if="!showP2PForm" size="lg" class="h-14 w-full rounded-2xl text-base font-bold" :disabled="!bcv || !amountUsdPrimary" @click="openP2PForm">
                        <Smartphone class="h-5 w-5" />
                        Ya pagué, reportar pago
                    </Button>
                    <Button v-else size="lg" class="h-14 w-full rounded-2xl text-base font-bold" :disabled="!canSubmitP2P" @click="submitP2P">
                        Verificar pago
                    </Button>

                    <DialogFooter class="mt-1 sm:justify-center">
                        <Button variant="ghost" size="sm" class="h-10 w-full text-[15px] text-muted-foreground" @click="cancelAndForget">Cancelar</Button>
                    </DialogFooter>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
