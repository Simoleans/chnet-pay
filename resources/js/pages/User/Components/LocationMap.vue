<script setup lang="ts">
interface Props {
    contract: {
        latitude: string | null
        longitude: string | null
    } | null
}

const props = defineProps<Props>()

const getMapUrl = () => {
    if (!props.contract || !props.contract.latitude || !props.contract.longitude) {
        return ''
    }
    return `https://maps.google.com/maps?q=${props.contract.latitude},${props.contract.longitude}&hl=es&z=14&output=embed`
}

const getGoogleMapsLink = () => {
    if (!props.contract || !props.contract.latitude || !props.contract.longitude) {
        return ''
    }
    return `https://www.google.com/maps?q=${props.contract.latitude},${props.contract.longitude}`
}
</script>

<template>
    <div v-if="contract && contract.latitude && contract.longitude" class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
        <h2 class="text-lg font-semibold mb-4">📍 Ubicación</h2>
        <div class="w-full h-96 rounded-lg overflow-hidden">
            <iframe
                :src="getMapUrl()"
                width="100%"
                height="100%"
                frameborder="0"
                style="border:0"
            ></iframe>
        </div>
        <div class="mt-2 text-sm text-gray-500">
            Coordenadas: {{ contract.latitude }}, {{ contract.longitude }}
            <a
                :href="getGoogleMapsLink()"
                target="_blank"
                class="ml-2 text-blue-600 hover:underline"
            >
                Ver en Google Maps →
            </a>
        </div>
    </div>
</template>

