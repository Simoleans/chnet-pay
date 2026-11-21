<script setup lang="ts">
/**
 * Lo primero que ve el cliente: ¿debo algo? ¿cuánto? → botón para pagar.
 */
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { CheckCircle2, Wallet } from 'lucide-vue-next';
import { useBcvStore } from '@/stores/bcv';
import { formatBs, formatUsd } from '@/lib/money';
import { buildPayAllPayment, buildSingleInvoicePayment, getPendingAmount, getUniqueInvoicingFirmId, isPending } from '@/lib/invoicePayment';

const props = defineProps<{ invoices: any[]; canPay: boolean }>();
const emit = defineEmits<{ (e: 'pay', payload: any): void; (e: 'see-invoices'): void }>();

const { bcv } = storeToRefs(useBcvStore());

const pending = computed(() => (props.invoices || []).filter(isPending));
const totalUsd = computed(() => pending.value.reduce((s, i) => s + getPendingAmount(i), 0));
const totalBs = computed(() => (bcv.value ? totalUsd.value * parseFloat(String(bcv.value)) : 0));
// "Pagar todo" solo si todas son de la misma empresa (mismo pago móvil) — igual que antes
// Con 1 sola factura se usa el mismo objeto que el botón "Pagar" de la fila (incluye client_id)
const canPayAll = computed(() => pending.value.length > 0 && getUniqueInvoicingFirmId(pending.value) !== null);
</script>

<template>
    <!-- Con deuda -->
    <section v-if="pending.length" class="relative overflow-hidden rounded-3xl bg-neutral-950 p-6 text-white dark:bg-neutral-900 dark:ring-1 dark:ring-white/10">
        <div class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full bg-brand/20 blur-3xl" />
        <!-- lg: monto a la izquierda, acción a la derecha (ocupa menos alto) -->
        <div class="relative lg:flex lg:items-center lg:justify-between lg:gap-8">
            <div>
            <div class="flex items-center gap-2 text-[15px] text-white/80">
                <Wallet class="h-5 w-5" />
                {{ pending.length === 1 ? 'Tienes 1 factura por pagar' : `Tienes ${pending.length} facturas por pagar` }}
            </div>

            <p class="mt-3 text-[2.75rem] leading-none font-bold tracking-tight tabular-nums">
                <template v-if="bcv">{{ formatBs(totalBs) }}<span class="ml-2 text-2xl text-white/70">Bs</span></template>
                <template v-else>${{ formatUsd(totalUsd) }}</template>
            </p>
            <p v-if="bcv" class="mt-2 text-[15px] text-white/75 tabular-nums">${{ formatUsd(totalUsd) }} USD · Tasa BCV {{ formatBs(bcv) }}</p>
            </div>

            <button
                v-if="canPay && canPayAll"
                type="button"
                class="mt-6 flex h-14 w-full shrink-0 items-center justify-center rounded-2xl bg-brand text-base font-bold text-brand-foreground transition hover:brightness-95 sm:w-auto sm:px-10 lg:mt-0"
                @click="emit('pay', pending.length === 1 ? buildSingleInvoicePayment(pending[0]) : buildPayAllPayment(pending))"
            >
                {{ pending.length === 1 ? 'Pagar ahora' : 'Pagar todo' }}
            </button>
            <!-- Facturas de empresas distintas: se pagan por separado -->
            <div v-else-if="canPay" class="mt-6 space-y-2 lg:mt-0 lg:max-w-xs lg:text-right">
                <p class="text-[15px] text-white/80">Tus facturas son de empresas distintas, así que se pagan una por una.</p>
                <button
                    type="button"
                    class="flex h-14 w-full items-center justify-center rounded-2xl bg-white text-base font-bold text-neutral-950 transition hover:bg-white/90 sm:w-auto sm:px-10 lg:ml-auto"
                    @click="emit('see-invoices')"
                >
                    Ver mis facturas
                </button>
            </div>
        </div>
    </section>

    <!-- Al día -->
    <section v-else class="flex items-center gap-4 rounded-3xl border-2 border-green-200 bg-green-50 p-6 dark:border-green-900 dark:bg-green-950/30">
        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-brand/20 text-green-700 dark:text-brand">
            <CheckCircle2 class="h-8 w-8" />
        </span>
        <div>
            <p class="text-xl font-bold text-green-900 dark:text-green-100">¡Estás al día!</p>
            <p class="text-[15px] text-green-800 dark:text-green-200">No tienes facturas pendientes por pagar.</p>
        </div>
    </section>
</template>
