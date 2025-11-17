<script setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Eye, EyeOff } from 'lucide-vue-next';
import QuickPaymentModal from '@/components/QuickPaymentModal.vue';
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { useBanksStore } from '@/stores/banks';

// Constantes para tipos de identificación
const ID_TYPE_ABONADO = 0;
const ID_TYPE_CEDULA_RIF = 1;

const props = defineProps({
    status: String,
    canResetPassword: Boolean,
    recaptchaSiteKey: String,
    test: String,
});

const form = useForm({
    nationality: 'V',
    id_type: ID_TYPE_CEDULA_RIF, // Por defecto cédula/RIF
    id_number: '',
    password: '',
    remember: false,
    'g-recaptcha-response': '',
});

// Estado para el modal de pago rápido
const showQuickPaymentModal = ref(false);

// Estado para mostrar/ocultar contraseña
const showPassword = ref(false);

// Cargar bancos al montar el componente
const banksStore = useBanksStore();
banksStore.loadBanks();

// Variables para reCAPTCHA
const recaptchaRef = ref(null);
const recaptchaWidgetId = ref(null);
const recaptchaLoaded = ref(false);

// Función para renderizar reCAPTCHA
const renderRecaptcha = async () => {
    if (!props.recaptchaSiteKey || !recaptchaRef.value) return;

    // Si ya está cargado, no hacer nada
    if (recaptchaLoaded.value && recaptchaWidgetId.value !== null) return;

    // Esperar a que grecaptcha esté disponible
    let attempts = 0;
    while (!window.grecaptcha && attempts < 50) {
        await new Promise(resolve => setTimeout(resolve, 100));
        attempts++;
    }

    if (!window.grecaptcha) {
        console.error('reCAPTCHA no pudo cargar');
        return;
    }

    // Esperar a que el método render esté disponible
    while (!window.grecaptcha.render && attempts < 50) {
        await new Promise(resolve => setTimeout(resolve, 100));
        attempts++;
    }

    try {
        // Limpiar widget anterior si existe
        if (recaptchaWidgetId.value !== null) {
            window.grecaptcha.reset(recaptchaWidgetId.value);
        }

        // Limpiar el contenido del div antes de renderizar
        if (recaptchaRef.value) {
            recaptchaRef.value.innerHTML = '';
        }

        // Renderizar nuevo widget
        recaptchaWidgetId.value = window.grecaptcha.render(recaptchaRef.value, {
            sitekey: props.recaptchaSiteKey,
            callback: (response) => {
                form['g-recaptcha-response'] = response;
            },
            'expired-callback': () => {
                form['g-recaptcha-response'] = '';
            }
        });

        recaptchaLoaded.value = true;
        console.log('reCAPTCHA renderizado correctamente con ID:', recaptchaWidgetId.value);
    } catch (error) {
        console.error('Error al renderizar reCAPTCHA:', error);
        recaptchaLoaded.value = false;
    }
};

// Función para limpiar reCAPTCHA
const cleanupRecaptcha = () => {
    if (recaptchaWidgetId.value !== null && window.grecaptcha) {
        try {
            window.grecaptcha.reset(recaptchaWidgetId.value);
        } catch (error) {
            console.error('Error al limpiar reCAPTCHA:', error);
        }
    }
    recaptchaWidgetId.value = null;
    recaptchaLoaded.value = false;
    form['g-recaptcha-response'] = '';
};

// Función para inicializar reCAPTCHA
const initializeRecaptcha = async () => {
    if (!props.recaptchaSiteKey) return;

    // Limpiar estado anterior
    cleanupRecaptcha();

    // Esperar al siguiente tick
    await nextTick();

    // Si el script ya está cargado y grecaptcha está disponible, renderizar inmediatamente
    if (window.grecaptcha && window.grecaptcha.render) {
        renderRecaptcha();
        return;
    }

    // Si no, esperar un poco y intentar de nuevo
    setTimeout(() => {
        renderRecaptcha();
    }, 500);
};

