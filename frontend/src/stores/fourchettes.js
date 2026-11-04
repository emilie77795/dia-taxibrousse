import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api'

export const useFourchettesStore = defineStore('fourchettes', () => {
  // Liste vide au départ — alimentée exclusivement depuis la base de données
  const listeFourchettes = ref([])
  const chargement = ref(false)
  const erreur = ref('')

  async function chargerFourchettes() {
    chargement.value = true
    erreur.value = ''
    try {
      const { data } = await api.get('/api/tarifs')
      listeFourchettes.value = data
    } catch (err) {
      console.error('Erreur lors du chargement des tarifs depuis la base de données', err)
      erreur.value = 'Impossible de charger les données depuis la base.'
    } finally {
      chargement.value = false
    }
  }

  async function ajouterFourchette(nouvelle) {
    chargement.value = true
    try {
      await api.post('/api/tarifs', nouvelle)
      await chargerFourchettes()
    } catch (err) {
      console.error('Erreur ajout tarif en base', err)
      throw err
    } finally {
      chargement.value = false
    }
  }

  async function modifierFourchette(id, donnees) {
    chargement.value = true
    try {
      await api.put(`/api/tarifs/${id}`, donnees)
      await chargerFourchettes()
    } catch (err) {
      console.error('Erreur modification tarif en base', err)
      throw err
    } finally {
      chargement.value = false
    }
  }

  async function supprimerFourchette(id) {
    chargement.value = true
    try {
      await api.delete(`/api/tarifs/${id}`)
      listeFourchettes.value = listeFourchettes.value.filter(f => f.id !== id)
    } catch (err) {
      console.error('Erreur suppression tarif en base', err)
      throw err
    } finally {
      chargement.value = false
    }
  }

  return {
    listeFourchettes,
    chargement,
    erreur,
    chargerFourchettes,
    ajouterFourchette,
    modifierFourchette,
    supprimerFourchette
  }
})
