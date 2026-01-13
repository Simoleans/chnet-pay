<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useNotifications } from '@/composables/useNotifications';
import { useBcvStore } from '@/stores/bcv';
import { useBanksStore } from '@/stores/banks';
import { storeToRefs } from 'pinia';
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
import { Label } from '@/components/ui/label';

interface Props {
    open?: boolean;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
});

const emit = defineEmits<Emits>();

// Estados
const searchCode = ref('');
const searchLoading = ref(false);
const userData = ref(null);
const imageFile = ref(null);
const selectedInvoice = ref(null);
const showPaymentForm = ref(false);

// Store BCV
const bcvStore = useBcvStore();
const { bcv } = storeToRefs(bcvStore);

// Store Bancos
const banksStore = useBanksStore();
const { getBankOptions } = banksStore;

const paymentForm = useForm({
    user_id: '',
    reference: '',
    nationality: 'V',
    id_number: '',
    bank: '',
    phone: '',
    amount: '',
    payment_date: '',
    image: null,
    invoice_wispro: '',
});

// Función de búsqueda manual con botón
const handleSearch = async () => {
    if (searchCode.value.length >= 3) {
        await searchUser(searchCode.value);
    } else {
        notify({
            message: 'Ingrese al menos 3 caracteres para buscar',
            type: 'warning',
            duration: 2000,
        });
    }
};

// Validar que la referencia solo contenga números y máximo 5 dígitos
watch(() => paymentForm.reference, (newValue) => {
    if (newValue) {
        // Eliminar cualquier carácter que no sea número
        const cleaned = newValue.replace(/\D/g, '');
        // Limitar a 5 dígitos
        paymentForm.reference = cleaned.slice(0, 5);
    }
});

// Validar formato de teléfono venezolano
watch(() => paymentForm.phone, (newValue) => {
    if (newValue) {
        // Eliminar cualquier carácter que no sea número
        const cleaned = newValue.replace(/\D/g, '');
        // Limitar a 11 dígitos (formato 04XX-XXXXXXX)
        paymentForm.phone = cleaned.slice(0, 11);
    }
});

const searchUser = async (code: string) => {
    searchLoading.value = true;
    try {
        const response = await fetch(`/api/users/search/${code}`);
        const data = await response.json();

        if (data.success) {
            userData.value = data.data;
            selectedInvoice.value = null;
            showPaymentForm.value = false;
        } else {
            userData.value = null;
            notify({
                message: data.message,
                type: 'warning',
                duration: 2000,
            });
        }
    } catch (error) {
        console.error('Error searching user:', error);
        userData.value = null;
    } finally {
        searchLoading.value = false;
    }
};

const selectInvoiceToPay = (invoice: any) => {
    selectedInvoice.value = invoice;
    showPaymentForm.value = true;

    // Auto-llenar campos del formulario
    if (bcv.value) {
        paymentForm.amount = (invoice.amount * parseFloat(bcv.value)).toFixed(2);
    }
    paymentForm.invoice_wispro = invoice.id.toString();
};

const cancelInvoiceSelection = () => {
    selectedInvoice.value = null;
    showPaymentForm.value = false;
    clearForm();
};

const formatDate = (dateString: string) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-VE', { year: 'numeric', month: '2-digit', day: '2-digit' });
};

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(price);
};

const formatPriceBs = (priceUSD: number) => {
    if (!bcv.value) return '0.00';
    const priceBs = priceUSD * parseFloat(bcv.value);
    return new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(priceBs);
};

const handleImageUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        imageFile.value = file;
        paymentForm.image = file;
    }
};

const setTodayDate = () => {
    const today = new Date();
    paymentForm.payment_date = today.toISOString().split('T')[0];
};

const clearForm = () => {
    paymentForm.reset();
    imageFile.value = null;
};

const submitPayment = () => {
    if (!userData.value) {
        notify({
            message: 'Debe buscar un usuario válido primero',
            type: 'error',
            duration: 2000,
        });
        return;
    }

    // Validar referencia (5 dígitos)
    if (!paymentForm.reference || paymentForm.reference.length !== 5) {
        notify({
            message: 'La referencia debe tener exactamente 5 dígitos',
            type: 'error',
            duration: 3000,
        });
        return;
    }

    // Validar teléfono venezolano (11 dígitos y debe comenzar con 04)
    if (!paymentForm.phone || paymentForm.phone.length !== 11) {
        notify({
            message: 'El teléfono debe tener 11 dígitos (Ej: 04121234567)',
            type: 'error',
            duration: 3000,
        });
        return;
    }

    if (!paymentForm.phone.startsWith('04')) {
        notify({
            message: 'El teléfono debe comenzar con 04 (formato venezolano)',
            type: 'error',
            duration: 3000,
        });
        return;
    }

    // Asignar el user_id al formulario
    paymentForm.user_id = userData.value.id;

    paymentForm.post(route('quick-payment.store'), {
                onSuccess: (page) => {
            // Extraer los mensajes de la sesión flash
            const successMessage = page.props?.flash?.success;
            const errorMessage = page.props?.flash?.error;

            if (successMessage) {
                notify({
                    message: successMessage,
                    type: 'success',
                    duration: 3000,
                });
                emit('update:open', false);
                resetModal();
            } else if (errorMessage) {
                notify({
                    message: errorMessage,
                    type: 'error',
                    duration: 4000,
                });
            } else {
                notify({
                    message: 'Pago registrado exitosamente',
                    type: 'success',
                    duration: 3000,
                });
                emit('update:open', false);
                resetModal();
            }
        },
        onError: (errors) => {
            console.error('Errores de validación:', errors);

            // Mostrar errores específicos de validación si existen
            if (errors && Object.keys(errors).length > 0) {
                const firstError = Object.values(errors)[0];
                notify({
                    message: Array.isArray(firstError) ? firstError[0] : firstError,
                    type: 'error',
                    duration: 4000,
                });
            } else {
                notify({
                    message: 'Error al procesar el pago. Revise los datos ingresados.',
                    type: 'error',
                    duration: 3000,
                });
            }
        },
    });
};

