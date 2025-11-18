<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'

const page = usePage()

const paymentLink = computed(() => {
    const baseUrl = window.location.origin
    return `${baseUrl}/pagar/${(page.props.auth as any)?.user?.code || ''}`
})

const copyPaymentLink = () => {
    if ((page.props.auth as any)?.user?.code) {
        navigator.clipboard.writeText(paymentLink.value)
        alert('¡Link de pago copiado al portapapeles!')
    }
}

const sharePaymentLink = async () => {
    const link = paymentLink.value
    const text = `Paga tu servicio CHNET aquí: ${link}`

    if (navigator.share) {
        try {
            await navigator.share({
                title: 'Link de Pago CHNET',
                text: text,
                url: link
            })
        } catch (err: any) {
            if (err.name !== 'AbortError') {
                console.error('Error al compartir:', err)
                navigator.clipboard.writeText(link)
                alert('Link copiado al portapapeles. Puedes compartirlo manualmente.')
            }
        }
    } else {
        navigator.clipboard.writeText(link)
        alert('Link copiado al portapapeles. Puedes compartirlo manualmente.')
    }
}
</script>

<template>
    <div class="relative min-h-[200px] overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-gradient-to-br from-purple-50 to-blue-50 dark:from-purple-950/30 dark:to-blue-950/30">
        <div class="p-4 h-full flex flex-col justify-between">
            <div>
                <h3 class="text-lg font-semibold mb-3">🔗 Link de Pago</h3>
                <div class="text-center">
                    <!-- QR Code usando API pública -->
                    <div class="bg-white p-3 rounded-lg inline-block mb-3">
                        <img
                            :src="`https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${paymentLink}`"
                            alt="QR Code"
                            class="w-[120px] h-[120px]"
                        />
                    </div>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                        Escanea para pagar
                    </p>
                </div>
            </div>
            <div class="space-y-2">
                <Button
                    @click="copyPaymentLink"
                    variant="outline"
                    size="sm"
                    class="w-full"
                >
                    📋 Copiar Link
                </Button>
                <Button
                    @click="sharePaymentLink"
                    variant="default"
                    size="sm"
                    class="w-full bg-blue-600 hover:bg-blue-700"
                >
                    📤 Compartir
                </Button>
            </div>
        </div>
    </div>
</template>

