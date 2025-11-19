<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBcvStore } from '@/stores/bcv';
import { useBanksStore } from '@/stores/banks';
import { storeToRefs } from 'pinia';

interface Props {
    user: any;
    error: string | null;
}

const props = defineProps<Props>();

// Store BCV
const bcvStore = useBcvStore();
const { bcv } = storeToRefs(bcvStore);

// Store Bancos
const banksStore = useBanksStore();
banksStore.loadBanks();
const { getBankOptions } = banksStore;

const imageFile = ref<File | null>(null);


const calculateTotalAmount = computed(() => {
    if (!props.user || !bcv.value) return '';
    const totalDebtUSD = props.user.total_debt;
    return (totalDebtUSD * parseFloat(bcv.value)).toFixed(2);
});

const paymentForm = useForm({
    user_id: props.user?.id || '',
    reference: '',
    nationality: 'V',
    id_number: '',
    bank: '',
    phone: '',
    amount: calculateTotalAmount.value,
    payment_date: '',
    image: null as File | null,
});


const handleImageUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        imageFile.value = file;
        paymentForm.image = file;
    }
};

const setTodayDate = () => {
    const today = new Date();
    paymentForm.payment_date = today.toISOString().split('T')[0];
};

const submitPayment = () => {
    paymentForm.user_id = props.user.id;
    paymentForm.post(route('public-payment.store'), {
        preserveScroll: true,
        onSuccess: () => {
            alert('¡Pago registrado exitosamente! Será verificado por nuestro equipo.');
            resetForm();
        },
        onError: (errors) => {
            console.error('Errores:', errors);
        }
    });
};

const resetForm = () => {
    paymentForm.reset();
    imageFile.value = null;
};

const reloadBcvRate = async () => {
    await bcvStore.$reloadBcvAmount();
};
</script>

<template>
    <Head title="Pagar Servicio" />

    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-blue-100 dark:from-gray-900 dark:to-gray-800 py-8 px-4">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-2">
                    💳 Pagar Servicio CHNET
                </h1>
                <p class="text-gray-600 dark:text-gray-400">
                    Realiza tu pago de forma rápida y segura
                </p>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">{{ error }}</p>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div v-if="user" class="bg-white dark:bg-gray-800 rounded-xl shadow-xl overflow-hidden">
                <!-- Client Info Card -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 text-white">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold">Información del Cliente</h2>
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                            <p class="text-xs opacity-90">Código de Abonado</p>
                            <p class="text-2xl font-bold">{{ user.code }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm opacity-90">Nombre</p>
                            <p class="font-semibold text-lg">{{ user.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm opacity-90">Zona</p>
                            <p class="font-semibold text-lg">{{ user.zone || 'N/A' }}</p>
                        </div>
                        <div v-if="user.plan">
                            <p class="text-sm opacity-90">Plan</p>
                            <p class="font-semibold text-lg">{{ user.plan.name }} - ${{ user.plan.price }}</p>
                        </div>
                        <div>
                            <p class="text-sm opacity-90">Deuda Total</p>
                            <p :class="['text-2xl font-bold', user.total_debt > 0 ? 'text-yellow-300' : 'text-green-300']">
                                ${{ user.total_debt }}
                                <span v-if="bcv" class="text-sm">({{ (user.total_debt * parseFloat(bcv)).toFixed(2) }} Bs)</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- BCV Rate Card -->
                <div class="p-6 bg-gray-50 dark:bg-gray-900 border-b">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Tasa BCV</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ bcv ? `${bcv} Bs` : 'Cargando...' }}
                            </p>
                        </div>
                        <Button @click="reloadBcvRate" size="sm" variant="outline">
                            🔄 Actualizar
                        </Button>
                    </div>
                </div>

                <!-- Payment Info Card -->
                <div class="p-6 bg-blue-50 dark:bg-blue-950/30">
                    <h3 class="font-semibold text-lg mb-3 text-blue-900 dark:text-blue-100">
                        🏦 Datos para Pago Móvil:
                    </h3>
                    <div class="space-y-2 text-sm text-blue-800 dark:text-blue-200">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">Banco:</span>
                            <span>Nacional de Crédito (0191)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">RIF:</span>
                            <span>J-12569785-7</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">Teléfono:</span>
                            <span>0412-0355541</span>
                        </div>
                    </div>
                </div>

                <!-- No Debt Message -->
                <div v-if="user.total_debt <= 0" class="p-6 bg-green-50 dark:bg-green-950/30 text-center">
                    <div class="text-green-600 dark:text-green-400 text-xl font-semibold mb-2">
                        ✅ No tienes deuda pendiente
                    </div>
                    <p class="text-green-600 dark:text-green-500">
                        Estás al día con tus pagos
                    </p>
                </div>

                <!-- Payment Form -->
                <div v-if="user.total_debt > 0" class="p-6 space-y-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">
                        Registrar Pago
                    </h3>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <Label for="reference">Referencia del Pago *</Label>
                            <Input
                                id="reference"
                                v-model="paymentForm.reference"
                                placeholder="Número de referencia del pago"
                                :disabled="paymentForm.processing"
                                class="text-lg"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="nationality">Nacionalidad *</Label>
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
                                <Label for="id_number">Cédula/RIF *</Label>
                                <Input
                                    id="id_number"
                                    v-model="paymentForm.id_number"
                                    placeholder="Número sin guiones"
                                    :disabled="paymentForm.processing"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="bank">Banco *</Label>
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
                                <Label for="phone">Teléfono *</Label>
                                <Input
                                    id="phone"
                                    v-model="paymentForm.phone"
                                    placeholder="Teléfono del pago"
                                    :disabled="paymentForm.processing"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="amount">Monto (Bs) *</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    readonly
                                    v-model="paymentForm.amount"
                                    :placeholder="`Monto sugerido: ${calculateTotalAmount}`"
                                    :disabled="paymentForm.processing"
                                    class="text-lg font-semibold cursor-not-allowed bg-gray-200"
                                />
                                <p class="text-xs text-gray-500">
                                    Monto sugerido: {{ calculateTotalAmount }} Bs
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="payment_date">Fecha de Pago *</Label>
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

                    <div class="pt-4 border-t">
                        <Button
                            @click="submitPayment"
                            :disabled="paymentForm.processing || !paymentForm.reference || !paymentForm.amount"
                            class="w-full h-12 text-lg font-semibold"
                            size="lg"
                        >
                            {{ paymentForm.processing ? 'Procesando...' : '✅ Registrar Pago' }}
                        </Button>
                        <p class="text-xs text-center text-gray-500 mt-2">
                            * Tu pago será verificado por nuestro equipo
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-8 text-sm text-gray-600 dark:text-gray-400">
                <p>© 2025 CHNET - Todos los derechos reservados</p>
                <p class="mt-1">¿Necesitas ayuda? Contacta al soporte técnico</p>
            </div>
        </div>
    </div>
</template>

