import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useBcvStore = defineStore('bcv', () => {
  const bcv = ref(null)
  const date = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const fetchBcvRate = async () => {
    loading.value = true
    error.value = null

    try {
      const res = await fetch('/api/bcv')
      //fetch get a https://servicios.bncenlinea.com:16500/api/Services/BCVRates
      //alert('https://servicios.bncenlinea.com:16500/api/Services/BCVRates')
      //const res = await fetch('https://servicios.bncenlinea.com:16500/api/Services/BCVRates')
      const json = await res.json()

      bcv.value = parseFloat(json?.Rate).toFixed(2) || null
      date.value = new Date(json?.Date).toLocaleDateString('es-ES') || null
    } catch (e) {
      console.error('Error fetching BCV rate:', e)
      error.value = e.message || 'Error al cargar la tasa BCV'
      bcv.value = null
      date.value = null
    } finally {
      loading.value = false
    }
  }

  const $reloadBcvAmount = async () => {
    await fetchBcvRate()
  }

  // Inicializar trae el bcv
  fetchBcvRate()

  return {
    bcv,
    date,
    loading,
    error,
    fetchBcvRate,
    $reloadBcvAmount
  }
})
