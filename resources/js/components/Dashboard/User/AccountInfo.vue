<script setup lang="ts">
/**
 * Resumen de la cuenta en una sola tarjeta: abonado, plan, servicio y tasa BCV.
 * Reemplaza (en la vista del cliente) a PlanCard + ContractCard + BcvRateCard.
 */
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { storeToRefs } from 'pinia';
import { Check, Copy, ExternalLink, MapPin } from 'lucide-vue-next';
import { useBcvStore } from '@/stores/bcv';
import { formatBs, formatUsd } from '@/lib/money';

const props = defineProps<{
    userPlan: { name: string; price: number } | null;
    userContract: { state: string; start_date: string | null; latitude?: string | null; longitude?: string | null } | null;
}>();

const page = usePage();
const { bcv, date } = storeToRefs(useBcvStore());
const code = (page.props.auth as any)?.user?.code ?? '';

const copied = ref(false);
const copyCode = async () => {
    try {
        await navigator.clipboard.writeText(String(code));
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch {
        /* sin permiso de portapapeles */
    }
};

const SERVICE: Record<string, { label: string; dot: string }> = {
    enabled: { label: 'Activo', dot: 'bg-green-500' },
    alerted: { label: 'Con aviso', dot: 'bg-amber-500' },
    degraded: { label: 'Velocidad reducida', dot: 'bg-orange-500' },
    disabled: { label: 'Suspendido', dot: 'bg-red-500' },
};
const service = (s?: string) => (s && SERVICE[s]) || { label: s || 'Sin información', dot: 'bg-muted-foreground' };

const since = (d?: string | null) => (d ? new Date(d).toLocaleDateString('es-VE', { month: 'long', year: 'numeric' }) : null);
const mapUrl = () =>
    props.userContract?.latitude && props.userContract?.longitude
        ? `https://www.google.com/maps?q=${parseFloat(props.userContract.latitude)},${parseFloat(props.userContract.longitude)}`
        : '';
</script>

<template>
    <section class="divide-y rounded-2xl border bg-card">
        <!-- Abonado -->
        <div class="flex items-center justify-between gap-3 p-4">
            <div>
                <p class="text-[13px] text-muted-foreground">Tu número de abonado</p>
                <p class="text-2xl font-bold tabular-nums">{{ code || '—' }}</p>
            </div>
            <button
                v-if="code"
                type="button"
                class="flex h-10 items-center gap-1.5 rounded-full bg-muted px-3 text-[13px] font-semibold transition hover:bg-foreground hover:text-background"
                @click="copyCode"
            >
                <Check v-if="copied" class="h-4 w-4" />
                <Copy v-else class="h-4 w-4" />
                {{ copied ? 'Copiado' : 'Copiar' }}
            </button>
        </div>

        <!-- Plan -->
        <div class="p-4">
            <p class="text-[13px] text-muted-foreground">Tu plan</p>
            <template v-if="userPlan">
                <p class="text-[17px] font-semibold">{{ userPlan.name }}</p>
                <p class="text-[15px] tabular-nums">
                    ${{ formatUsd(userPlan.price) }} al mes
                    <span v-if="bcv" class="text-muted-foreground">· {{ formatBs(userPlan.price * parseFloat(String(bcv))) }} Bs</span>
                </p>
            </template>
            <p v-else class="text-[15px] text-muted-foreground">Sin plan asignado</p>
        </div>

        <!-- Servicio -->
        <div class="p-4">
            <p class="text-[13px] text-muted-foreground">Estado del servicio</p>
            <template v-if="userContract">
                <p class="flex items-center gap-2 text-[17px] font-semibold">
                    <span :class="['h-2.5 w-2.5 rounded-full', service(userContract.state).dot]" />
                    {{ service(userContract.state).label }}
                </p>
                <p v-if="since(userContract.start_date)" class="text-[13px] text-muted-foreground">Cliente desde {{ since(userContract.start_date) }}</p>
                <a
                    v-if="mapUrl()"
                    :href="mapUrl()"
                    target="_blank"
                    rel="noopener"
                    class="mt-2 inline-flex items-center gap-1.5 text-[13px] font-semibold underline underline-offset-4"
                >
                    <MapPin class="h-4 w-4" /> Ver ubicación en el mapa
                </a>
            </template>
            <p v-else class="text-[15px] text-muted-foreground">Sin contrato disponible</p>
        </div>

        <!-- Tasa BCV -->
        <div class="flex items-center justify-between gap-3 p-4">
            <div>
                <p class="text-[13px] text-muted-foreground">Tasa BCV {{ date ? `· ${date}` : '' }}</p>
                <p class="text-[17px] font-semibold tabular-nums">{{ bcv ? `${formatBs(bcv)} Bs por dólar` : 'Cargando…' }}</p>
            </div>
            <a
                href="https://www.bcv.org.ve/"
                target="_blank"
                rel="noopener"
                class="flex h-10 items-center gap-1 rounded-full bg-muted px-3 text-[13px] font-semibold transition hover:bg-foreground hover:text-background"
            >
                Verificar <ExternalLink class="h-3.5 w-3.5" />
            </a>
        </div>
    </section>
</template>
