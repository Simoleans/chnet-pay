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

interface Props {
    open?: boolean;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'select-bank', bank: 'bnc' | 'bdv'): void;
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
});

const emit = defineEmits<Emits>();

const handleOpenChange = (open: boolean) => {
    emit('update:open', open);
};

const selectBank = (bank: 'bnc' | 'bdv') => {
    emit('update:open', false);
    emit('select-bank', bank);
};

const goToBiopago = () => {
   /*  emit('update:open', false);
    router.visit(route('bdv.ipg2.checkout')); */

    alert('Próximamente');
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Selecciona el banco donde hiciste el pago</DialogTitle>
                <DialogDescription>
                    Elige el banco con el que deseas <strong>verificar</strong> el pago
                </DialogDescription>
            </DialogHeader>

            <div class="grid grid-cols-2 gap-4 py-2">
                <!-- BNC -->
                <button
                    @click="selectBank('bnc')"
                    class="group flex flex-col items-center gap-3 rounded-xl border-2 p-5 text-center transition-all duration-200 hover:border-primary hover:bg-primary/5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary bg-primary/2 border-primary"
                >
                    <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-red-50 dark:bg-red-950/30 overflow-hidden">
                        <img src="/img/bnc.png" alt="BioPago BDV" class="h-14 w-14 object-contain" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-foreground">BNC</p>
                        <p class="text-xs text-muted-foreground leading-tight mt-0.5">Banco Nacional<br>de Crédito</p>
                    </div>
                </button>

                <!-- BDV -->
                <button
                    @click="selectBank('bdv')"
                    class="group flex flex-col items-center gap-3 rounded-xl border-2 p-5 text-center transition-all duration-200 hover:border-primary hover:bg-primary/6 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary bg-primary/2 border-primary"
                >
                    <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-red-50 dark:bg-red-950/30 overflow-hidden">
                        <img src="/img/bdv.webp" alt="BDV" class="h-14 w-14 object-contain" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-foreground">BDV</p>
                        <p class="text-xs text-muted-foreground leading-tight mt-0.5">Banco de<br>Venezuela</p>
                    </div>
                </button>
            </div>

            <div class="relative my-1">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t border-muted" />
                </div>
                <div class="relative flex">
                    <DialogHeader>
                        <DialogTitle>
                            Pagar con punto de venta digital
                        </DialogTitle>
                        <DialogDescription>
                            Paga directamente desde tu dispositivo
                        </DialogDescription>
                    </DialogHeader>
                </div>
            </div>

            <div class="relative">
                <span class="absolute -top-2 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-amber-100 dark:bg-amber-950/60 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-400 shadow-sm ring-1 ring-amber-200 dark:ring-amber-800 z-10">
                    Próximamente
                </span>
                <button
                    @click="goToBiopago"
                    class="w-full flex items-center justify-center gap-3 rounded-xl border-2 p-4 transition-all duration-200 hover:border-primary hover:bg-primary/5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary bg-primary/2 border-primary"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 dark:bg-red-950/30 overflow-hidden">
                        <img src="/img/bdv.webp" alt="BDV" class="h-8 w-8 object-contain" />
                    </div>
                    <span class="text-sm font-semibold text-gray-400">BioPago BDV</span>
                </button>
            </div>

            <DialogFooter>
                <DialogClose asChild>
                    <Button variant="outline" class="w-full">Cancelar</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
