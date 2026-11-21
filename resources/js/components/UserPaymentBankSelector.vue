<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
    DialogClose,
} from '@/components/ui/dialog';
import { router } from '@inertiajs/vue3';
import { ChevronRight, CreditCard } from 'lucide-vue-next';

interface Props {
    open?: boolean;
    disableBdvPayment?: boolean;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'select-bank', bank: 'bnc' | 'bdv'): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    disableBdvPayment: false,
});

const emit = defineEmits<Emits>();

const handleOpenChange = (open: boolean) => {
    emit('update:open', open);
};

const selectBank = (bank: 'bnc' | 'bdv') => {
    if (bank === 'bdv' && props.disableBdvPayment) {
        return;
    }

    emit('update:open', false);
    emit('select-bank', bank);
};

const goToBiopago = () => {
    if (props.disableBdvPayment) {
        return;
    }

    /* const password = window.prompt('Ingrese la clave para continuar');

    if (password !== '123') {
        window.alert('Clave incorrecta');
        return;
    } */

    emit('update:open', false);
    router.visit(route('bdv.ipg2.checkout'));

    //alert('Próximamente');
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            @open-auto-focus.prevent
            class="gap-0 p-0 sm:max-w-md sm:rounded-3xl max-sm:top-auto max-sm:bottom-0 max-sm:max-w-full max-sm:translate-y-0 max-sm:rounded-t-3xl max-sm:rounded-b-none max-sm:border-x-0 max-sm:border-b-0"
        >
            <DialogHeader class="space-y-1 px-5 pt-5 pb-4 text-left">
                <div class="mx-auto -mt-2 mb-2 h-1.5 w-10 rounded-full bg-muted sm:hidden" />
                <DialogTitle class="text-lg font-bold">¿Cómo quieres pagar?</DialogTitle>
                <DialogDescription class="text-[13px]">
                    Elige el banco al que harás el Pago Móvil para <strong>verificarlo</strong> al instante
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-5 px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                <!-- Pago móvil -->
                <div>
                    <p class="mb-2 text-[13px] font-semibold text-muted-foreground">Pago Móvil</p>
                    <div class="divide-y overflow-hidden rounded-2xl border bg-card">
                        <!-- BNC -->
                        <button
                            type="button"
                            class="group flex w-full items-center gap-3.5 px-4 py-3.5 text-left transition hover:bg-muted/50 focus:outline-none focus-visible:bg-muted"
                            @click="selectBank('bnc')"
                        >
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white ring-1 ring-border">
                                <img src="/img/bnc.png" alt="BNC" class="h-9 w-9 object-contain" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-base font-semibold">BNC</span>
                                <span class="block text-[13px] text-muted-foreground">Banco Nacional de Crédito · Pago Móvil o C2P</span>
                            </span>
                            <ChevronRight class="h-4 w-4 text-muted-foreground transition group-hover:translate-x-0.5" />
                        </button>

                        <!-- BDV -->
                        <button
                            type="button"
                            :disabled="disableBdvPayment"
                            :class="[
                                'group flex w-full items-center gap-3.5 px-4 py-3.5 text-left transition focus:outline-none focus-visible:bg-muted',
                                disableBdvPayment ? 'cursor-not-allowed opacity-50' : 'hover:bg-muted/50',
                            ]"
                            @click="selectBank('bdv')"
                        >
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white ring-1 ring-border">
                                <img src="/img/bdv.webp" alt="BDV" class="h-9 w-9 object-contain" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[15px] font-semibold">BDV</span>
                                <span class="block text-[13px] text-muted-foreground">
                                    {{ disableBdvPayment ? 'No disponible para esta empresa' : 'Banco de Venezuela · Pago Móvil' }}
                                </span>
                            </span>
                            <ChevronRight v-if="!disableBdvPayment" class="h-4 w-4 text-muted-foreground transition group-hover:translate-x-0.5" />
                        </button>
                    </div>
                </div>

                <!-- Punto de venta digital (IPG2 BDV) -->
                <div>
                    <p class="mb-2 text-[13px] font-semibold text-muted-foreground">Punto de venta digital</p>
                    <button
                        type="button"
                        :disabled="disableBdvPayment"
                        :class="[
                            'group flex w-full items-center gap-3.5 rounded-2xl bg-neutral-950 px-4 py-3.5 text-left text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:bg-neutral-900 dark:ring-1 dark:ring-white/10',
                            disableBdvPayment ? 'cursor-not-allowed opacity-50' : 'hover:bg-neutral-800',
                        ]"
                        @click="goToBiopago"
                    >
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white">
                            <img src="/img/bdv.webp" alt="BDV" class="h-9 w-9 object-contain" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[15px] font-semibold">Botón de pago BDV</span>
                            <span class="block text-[13px] text-white/75">Paga con tarjeta desde tu dispositivo</span>
                        </span>
                        <CreditCard class="h-5 w-5 text-brand" />
                    </button>
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="ghost" class="w-full text-muted-foreground">Cancelar</Button>
                    </DialogClose>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
