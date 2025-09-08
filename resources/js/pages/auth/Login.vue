<script setup lang="ts">
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
import { ref, onMounted } from 'vue';
import { useBanksStore } from '@/stores/banks';

// Declaración de tipos para reCAPTCHA
declare global {
    interface Window {
        grecaptcha: {
            reset: (widgetId?: number) => void;
            render: (container: string | Element, parameters: any) => number;
        };
        onCaptchaVerified: (response: string) => void;
        onCaptchaExpired: () => void;
    }
}

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    recaptchaSiteKey?: string;
}>();

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

// Cargar script de reCAPTCHA
onMounted(() => {
    if (!document.querySelector('script[src*="recaptcha"]')) {
        const script = document.createElement('script');
        script.src = 'https://www.google.com/recaptcha/api.js';
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
    }

    // Hacer las funciones disponibles globalmente para reCAPTCHA
    (window as any).onCaptchaVerified = (response: string) => {
        form['g-recaptcha-response'] = response;
    };

    (window as any).onCaptchaExpired = () => {
        form['g-recaptcha-response'] = '';
    };
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
            if (props.recaptchaSiteKey && window.grecaptcha && recaptchaRef.value) {
                window.grecaptcha.reset();
                form['g-recaptcha-response'] = '';
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
                <div v-if="props.recaptchaSiteKey" class="flex flex-col items-center gap-2">
                    <div
                        ref="recaptchaRef"
                        class="g-recaptcha"
                        :data-sitekey="props.recaptchaSiteKey"
                        data-callback="onCaptchaVerified"
                        data-expired-callback="onCaptchaExpired"
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
