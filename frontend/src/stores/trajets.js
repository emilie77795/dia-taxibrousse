import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api'

export const useTrajetsStore = defineStore('trajets', () => {
  const trajets = ref([])
  const chargement = ref(false)
  const erreur = ref(null)

  async function chargerTrajets() {
    chargement.value = true
    erreur.value = null
    try {
      const { data } = await api.get('/api/trajets')
      trajets.value = data
    } catch (e) {
      erreur.value = e.response?.data?.message || 'Erreur lors du chargement des trajets.'
    } finally {
      chargement.value = false
    }
  }

  return {
    trajets,
    chargement,
    erreur,
    chargerTrajets,

    // Alias de compatibilité
    loading: chargement,
    error: erreur,
    fetchTrajets: chargerTrajets,
  }
})
