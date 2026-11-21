<script setup lang="ts">
/**
 * Facturas del cliente.
 * - Móvil (< lg): tarjetas grandes, fáciles de tocar.
 * - Escritorio (lg+): tabla compacta (una fila por factura) para no hacer scroll infinito.
 * - Las pagadas/anuladas quedan plegadas bajo "Ver facturas pagadas".
 * Emite el MISMO objeto que WisproInvoicesTable (ver lib/invoicePayment.ts).
 */
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { ChevronDown, FileText } from 'lucide-vue-next';
import { useBcvStore } from '@/stores/bcv';
import { formatBs, formatUsd } from '@/lib/money';
import { buildSingleInvoicePayment, getPendingAmount, isPending } from '@/lib/invoicePayment';

const props = defineProps<{ invoices: any[]; canPay: boolean }>();
const emit = defineEmits<{ (e: 'pay', payload: any): void }>();

const { bcv } = storeToRefs(useBcvStore());

const pending = computed(() => (props.invoices || []).filter(isPending));
const others = computed(() => (props.invoices || []).filter((i) => !isPending(i)));
// Si no hay pendientes, mostramos las demás abiertas (si no, la sección quedaría vacía)
const showOthers = ref(false);
const othersVisible = computed(() => showOthers.value || pending.value.length === 0);

