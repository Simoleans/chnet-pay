<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useBcvStore } from '@/stores/bcv';
import { storeToRefs } from 'pinia';
import axios from 'axios';
import { useBanksStore } from '@/stores/banks';

// ── Alertas DENTRO de la hoja (UI) ──────────────────────────────────────────
// Antes: SweetAlert flotante sobre el Dialog (a veces no respondía y se cerraba solo).
// Ahora: errores/avisos fijos hasta "Entendido"; éxito con pantalla propia.
// La firma de notify() es la misma, así el resto del código no cambia.
const paymentAlert = ref<PaymentAlert | null>(null);
const paymentSuccess = ref<PaymentSuccess | null>(null);
let alertTimer: ReturnType<typeof setTimeout> | null = null;
const notify = ({ message, type = 'success' }: { message: string; type?: PaymentAlert['type']; duration?: number }) => {
    if (alertTimer) clearTimeout(alertTimer);
    paymentAlert.value = { type, message };
    // Solo los mensajes positivos (ej. "Datos copiados") se ocultan solos
    if (type === 'success' || type === 'info') {
        alertTimer = setTimeout(() => (paymentAlert.value = null), 2500);
    }
};

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
import { ArrowLeft, ChevronRight, KeyRound, ShieldCheck, Smartphone } from 'lucide-vue-next';

type PaymentMobileData = {
    name?: string;
    banco?: string;
    tlf?: string;
    rif?: string;
};

interface Props {
    open?: boolean;
    userPlan?: {
        name: string;
        price: number;
    } | null;
    /** Una factura o varias (Pagar todo): siempre incluye amount en USD total y invoice_ids o id legacy. */
    selectedInvoice?: {
        id?: number | string;
        invoice_ids?: string[];
        amount?: number;
        invoice_number?: string;
        client_id?: number | string;
        invoicing_firm_id?: string | null;
        invoices?: Array<{
            id?: number | string;
            invoice_number?: string;
            amount?: number;
            invoicing_firm_id?: string | null;
        }>;
    } | null;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    // (e: 'openReportModal'): void; // deshabilitado
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    userPlan: null,
    selectedInvoice: null,
});

const emit = defineEmits<Emits>();

// Usar usePage para acceder a los datos del usuario
const page = usePage();

// Obtener datos de pago móvil desde las props compartidas
// La llave del mapa es el invoicing_firm_id que viene en cada factura de Wispro.
const paymentMobileByFirm = computed(() => {
    return (page.props.paymentMobileByFirm as Record<string, PaymentMobileData> | undefined) || {};
});

// Para una factura simple toma su firm id; para "Pagar todo" exige que todas coincidan.
const selectedInvoiceFirmId = computed(() => {
    if (props.selectedInvoice?.invoicing_firm_id) {
        return String(props.selectedInvoice.invoicing_firm_id);
    }

    const firmIds = Array.from(
        new Set(
            (props.selectedInvoice?.invoices || [])
                .map((invoice: { invoicing_firm_id?: string | null }) => invoice.invoicing_firm_id)
                .filter(Boolean)
                .map(String)
        )
    );

    return firmIds.length === 1 ? firmIds[0] : null;
});

const paymentMobile = computed(() => {
    const firmId = selectedInvoiceFirmId.value;

    // Si la empresa tiene un pago móvil configurado, se usa ese; si no, queda el actual.
    if (firmId && paymentMobileByFirm.value[firmId]) {
        return paymentMobileByFirm.value[firmId];
    }

    return (page.props.paymentMobile as PaymentMobileData | undefined) || {};
});

const paymentMobileName = computed(() => paymentMobile.value.name);
const paymentMobileBanco = computed(() => paymentMobile.value.banco);
const paymentMobileTlf = computed(() => paymentMobile.value.tlf);
const paymentMobileRif = computed(() => paymentMobile.value.rif);