const resetModal = () => {
    searchCode.value = '';
    userData.value = null;
    selectedInvoice.value = null;
    showPaymentForm.value = false;
    clearForm();
};

const handleOpenChange = (open: boolean) => {
    emit('update:open', open);
    if (!open) {
        resetModal();
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Pago Rápido</DialogTitle>
                <DialogDescription>
                    Busque al cliente por código y registre su pago
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Búsqueda de usuario -->
                <div class="space-y-2">
                    <Label for="search_code">Código del Cliente</Label>
                    <div class="flex gap-2">
                        <Input
                            id="search_code"
                            v-model="searchCode"
                            placeholder="Ingrese el código del cliente"
                            :disabled="paymentForm.processing || searchLoading"
                            @keyup.enter="handleSearch"
                            class="flex-1"
                        />
                        <Button
                            @click="handleSearch"
                            :disabled="searchLoading || !searchCode || searchCode.length < 3"
                            class="bg-blue-600 hover:bg-blue-700"
                        >
                            {{ searchLoading ? 'Buscando...' : '🔍 Buscar' }}
                        </Button>
                    </div>
                </div>

                <!-- Información del usuario encontrado -->
                <div v-if="userData" class="space-y-4">
                    <!-- Datos básicos del cliente -->
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                        <h4 class="font-semibold text-blue-900 mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                            Cliente Encontrado
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Nombre</p>
                                <p class="font-semibold text-gray-900">{{ userData.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Código de Abonado</p>
                                <p class="font-semibold text-blue-700">{{ userData.code }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-xs text-gray-600 mb-1">Zona</p>
                                <p class="font-semibold text-gray-900">{{ userData.zone || 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Estado de facturas -->
                    <div v-if="userData.pending_invoices_count > 0" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                        <div class="flex items-center gap-3">
                            <svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="font-semibold text-yellow-800">
                                    {{ userData.pending_invoices_count }} {{ userData.pending_invoices_count === 1 ? 'factura pendiente' : 'facturas pendientes' }}
                                </p>
                                <p class="text-sm text-yellow-700">Por favor, selecciona una factura para registrar el pago</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="bg-green-50 border-l-4 border-green-400 p-4 rounded">
                        <div class="flex items-center gap-3">
                            <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="font-semibold text-green-800">Sin facturas pendientes</p>
                                <p class="text-sm text-green-700">¡El cliente está al día con sus pagos!</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de facturas pendientes -->
                <div v-if="userData && userData.pending_invoices && userData.pending_invoices.length > 0 && !showPaymentForm" class="bg-white rounded-lg border">
                    <div class="p-3 bg-gray-50 border-b">
                        <h4 class="font-semibold text-gray-900">📄 Facturas Pendientes</h4>
                        <p class="text-xs text-gray-500 mt-1">Selecciona una factura para registrar el pago</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Factura #</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vencimiento</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Monto USD</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Monto Bs</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="invoice in userData.pending_invoices" :key="invoice.id" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm font-medium text-gray-900">
                                        {{ invoice.invoice_number }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-600">
                                        <div v-if="invoice.from && invoice.to">
                                            {{ formatDate(invoice.from) }}<br>
                                            <span class="text-gray-400">hasta</span><br>
                                            {{ formatDate(invoice.to) }}
                                        </div>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-600">
                                        {{ formatDate(invoice.first_due_date) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm font-semibold text-green-600">
                                        ${{ formatPrice(invoice.amount) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm font-semibold text-blue-600">
                                        <span v-if="bcv">Bs. {{ formatPriceBs(invoice.amount) }}</span>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <Button
                                            @click="selectInvoiceToPay(invoice)"
                                            size="sm"
                                            class="bg-green-600 hover:bg-green-700 text-xs"
                                        >
                                            💰 Pagar
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="bcv" class="p-2 bg-gray-50 border-t text-xs text-gray-500 text-right">
                        Tasa BCV: {{ bcv }} ({{ bcvStore.date }})
                    </div>
                </div>

                <!-- Factura seleccionada para pago -->
                <div v-if="selectedInvoice" class="bg-purple-50 border-l-4 border-purple-400 p-4 rounded">
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-semibold text-purple-900 mb-2">💳 Factura Seleccionada</h4>
                            <div class="space-y-1 text-sm">
                                <p><strong>Número:</strong> {{ selectedInvoice.invoice_number }}</p>
                                <p><strong>Monto USD:</strong> <span class="text-green-600 font-bold">${{ formatPrice(selectedInvoice.amount) }}</span></p>
                                <p v-if="bcv"><strong>Monto Bs:</strong> <span class="text-blue-600 font-bold">Bs. {{ formatPriceBs(selectedInvoice.amount) }}</span></p>
                                <p><strong>Vencimiento:</strong> {{ formatDate(selectedInvoice.first_due_date) }}</p>
                            </div>
                        </div>
                        <Button
                            @click="cancelInvoiceSelection"
                            variant="ghost"
                            size="sm"
                            class="text-purple-600 hover:text-purple-800"
                        >
                            ✕ Cancelar
                        </Button>
                    </div>
                </div>

                <!-- Datos bancarios -->
                <div v-if="showPaymentForm" class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <h4 class="font-semibold mb-2 text-blue-900">📱 Datos para Pago Móvil:</h4>
                    <div class="text-sm space-y-1 text-blue-800">
                        <div>🏦 <strong>Banco:</strong> Nacional de Crédito (0191)</div>
                        <div>👤 <strong>RIF:</strong> J-12569785-7</div>
                        <div>📞 <strong>Teléfono:</strong> 0412-0355541</div>
                    </div>
                </div>

                <!-- Formulario de pago -->
                <div v-if="showPaymentForm && selectedInvoice" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="reference">Referencia del Pago (Últimos 5 dígitos)</Label>
                        <Input
                            id="reference"
                            v-model="paymentForm.reference"
                            placeholder="Ej: 12345"
                            maxlength="5"
                            type="text"
                            pattern="[0-9]*"
                            :disabled="paymentForm.processing"
                        />
                        <p class="text-xs text-gray-500">Ingrese solo los últimos 5 números de la referencia</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="nationality">Nacionalidad</Label>
                            <select
                                id="nationality"
                                v-model="paymentForm.nationality"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                :disabled="paymentForm.processing"
                            >
                                <option value="V">V - Venezolano</option>
                                <option value="E">E - Extranjero</option>
                                <option value="J">J - Jurídico</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label for="id_number">Cédula/RIF</Label>
                            <Input
                                id="id_number"
                                v-model="paymentForm.id_number"
                                placeholder="Número de cédula o RIF"
                                :disabled="paymentForm.processing"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="bank">Banco</Label>
                            <select
                                id="bank"
                                v-model="paymentForm.bank"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                :disabled="paymentForm.processing"
                            >
                                <option value="">Seleccione un banco</option>
                                <option
                                    v-for="bank in getBankOptions()"
                                    :key="bank.value"
                                    :value="bank.value"
                                >
                                    {{ bank.label }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">Teléfono</Label>
                            <Input
                                id="phone"
                                v-model="paymentForm.phone"
                                placeholder="Ej: 04121234567"
                                maxlength="11"
                                type="tel"
                                pattern="[0-9]*"
                                :disabled="paymentForm.processing"
                            />
                            <p class="text-xs text-gray-500">Formato: 04XX-XXXXXXX (sin espacios ni guiones)</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="amount">Monto (Bs)</Label>
                            <Input
                                id="amount"
                                type="number"
                                step="0.01"
                                v-model="paymentForm.amount"
                                placeholder="Monto automático"
                                :disabled="paymentForm.processing"
                                readonly
                            />
                            <p class="text-xs text-gray-500">Monto calculado automáticamente según la factura</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="payment_date">Fecha de Pago</Label>
                            <div class="flex gap-2">
                                <Input
                                    id="payment_date"
                                    type="date"
                                    v-model="paymentForm.payment_date"
                                    :disabled="paymentForm.processing"
                                    class="flex-1"
                                />
                                <Button
                                    type="button"
                                    @click="setTodayDate"
                                    size="sm"
                                    variant="outline"
                                >
                                    Hoy
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="image">Captura del Pago (Opcional)</Label>
                        <Input
                            id="image"
                            type="file"
                            accept="image/*"
                            @change="handleImageUpload"
                            :disabled="paymentForm.processing"
                        />
                        <div v-if="imageFile" class="text-sm text-green-600">
                            ✓ Imagen seleccionada: {{ imageFile.name }}
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter v-if="showPaymentForm && selectedInvoice">
                <Button
                    variant="outline"
                    @click="cancelInvoiceSelection"
                    :disabled="paymentForm.processing"
                >
                    Cancelar
                </Button>
                <Button
                    @click="submitPayment"
                    :disabled="paymentForm.processing || !paymentForm.reference || !paymentForm.amount"
                >
                    {{ paymentForm.processing ? 'Procesando...' : 'Registrar Pago' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