const STATE: Record<string, { label: string; cls: string }> = {
    pending: { label: 'Por pagar', cls: 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200' },
    paid: { label: 'Pagada', cls: 'bg-green-100 text-green-900 dark:bg-green-950/60 dark:text-green-200' },
    void: { label: 'Anulada', cls: 'bg-muted text-muted-foreground' },
    draft: { label: 'Borrador', cls: 'bg-muted text-muted-foreground' },
    authorizing: { label: 'Autorizando', cls: 'bg-blue-100 text-blue-900 dark:bg-blue-950/60 dark:text-blue-200' },
    issuing: { label: 'Emitiendo', cls: 'bg-blue-100 text-blue-900 dark:bg-blue-950/60 dark:text-blue-200' },
    authorizing_error: { label: 'Error de autorización', cls: 'bg-red-100 text-red-900 dark:bg-red-950/60 dark:text-red-200' },
};
const stateOf = (s: string) => STATE[s] ?? { label: s, cls: 'bg-muted text-muted-foreground' };

// Pendiente: saldo por pagar. Pagada/otras: total de la factura (antes salía "0,00 Bs")
const shownAmount = (inv: any) => (isPending(inv) ? getPendingAmount(inv) : parseFloat(String(inv.amount ?? inv.balance ?? 0)));
const amountBs = (inv: any) => (bcv.value ? formatBs(shownAmount(inv) * parseFloat(String(bcv.value))) : null);

const fmtDate = (d?: string | null) =>
    d ? new Date(d).toLocaleDateString('es-VE', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const pay = (inv: any) => emit('pay', buildSingleInvoicePayment(inv));
</script>

<template>
    <section id="mis-facturas" class="scroll-mt-20">
        <div class="mb-3 flex items-center gap-2">
            <h2 class="text-lg font-bold">Mis facturas</h2>
            <span v-if="pending.length" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[13px] font-semibold text-amber-900 dark:bg-amber-950/60 dark:text-amber-200">
                {{ pending.length }} por pagar
            </span>
        </div>

        <div v-if="!invoices?.length" class="rounded-2xl border border-dashed p-8 text-center">
            <p class="text-[15px] font-medium">Aún no tienes facturas</p>
            <p class="text-[13px] text-muted-foreground">Cuando se emita tu primera factura aparecerá aquí.</p>
        </div>

        <template v-else>
            <!-- ═════════ ESCRITORIO: tabla compacta ═════════ -->
            <div class="hidden overflow-hidden rounded-2xl border bg-card lg:block">
                <table class="w-full text-left">
                    <thead class="border-b bg-muted/50 text-[13px] text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Factura</th>
                            <th class="px-4 py-2.5 font-medium">Vence</th>
                            <th class="px-4 py-2.5 font-medium">Estado</th>
                            <th class="px-4 py-2.5 text-right font-medium">Monto</th>
                            <th class="w-28 px-4 py-2.5"><span class="sr-only">Acción</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="inv in pending" :key="inv.id" class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <p class="text-[15px] font-semibold">#{{ inv.invoice_number }}</p>
                                <p class="text-[13px] text-muted-foreground">{{ inv.invoicing_firm_company_name }}</p>
                            </td>
                            <td class="px-4 py-3 text-[15px] whitespace-nowrap">{{ fmtDate(inv.first_due_date) }}</td>
                            <td class="px-4 py-3">
                                <span :class="['rounded-full px-2.5 py-1 text-[13px] font-semibold whitespace-nowrap', stateOf(inv.state).cls]">{{ stateOf(inv.state).label }}</span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <p class="text-[15px] font-bold tabular-nums">{{ amountBs(inv) ? `${amountBs(inv)} Bs` : `$${formatUsd(shownAmount(inv))}` }}</p>
                                <p v-if="amountBs(inv)" class="text-[13px] text-muted-foreground tabular-nums">${{ formatUsd(shownAmount(inv)) }} USD</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="canPay"
                                    type="button"
                                    class="h-10 rounded-xl bg-foreground px-5 text-[15px] font-bold text-background transition hover:opacity-90"
                                    @click="pay(inv)"
                                >
                                    Pagar
                                </button>
                            </td>
                        </tr>

                        <template v-if="othersVisible">
                            <tr v-for="inv in others" :key="inv.id" class="text-muted-foreground">
                                <td class="px-4 py-3">
                                    <p class="text-[15px] font-medium text-foreground">#{{ inv.invoice_number }}</p>
                                    <p class="text-[13px]">{{ inv.invoicing_firm_company_name }}</p>
                                </td>
                                <td class="px-4 py-3 text-[15px] whitespace-nowrap">{{ fmtDate(inv.first_due_date) }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['rounded-full px-2.5 py-1 text-[13px] font-semibold whitespace-nowrap', stateOf(inv.state).cls]">{{ stateOf(inv.state).label }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-[15px] whitespace-nowrap tabular-nums">${{ formatUsd(shownAmount(inv)) }} USD</td>
                                <td />
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- ═════════ MÓVIL: tarjetas ═════════ -->
            <div class="space-y-3 lg:hidden">
                <article v-for="inv in pending" :key="inv.id" class="rounded-2xl border border-amber-300 bg-card p-4 dark:border-amber-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted">
                                <FileText class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[15px] font-semibold">Factura #{{ inv.invoice_number }}</p>
                                <p class="truncate text-[13px] text-muted-foreground">{{ inv.invoicing_firm_company_name }}</p>
                                <p class="text-[13px] text-muted-foreground">Vence: {{ fmtDate(inv.first_due_date) }}</p>
                            </div>
                        </div>
                        <span :class="['shrink-0 rounded-full px-2.5 py-1 text-[13px] font-semibold', stateOf(inv.state).cls]">{{ stateOf(inv.state).label }}</span>
                    </div>
                    <div class="mt-3 flex items-end justify-between gap-3 border-t pt-3">
                        <div>
                            <p class="text-lg font-bold tabular-nums">{{ amountBs(inv) ? `${amountBs(inv)} Bs` : `$${formatUsd(shownAmount(inv))}` }}</p>
                            <p v-if="amountBs(inv)" class="text-[13px] text-muted-foreground tabular-nums">${{ formatUsd(shownAmount(inv)) }} USD</p>
                        </div>
                        <button
                            v-if="canPay"
                            type="button"
                            class="h-12 shrink-0 rounded-xl bg-foreground px-6 text-[15px] font-bold text-background transition hover:opacity-90"
                            @click="pay(inv)"
                        >
                            Pagar
                        </button>
                    </div>
                </article>

                <template v-if="othersVisible">
                    <article v-for="inv in others" :key="inv.id" class="flex items-center justify-between gap-3 rounded-2xl border bg-card p-4">
                        <div class="min-w-0">
                            <p class="text-[15px] font-medium">Factura #{{ inv.invoice_number }}</p>
                            <p class="truncate text-[13px] text-muted-foreground">{{ fmtDate(inv.first_due_date) }} · ${{ formatUsd(shownAmount(inv)) }} USD</p>
                        </div>
                        <span :class="['shrink-0 rounded-full px-2.5 py-1 text-[13px] font-semibold', stateOf(inv.state).cls]">{{ stateOf(inv.state).label }}</span>
                    </article>
                </template>
            </div>

            <!-- Facturas pagadas/anuladas plegadas -->
            <button
                v-if="others.length && pending.length"
                type="button"
                class="mt-3 flex items-center gap-1 text-[15px] font-semibold text-foreground/80 underline-offset-4 hover:underline"
                @click="showOthers = !showOthers"
            >
                {{ showOthers ? 'Ocultar facturas pagadas' : `Ver facturas pagadas (${others.length})` }}
                <ChevronDown :class="['h-4 w-4 transition', showOthers && 'rotate-180']" />
            </button>
        </template>
    </section>
</template>
