<script setup>
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import QuickPaymentModal from '@/components/QuickPaymentModal.vue';
import { ref, onMounted, onUnmounted, nextTick, watchEffect } from 'vue';
import { useBanksStore } from '@/stores/banks';

const props = defineProps({
    status: String,
    canResetPassword: Boolean,
    recaptchaSiteKey: String,
    test: String,
});

const form = useForm({
    nationality: 'V',
    id_number: '',
    password: '',
    remember: false,
    'g-recaptcha-response': '',
});

// Estado para el modal de pago rápido
const showQuickPaymentModal = ref(false);

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

    try {
        // Limpiar widget anterior si existe
        if (recaptchaWidgetId.value !== null) {
            window.grecaptcha.reset(recaptchaWidgetId.value);
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
    } catch (error) {
        console.error('Error al renderizar reCAPTCHA:', error);
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

// Cargar script de reCAPTCHA
onMounted(async () => {
    if (!document.querySelector('script[src*="recaptcha"]')) {
        const script = document.createElement('script');
        script.src = 'https://www.google.com/recaptcha/api.js';
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
    }

    // Esperar al siguiente tick para asegurar que el DOM esté listo
    await nextTick();

    // Intentar renderizar reCAPTCHA
    if (props.recaptchaSiteKey) {
        renderRecaptcha();
    }
});

// Observar cambios en recaptchaSiteKey para reinicializar
watchEffect(async () => {
    if (props.recaptchaSiteKey && recaptchaRef.value) {
        await nextTick();
        renderRecaptcha();
    }
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

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <!-- <pre>{{ props }}</pre> -->
                <div class="grid gap-2">
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
                </div>

                <div class="grid gap-2">
                    <Label for="id_number">Cédula o RIF</Label>
                    <Input
                        id="id_number"
                        type="text"
                        required
                        :tabindex="2"
                        autocomplete="id_number"
                        v-model="form.id_number"
                        placeholder="Número de cédula o RIF"
                    />
                    <InputError :message="form.errors.id_number" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Contraseña</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" :tabindex="5">
                            Olvidaste tu contraseña?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="Contraseña"
                    />
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
