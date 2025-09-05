import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const useBanksStore = defineStore('banks', () => {
    const banks = ref([])
    const loading = ref(false)
    const error = ref(null)

    const loadBanks = async (retryCount = 0) => {
        const maxRetries = 2 // Máximo 2 reintentos adicionales

        if (banks.value.length > 0) {
            return // Ya están cargados
        }

        loading.value = true
        error.value = null

        try {
            const response = await axios.get('/api/banks')

            if (response.data.success) {
                banks.value = response.data.data
                console.log('Bancos cargados exitosamente')
            } else {
                throw new Error(response.data.message || 'Error al cargar bancos')
            }
        } catch (err) {
            const errorMessage = err.response?.data?.error || err.message || 'Error de conexión al cargar bancos'
            console.error(`Error loading banks (intento ${retryCount + 1}):`, err)

            // Si no hemos alcanzado el máximo de reintentos, intentar de nuevo
            if (retryCount < maxRetries) {
                console.log(`Reintentando cargar bancos... (intento ${retryCount + 2}/${maxRetries + 1})`)
                // Esperar un poco antes del siguiente intento (delay progresivo)
                await new Promise(resolve => setTimeout(resolve, (retryCount + 1) * 1000))
                return loadBanks(retryCount + 1)
            } else {
                // Si ya agotamos los reintentos, establecer el error final
                error.value = `${errorMessage} (falló después de ${maxRetries + 1} intentos)`
                console.error('Error final loading banks después de todos los reintentos:', errorMessage)
            }
        } finally {
            // Solo cambiar loading a false si no vamos a reintentar
            if (retryCount >= maxRetries || banks.value.length > 0) {
                loading.value = false
            }
        }
    }

    const getBankName = (bankCode) => {
        const bank = banks.value.find(b => b.Code === bankCode)
        return bank ? bank.Name : bankCode
    }

    const getBankOptions = () => {
        return banks.value.map(bank => ({
            value: bank.Code,
            label: `${bank.Code} - ${bank.Name}`
        }))
    }

    const reloadBanks = async () => {
        // Forzar recarga limpiando los bancos existentes
        banks.value = []
        return loadBanks()
    }

    return {
        banks,
        loading,
        error,
        loadBanks,
        reloadBanks,
        getBankName,
        getBankOptions
    }
})