// Cargar script de reCAPTCHA
onMounted(async () => {
    // Cargar script si no existe
    if (!document.querySelector('script[src*="recaptcha"]')) {
        const script = document.createElement('script');
        script.src = 'https://www.google.com/recaptcha/api.js';
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);

        // Esperar a que el script se cargue
        await new Promise((resolve) => {
            script.onload = resolve;
            script.onerror = resolve; // Continuar incluso si hay error
        });
    }

    // Inicializar reCAPTCHA inmediatamente
    initializeRecaptcha();

    // Usar un timeout adicional para casos donde no se cargó a la primera
    setTimeout(() => {
        if (props.recaptchaSiteKey && (!recaptchaLoaded.value || recaptchaWidgetId.value === null)) {
            console.log('Reintentando cargar reCAPTCHA...');
            initializeRecaptcha();
        }
    }, 1000);
});

// Limpiar al desmontar
onUnmounted(() => {
    cleanupRecaptcha();
});

const submit = () => {
    // Verificar que el captcha esté completado solo si está habilitado
    if (props.recaptchaSiteKey && !form['g-recaptcha-response']) {
        alert('Por favor, completa el captcha antes de continuar.');
        return;
    }

    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
            // Resetear captcha si está presente
            if (props.recaptchaSiteKey && recaptchaWidgetId.value !== null && window.grecaptcha) {
                try {
                    window.grecaptcha.reset(recaptchaWidgetId.value);
                    form['g-recaptcha-response'] = '';
                } catch (error) {
                    console.error('Error al resetear reCAPTCHA:', error);
                }
            }
        },
    });
};
</script>