// Usar el store de BCV
const bcvStore = useBcvStore();
const { bcv } = storeToRefs(bcvStore);

// Store de bancos
const banksStore = useBanksStore();
const { banks, loading: banksLoading, error: banksError } = storeToRefs(banksStore as any);

const PHONE_PREFIXES = ['0412', '0414', '0424', '0426', '0416', '0422'];

// Estados para el pago
const paymentLoading = ref(false);
const paymentError = ref(false);
const showReferenceInput = ref(false);
const referenceNumber = ref('');
const paymentAmount = ref('');
const paymentDate = ref('');
// const showReportLink = ref(false); // deshabilitado: ya no se muestra el link de reporte manual
// Estados para validación manual
const manualBankCode = ref('');
const manualPhonePrefix = ref('0412');
const manualPhoneNumber = ref('');
// Estados para C2P
const showC2PSection = ref(false);
const c2pLoading = ref(false);
const c2pBankCode = ref('');
const c2pToken = ref('');
const c2pId = ref('');
const c2pPhonePrefix = ref('0412');
const c2pPhoneNumber = ref('');

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

const suggestedAmountBs = computed(() => {
    const amountToPay = amountUsdPrimary.value;
    if (bcv.value && amountToPay) {
        return (parseFloat(String(amountToPay)) * parseFloat(bcv.value)).toFixed(2);
    }
    return '';
});

const parsePaymentAmount = (value: string) => {
    const amount = Number(value.trim().replace(',', '.'));
    return Number.isFinite(amount) ? amount : 0;
};