<template>
    <AuthBase title="Entra con tu cuenta" description="Ingresa tu cédula o RIF y contraseña para iniciar sesión">
        <Head title="Iniciar sesión" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <!-- Mensaje de error general -->
        <div v-if="form.errors.id_number || form.errors.password" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-red-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-red-800">Error de autenticación</h3>
                    <div class="mt-1 text-sm text-red-700">
                        <p v-if="form.errors.id_number">{{ form.errors.id_number }}</p>
                        <p v-if="form.errors.password">{{ form.errors.password }}</p>
                        <p v-if="!form.errors.id_number && !form.errors.password">
                            Las credenciales ingresadas no son correctas. Por favor, verifica tu número de cédula/RIF/abonado y contraseña.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensaje de rate limiting -->
        <div v-if="form.errors.email" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-yellow-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-yellow-800">Demasiados intentos</h3>
                    <p class="mt-1 text-sm text-yellow-700">{{ form.errors.email }}</p>
                </div>
            </div>
        </div>

        <!-- Mensaje de error de captcha -->
        <div v-if="form.errors['g-recaptcha-response']" class="mb-4 p-4 bg-orange-50 border border-orange-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-orange-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <h3 class="text-sm font-medium text-orange-800">Error de verificación</h3>
                    <p class="mt-1 text-sm text-orange-700">{{ form.errors['g-recaptcha-response'] }}</p>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <!-- <pre>{{ props }}</pre> -->
                <!-- <div class="grid gap-2">
                    <Label for="nationality">Nacionalidad</Label>
                    <select
                        id="nationality"
                        v-model="form.nationality"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :tabindex="1"
                        required
                    >
                        <option value="V">V - Venezolano</option>
                        <option value="E">E - Extranjero</option>
                        <option value="J">J - Jurídico</option>
                    </select>
                    <InputError :message="form.errors.nationality" />
                </div> -->

                <!-- Tipo de identificación -->
                <div class="grid gap-2">
                    <Label for="id_type">Tipo de identificación</Label>
                    <select
                        id="id_type"
                        v-model.number="form.id_type"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :tabindex="1"
                        required
                    >
                        <option :value="ID_TYPE_CEDULA_RIF">Cédula / RIF</option>
                        <option :value="ID_TYPE_ABONADO">Número de Abonado</option>
                    </select>
                    <InputError :message="form.errors.id_type" />
                </div>

                <!-- Nacionalidad (solo si es cédula/RIF) -->
                <!-- <div v-if="form.id_type === ID_TYPE_CEDULA_RIF" class="grid gap-2">
                    <Label for="nationality">Nacionalidad</Label>
                    <select
                        id="nationality"
                        v-model="form.nationality"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :tabindex="2"
                        required
                    >
                        <option value="V">V - Venezolano</option>
                        <option value="E">E - Extranjero</option>
                        <option value="J">J - Jurídico</option>
                    </select>
                    <InputError :message="form.errors.nationality" />
                </div> -->

                <!-- Número de identificación -->
                <div class="grid gap-2">
                    <Label for="id_number">
                        {{ form.id_type === ID_TYPE_CEDULA_RIF ? 'Número de Cédula o RIF' : 'Número de Abonado' }}
                    </Label>
                    <Input
                        id="id_number"
                        type="number"
                        required
                        :tabindex="3"
                        autocomplete="id_number"
                        v-model="form.id_number"
                        placeholder="Solo números"
                        @input="form.id_number = form.id_number.replace(/[^0-9]/g, '')"
                    />
                    <small class="text-xs text-muted-foreground">
                        {{ form.id_type === ID_TYPE_CEDULA_RIF ? 'Ejemplo: 12345678 o 123456789' : 'Ingresa tu número de abonado' }}
                    </small>
                   <!--  <InputError :message="form.errors.id_number" /> -->
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Contraseña</Label>
                        <!-- <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" :tabindex="5">
                            Olvidaste tu contraseña?
                        </TextLink> -->
                    </div>
                    <div class="relative">
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            :tabindex="3"
                            autocomplete="current-password"
                            v-model="form.password"
                            placeholder="Contraseña"
                            class="pr-10"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                            tabindex="-1"
                        >
                            <Eye v-if="!showPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model="form.remember" :tabindex="4" />
                        <span>Recordarme</span>
                    </Label>
                </div>

                <!-- reCAPTCHA -->
                <div class="flex flex-col items-center gap-2">
                    <!-- Debug: mostrar si la site key está presente -->
                    <div v-if="!props.recaptchaSiteKey" class="text-sm text-yellow-600 bg-yellow-100 p-2 rounded">
                        ⚠️ reCAPTCHA no configurado (Site Key no encontrada)
                    </div>

                   <!--  <div v-if="props.recaptchaSiteKey" class="text-sm text-green-600 bg-green-100 p-2 rounded mb-2">
                        ✅ reCAPTCHA configurado: {{ props.recaptchaSiteKey.substring(0, 20) }}...
                    </div> -->

                    <div
                        v-if="props.recaptchaSiteKey"
                        ref="recaptchaRef"
                        class="g-recaptcha"
                    ></div>
                    <InputError :message="form.errors['g-recaptcha-response']" />
                </div>

                <div class="space-y-3">
                    <Button type="submit" class="w-full" :tabindex="5" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        Iniciar sesión
                    </Button>

                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <span class="w-full border-t" />
                        </div>
                        <div class="relative flex justify-center text-xs uppercase">
                            <span class="bg-background px-2 text-muted-foreground">O</span>
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        class="w-full"
                        @click="showQuickPaymentModal = true"
                        :tabindex="6"
                    >
                        💳 Pago Rápido
                    </Button>
                </div>
            </div>

            <!-- <div class="text-center text-sm text-muted-foreground">
                No tienes una cuenta?
                <TextLink :href="route('register')" :tabindex="5">Registrate</TextLink>
            </div> -->
        </form>

        <!-- Modal de pago rápido -->
        <QuickPaymentModal v-model:open="showQuickPaymentModal" />
    </AuthBase>
</template>