const enteredPaymentAmountBs = computed(() => {
    return parsePaymentAmount(paymentAmount.value);
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
    paymentLoading.value = false;
    c2pLoading.value = false;
    showReferenceInput.value = false;
    referenceNumber.value = '';
    paymentAmount.value = '';
    paymentDate.value = '';
    // showReportLink.value = false;
    manualBankCode.value = '';
    manualPhonePrefix.value = '0412';
    manualPhoneNumber.value = '';
    showC2PSection.value = false;
    c2pPhonePrefix.value = '0412';
    c2pPhoneNumber.value = '';
};

const openC2PSection = () => {
    // Limpiar formulario de "Ya pagué" si estaba activo
    if (showReferenceInput.value) {
        referenceNumber.value = '';
        paymentAmount.value = '';
        paymentDate.value = '';
        manualPhoneNumber.value = '';
        // showReportLink.value = false;
    }

    showC2PSection.value = true;
    showReferenceInput.value = false;

    if (!banks.value || banks.value.length === 0) {
        banksStore.loadBanks();
    }
    // precargar datos del usuario
    c2pId.value = page.props.auth?.user?.id_number || '';
    //c2pPhone.value = page.props.auth?.user?.phone || '';

    // cargar/actualizar BCV
    if (typeof (bcvStore as any).$reloadBcvAmount === 'function') {
        (bcvStore as any).$reloadBcvAmount();
    }
};

const sendC2P = async () => {
    c2pLoading.value = true;
    try {
        if (!c2pBankCode.value || !c2pToken.value || !c2pId.value || !c2pPhoneNumber.value) {
            notify({ message: 'Complete los datos del C2P', type: 'error', duration: 2000 });
            c2pLoading.value = false;
            return;
        }

        // Validación de cédula: V00000000 o E00000000 (sin guiones)
        const idPattern = /^[VE][0-9]+$/i;
        const normalized = c2pId.value.trim().toUpperCase().replace(/[^VE0-9]/g, '');
        if (!idPattern.test(normalized)) {
            notify({ message: 'La cédula debe ser en formato V00000000 o E00000000', type: 'error', duration: 2500 });
            c2pLoading.value = false;
            return;
        }
        c2pId.value = normalized;

        // Validación de teléfono: 7 dígitos después del prefijo
        const phoneDigits7 = c2pPhoneNumber.value.replace(/\D/g, '');
        if (!/^\d{7}$/.test(phoneDigits7)) {
            notify({ message: 'El teléfono debe tener exactamente 7 dígitos después del prefijo', type: 'error', duration: 2500 });
            c2pLoading.value = false;
            return;
        }
        // Construir número completo: quitar el 0 inicial del prefijo y agregar 58
        // Ej: 0412 + 1234567 → 4121234567 → 584121234567
        const phoneDigits10 = c2pPhonePrefix.value.substring(1) + phoneDigits7;
        const phoneWithPrefix = '58' + phoneDigits10;

        // Calcular monto exacto: total factura(s) o plan
        const amountToPay = amountUsdPrimary.value;
        if (!amountToPay) {
            notify({ message: 'No se pudo calcular el monto. Verifique su factura o plan.', type: 'error', duration: 2500 });
            c2pLoading.value = false;
            return;
        }

        const hasBcv = bcv.value !== null && bcv.value !== undefined;
        if (!hasBcv) {
            notify({ message: 'No se pudo obtener la tasa BCV.', type: 'error', duration: 2500 });
            c2pLoading.value = false;
            return;
        }
        const amountStr = (parseFloat(String(amountToPay)) * parseFloat(String(bcv.value))).toFixed(2);

        // Preparar payload con invoice_id y client_id si están disponibles
        const payload: any = {
            debtor_bank_code: c2pBankCode.value,
            token: c2pToken.value,
            amount: parseFloat(amountStr),
            debtor_id: c2pId.value,
            debtor_phone: phoneWithPrefix,
        };

        const ids = invoiceIdsForWispro.value;
        if (ids.length === 1) {
            payload.invoice_id = ids[0];
        } else if (ids.length > 1) {
            payload.invoice_ids = ids;
        }
        // Backend puede usar este firm id para elegir la cuenta/validación correcta.
        if (selectedInvoiceFirmId.value) {
            payload.invoicing_firm_id = selectedInvoiceFirmId.value;
        }
        // Intentar obtener client_id de la factura o del usuario
        if (props.selectedInvoice?.client_id) {
            payload.client_id = props.selectedInvoice.client_id;
        } else if (page.props.auth?.user?.id_wispro) {
            // Si no viene en la factura, usar el id_wispro del usuario
            payload.client_id = page.props.auth.user.id_wispro;
        }

        const res = await axios.post('/api/bnc/send-c2p', payload);

        if (res.data?.success) {
            // UI: pantalla de éxito; la recarga ocurre cuando la persona toca "Listo"
            paymentSuccess.value = {
                title: '¡Pago realizado!',
                message: res.data.message || 'Tu pago C2P fue procesado correctamente.',
                amountBs: amountStr,
            };
            persisted.clear();
        } else {
            notify({ message: res.data?.error || res.data?.message || 'No se pudo enviar C2P', type: 'error', duration: 3000 });
        }
    } catch (e: any) {
        const msg = e?.response?.data?.message ?? e?.response?.data?.error ?? e?.message ?? 'Error al enviar C2P';
        notify({ message: msg, type: 'error', duration: 3500 });
    } finally {
        c2pLoading.value = false;
    }
};

const copyPaymentReference = async () => {
    console.log('Intentando copiar...', { bcv: bcv.value, userPlan: props.userPlan });

    if (bcv.value && amountUsdPrimary.value) {
        const total = (parseFloat(String(amountUsdPrimary.value)) * parseFloat(bcv.value)).toFixed(2);

        // Obtener datos de pago móvil desde las props compartidas
        const banco = paymentMobileBanco.value;
        const rif = paymentMobileRif.value;
        const tlf = paymentMobileTlf.value;
        const name = paymentMobileName.value;

        // Formato mejorado de los datos bancarios
        const bankingData = `📧 ${name} - Datos para Pago Movil

            Banco: ${banco}
            RIF: ${rif}
            Teléfono: ${tlf}
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
        console.error('Faltan datos:', { bcv: bcv.value, amountUsdPrimary: amountUsdPrimary.value });
        notify({
            message: '❌ No hay datos disponibles para copiar. Verifica que tengas una factura o plan y que la tasa BCV esté cargada.',
            type: 'error',
            duration: 3000,
        });
    }
};

// const handleReportManually = () => {
//     emit('update:open', false);
//     emit('openReportModal');
// };

const checkPayment = async () => {
    const shouldPrefillPaymentAmount = !showReferenceInput.value && !paymentAmount.value;

    // Limpiar formulario de C2P si estaba activo
    if (showC2PSection.value) {
        c2pToken.value = '';
        c2pPhoneNumber.value = '';
        c2pBankCode.value = banks.value && banks.value.length > 0 ? String(banks.value[0].Code || '') : '';
    }

    paymentLoading.value = true;
    paymentError.value = false;
    showReferenceInput.value = true;
    // showReportLink.value = false;
    showC2PSection.value = false;

    // Cargar bancos si no están cargados
    if (!banks.value || banks.value.length === 0) {
        banksStore.loadBanks();
    }

    // Precargar fecha actual
    if (!paymentDate.value) {
        paymentDate.value = localIsoDate(0); // fecha local (antes UTC: fallaba de noche)
    }

    // Precargar solo al abrir el formulario; al verificar se respeta lo escrito por el usuario.
    const amountToPay = amountUsdPrimary.value;
    if (shouldPrefillPaymentAmount && bcv.value && amountToPay) {
        paymentAmount.value = (parseFloat(String(amountToPay)) * parseFloat(bcv.value)).toFixed(2);
    }

    try {
        if (referenceNumber.value.trim()) {
            // Validar 7 dígitos del número de teléfono
            if (!/^\d{7}$/.test(manualPhoneNumber.value.trim())) {
                notify({ message: 'El teléfono debe tener exactamente 7 dígitos después del prefijo', type: 'error', duration: 2500 });
                paymentLoading.value = false;
                return;
            }
            const manualPhoneFull = manualPhonePrefix.value + manualPhoneNumber.value.trim();

            // Preparar payload con invoice_id y client_id si están disponibles
            const payload: any = {
                reference: referenceNumber.value,
                amount: enteredPaymentAmountBs.value,
                bank: manualBankCode.value,
                phone: manualPhoneFull,
                payment_date: paymentDate.value
            };

            const ids = invoiceIdsForWispro.value;
            if (ids.length === 1) {
                payload.invoice_id = ids[0];
            } else if (ids.length > 1) {
                payload.invoice_ids = ids;
            }
            // Backend puede usar este firm id para elegir la cuenta/validación correcta.
            if (selectedInvoiceFirmId.value) {
                payload.invoicing_firm_id = selectedInvoiceFirmId.value;
            }
            // Intentar obtener client_id de la factura o del usuario
            if (props.selectedInvoice?.client_id) {
                payload.client_id = props.selectedInvoice.client_id;
            } else if (page.props.auth?.user?.id_wispro) {
                // Si no viene en la factura, usar el id_wispro del usuario
                payload.client_id = page.props.auth.user.id_wispro;
            }

            const res = await axios.post('/api/bnc/validate-and-store-payment', payload);

            if (res.data.success) {
                // UI: pantalla de éxito; la recarga ocurre cuando la persona toca "Listo"
                paymentSuccess.value = {
                    title: '¡Pago verificado!',
                    message: res.data.message || 'Tu pago fue registrado correctamente.',
                    amountBs: String(payload.amount),
                    reference: payload.reference,
                    date: payload.payment_date,
                };
                persisted.clear();
            } else {
                // showReportLink.value = res.data.showReportLink;
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
            message: 'Por favor ingrese los últimos 6 números de la referencia',
            type: 'error',
            duration: 2000,
        });
        return;
    }

    if (!/^\d{6}$/.test(trimmedRef)) {
        notify({
            message: 'La referencia debe tener exactamente 6 números',
            type: 'error',
            duration: 2000,
        });
        return;
    }

    // Se permite reportar el monto ingresado por el usuario, aunque sea menor al monto oficial.
    // if (isPaymentAmountTooLow.value) {
    //     notify({
    //         message: `El monto pagado no puede ser menor a ${minimumMarkedPaymentAmountBs.value.toFixed(2)} Bs`,
    //         type: 'error',
    //         duration: 2500,
    //     });
    //     return;
    // }

    await checkPayment();
};

const handleOpenChange = (open: boolean) => {
    // UI: no se puede cerrar mientras el banco está respondiendo
    if (!open && (paymentLoading.value || c2pLoading.value)) return;
    // UI: si el pago ya se verificó, al cerrar refrescamos facturas
    if (!open && paymentSuccess.value) {
        window.location.reload();
        return;
    }
    emit('update:open', open);
    if (!open) {
        resetStates();
    }
};

// Fechas locales (antes toISOString = UTC: desde las 8 pm daba el día siguiente)
const setToday = () => {
    paymentDate.value = localIsoDate(0);
};

const setYesterday = () => {
    paymentDate.value = localIsoDate(-1);
};

// ── Ayudas solo de interfaz (no modifican payloads ni endpoints) ─────────────
const step = computed<'pay' | 'report' | 'c2p'>(() => {
    if (showC2PSection.value) return 'c2p';
    if (showReferenceInput.value) return 'report';
    return 'pay';
});

const goBack = () => {
    showReferenceInput.value = false;
    showC2PSection.value = false;
};

// Sin maxlength en el HTML: así al pegar "765-4321" se limpia primero y luego se recorta.
// Solo dígitos en teléfonos (la referencia la maneja <RefInput>)
const onlyDigits = (v: string, max: number) => v.replace(/\D/g, '').slice(0, max);
watch(manualPhoneNumber, (v) => { const c = onlyDigits(v, 7); if (c !== v) manualPhoneNumber.value = c; });
watch(c2pPhoneNumber, (v) => { const c = onlyDigits(v, 7); if (c !== v) c2pPhoneNumber.value = c; });

const canSubmitReference = computed(() =>
    !paymentLoading.value &&
    referenceNumber.value.trim().length === 6 &&
    !!paymentAmount.value &&
    enteredPaymentAmountBs.value > 0 &&
    /^\d{7}$/.test(manualPhoneNumber.value.trim()) &&
    !!paymentDate.value
);

const canSendC2P = computed(() =>
    !c2pLoading.value &&
    !!c2pToken.value &&
    !!c2pBankCode.value &&
    !!c2pId.value &&
    /^\d{7}$/.test(c2pPhoneNumber.value) &&
    !!(amountUsdPrimary.value && bcv.value)
);

const amountLabel = computed(() =>
    isBatchInvoicePay.value ? 'Total de tus facturas' : props.selectedInvoice ? 'Total de la factura' : 'Total de tu plan'
);

const fieldBase =
    'h-12 rounded-xl border border-input bg-background px-3 text-[15px] shadow-xs outline-none transition focus:border-foreground focus:ring-2 focus:ring-foreground/10';
// ── Recordar lo escrito si la persona sale a la app del banco (localStorage) ──
// Solo estado de pantalla. El token C2P NO se guarda.
const persistKey = () => {
    const uid = (page.props.auth as any)?.user?.id;
    if (!uid) return null;
    return `chnet:pm:bnc:${uid}:${invoiceIdsForWispro.value.join('-') || 'plan'}`;
};
const persisted = usePersistedForm({
    key: persistKey,
    enabled: () => props.open && !paymentSuccess.value,
    sources: [step, referenceNumber, paymentAmount, paymentDate, manualBankCode, manualPhonePrefix, manualPhoneNumber, c2pBankCode, c2pId, c2pPhonePrefix, c2pPhoneNumber],
    snapshot: () => ({
        step: step.value,
        referenceNumber: referenceNumber.value,
        paymentAmount: paymentAmount.value,
        paymentDate: paymentDate.value,
        manualBankCode: manualBankCode.value,
        manualPhonePrefix: manualPhonePrefix.value,
        manualPhoneNumber: manualPhoneNumber.value,
        c2pBankCode: c2pBankCode.value,
        c2pId: c2pId.value,
        c2pPhonePrefix: c2pPhonePrefix.value,
        c2pPhoneNumber: c2pPhoneNumber.value,
    }),
});

// Se registra DESPUÉS del watcher original de `open` (que resetea), así restaura encima
watch(() => props.open, (isOpen) => {
    if (!isOpen) return;
    paymentAlert.value = null;
    paymentSuccess.value = null;
    const saved = persisted.load();
    if (!saved) return;
    referenceNumber.value = saved.referenceNumber ?? '';
    paymentAmount.value = saved.paymentAmount ?? '';
    paymentDate.value = saved.paymentDate ?? '';
    manualBankCode.value = saved.manualBankCode ?? '';
    manualPhonePrefix.value = saved.manualPhonePrefix ?? '0412';
    manualPhoneNumber.value = saved.manualPhoneNumber ?? '';
    if (saved.c2pBankCode) c2pBankCode.value = saved.c2pBankCode;
    c2pId.value = saved.c2pId ?? '';
    c2pPhonePrefix.value = saved.c2pPhonePrefix ?? '0412';
    c2pPhoneNumber.value = saved.c2pPhoneNumber ?? '';
    showReferenceInput.value = saved.step === 'report';
    showC2PSection.value = saved.step === 'c2p';
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
        <!-- @pointer-down-outside.prevent: tocar fuera ya NO cierra la hoja (evita perder lo escrito) -->
        <DialogContent
            @open-auto-focus.prevent
            @pointer-down-outside.prevent
            @interact-outside.prevent
            class="flex max-h-[92dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-md sm:rounded-3xl max-sm:top-auto max-sm:bottom-0 max-sm:max-w-full max-sm:translate-y-0 max-sm:rounded-t-3xl max-sm:rounded-b-none max-sm:border-x-0 max-sm:border-b-0"
        >
            <input type="hidden" :value="$page.props.auth.user?.id" />

            <!-- Punto 9: capa de espera mientras el banco responde -->
            <VerifyingOverlay v-if="paymentLoading && step === 'report'" />
            <VerifyingOverlay v-if="c2pLoading" title="Procesando tu pago C2P" />

            <!-- ═════════ Pantalla de éxito ═════════ -->
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
                            v-if="step !== 'pay'"
                            type="button"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-muted text-foreground transition hover:bg-muted/70"
                            aria-label="Volver"
                            @click="goBack"
                        >
                            <ArrowLeft class="h-5 w-5" />
                        </button>
                        <img v-else src="/img/bnc.png" alt="BNC" class="h-10 w-10 shrink-0 rounded-full bg-white object-contain p-1 ring-1 ring-border" />
                        <div class="min-w-0">
                            <DialogTitle class="text-xl leading-tight font-bold">
                                {{ step === 'pay' ? 'Paga con Pago Móvil' : step === 'report' ? 'Confirma tu pago' : 'Pagar con C2P' }}
                            </DialogTitle>
                            <DialogDescription class="text-[13px]">
                                {{
                                    step === 'pay'
                                        ? 'Banco Nacional de Crédito (BNC)'
                                        : step === 'report'
                                          ? 'Con estos datos verificamos tu pago'
                                          : 'Débito directo con la clave de tu banco'
                                }}
                            </DialogDescription>
                        </div>
                    </div>

                    <template v-if="step !== 'c2p'">
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 flex-1 rounded-full bg-foreground" />
                            <div :class="['h-1.5 flex-1 rounded-full transition-colors', step === 'report' ? 'bg-foreground' : 'bg-muted']" />
                        </div>
                        <div class="-mt-1 flex justify-between text-[13px] font-medium text-muted-foreground">
                            <span :class="step === 'pay' && 'font-semibold text-foreground'">Paso 1: Paga</span>
                            <span :class="step === 'report' && 'font-semibold text-foreground'">Paso 2: Confirma</span>
                        </div>
                    </template>
                </DialogHeader>

                <!-- Contenido -->
                <div class="flex-1 space-y-5 overflow-y-auto px-5 pb-5">
                    <!-- Alertas: quedan fijas hasta "Entendido" -->
                    <InlineAlert v-if="paymentAlert" :alert="paymentAlert" @dismiss="paymentAlert = null" />

                    <!-- ───────────── PASO 1: PAGAR ───────────── -->
                    <template v-if="step === 'pay'">
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
                                    @click="copyPaymentReference"
                                >
                                    Copiar todo
                                </button>
                            </div>
                            <div class="divide-y overflow-hidden rounded-2xl border bg-card">
                                <CopyRow label="Banco" :value="paymentMobileBanco" />
                                <CopyRow label="Teléfono" :value="paymentMobileTlf" mono />
                                <CopyRow label="RIF / Cédula" :value="paymentMobileRif" mono />
                                <!-- Se MUESTRA 5.911,50 pero se COPIA 5911.50 (lo que acepta el banco) -->
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

                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-2xl border bg-card px-4 py-3.5 text-left transition hover:border-foreground/30 hover:bg-muted/40 disabled:opacity-50"
                            :disabled="!bcv || !amountUsdPrimary"
                            @click="openC2PSection"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted">
                                <KeyRound class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[15px] font-semibold">Pagar con C2P</span>
                                <span class="block text-[13px] text-muted-foreground">Usa la clave de pago que te envía tu banco</span>
                            </span>
                            <ChevronRight class="h-5 w-5 text-muted-foreground" />
                        </button>
                    </template>

                    <!-- ───────────── PASO 2: CONFIRMAR / VERIFICAR ───────────── -->
                    <template v-else-if="step === 'report'">
                        <div class="flex items-center justify-between rounded-xl bg-muted px-4 py-3 text-[15px]">
                            <span class="text-muted-foreground">Monto a pagar</span>
                            <span class="font-bold tabular-nums">{{ formatBs(suggestedAmountBs) }} Bs</span>
                        </div>

                        <!-- Referencia -->
                        <div class="space-y-1.5">
                            <label for="referenceNumber" class="text-[15px] font-semibold">Últimos 6 números de la referencia</label>
                            <p class="text-[13px] text-muted-foreground">
                                <span class="font-semibold text-foreground">Referencia:</span> el número que aparece en el comprobante que te dio tu
                                banco.
                            </p>
                            <RefInput id="referenceNumber" v-model="referenceNumber" :length="6" />
                        </div>

                        <!-- Banco (Punto 7: más usados arriba) -->
                        <div class="space-y-1.5">
                            <label for="manualBankCode" class="text-[15px] font-semibold">¿Desde qué banco pagaste?</label>
                            <BankSelect id="manualBankCode" v-model="manualBankCode" :banks="banks" :loading="banksLoading" :error="banksError" pad />
                        </div>

                        <!-- Teléfono -->
                        <div class="space-y-1.5">
                            <label class="text-[15px] font-semibold">Teléfono desde el que pagaste</label>
                            <div class="flex gap-2">
                                <select v-model="manualPhonePrefix" :class="[fieldBase, 'w-[5.5rem] shrink-0 px-2']" aria-label="Código">
                                    <option v-for="p in PHONE_PREFIXES" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <input
                                    v-model="manualPhoneNumber"
                                    placeholder="1234567"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]{7}"
                                    aria-label="Número"
                                    :class="[fieldBase, 'min-w-0 flex-1 tabular-nums']"
                                />
                            </div>
                        </div>

                        <!-- Fecha (arreglado: "Otra" sí abre el calendario) -->
                        <div class="space-y-1.5">
                            <label for="paymentDate" class="text-[15px] font-semibold">¿Cuándo pagaste?</label>
                            <DateChips id="paymentDate" v-model="paymentDate" />
                        </div>

                        <!-- Monto (Punto 6: confirmar en vez de editar) -->
                        <div class="space-y-1.5">
                            <p class="text-[15px] font-semibold">¿Cuánto pagaste?</p>
                            <AmountConfirm v-model="paymentAmount" :expected="suggestedAmountBs" />
                        </div>
                    </template>

                    <!-- ───────────── C2P ───────────── -->
                    <template v-else>
                        <div class="flex items-center justify-between rounded-xl bg-muted px-4 py-3 text-[15px]">
                            <span class="text-muted-foreground">Se debitará</span>
                            <span class="font-bold tabular-nums">{{ formatBs(suggestedAmountBs) }} Bs</span>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[15px] font-semibold">Tu banco</label>
                            <!-- C2P usa String(b.Code) sin padStart, igual que antes -->
                            <BankSelect v-model="c2pBankCode" :banks="banks" :loading="banksLoading" :error="banksError" :pad="false" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[15px] font-semibold">Cédula</label>
                            <input v-model="c2pId" placeholder="V12345678" :class="[fieldClass, 'uppercase']" />
                            <p class="text-[13px] text-muted-foreground">V o E seguido de los números, sin guiones</p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[15px] font-semibold">Teléfono afiliado al pago móvil</label>
                            <div class="flex gap-2">
                                <select v-model="c2pPhonePrefix" :class="[fieldBase, 'w-[5.5rem] shrink-0 px-2']" aria-label="Código">
                                    <option v-for="p in PHONE_PREFIXES" :key="p" :value="p">{{ p }}</option>
                                </select>
                                <input
                                    v-model="c2pPhoneNumber"
                                    placeholder="1234567"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]{7}"
                                    aria-label="Número"
                                    :class="[fieldBase, 'min-w-0 flex-1 tabular-nums']"
                                />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[15px] font-semibold">Clave de pago (token)</label>
                            <input
                                v-model="c2pToken"
                                placeholder="Código enviado por tu banco"
                                autocomplete="one-time-code"
                                :class="[fieldClass, 'font-mono tracking-widest']"
                            />
                            <p class="text-[13px] text-muted-foreground">Solicítala en la app de tu banco o por mensaje de texto</p>
                        </div>
                    </template>
                </div>

                <!-- Pie con acción principal -->
                <div class="shrink-0 border-t bg-background px-5 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <Button
                        v-if="step === 'pay'"
                        size="lg"
                        class="h-14 w-full rounded-2xl text-base font-bold"
                        :disabled="paymentLoading || !bcv || !amountUsdPrimary"
                        @click="checkPayment"
                    >
                        <Smartphone class="h-5 w-5" />
                        Ya pagué, reportar pago
                    </Button>

                    <Button v-else-if="step === 'report'" size="lg" class="h-14 w-full rounded-2xl text-base font-bold" :disabled="!canSubmitReference" @click="submitReference">
                        Verificar pago
                    </Button>

                    <Button v-else size="lg" class="h-14 w-full rounded-2xl text-base font-bold" :disabled="!canSendC2P" @click="sendC2P">
                        Pagar {{ formatBs(suggestedAmountBs) }} Bs
                    </Button>

                    <DialogFooter class="mt-1 sm:justify-center">
                        <Button variant="ghost" size="sm" class="h-10 w-full text-[15px] text-muted-foreground" @click="cancelAndForget">Cancelar</Button>
                    </DialogFooter>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
