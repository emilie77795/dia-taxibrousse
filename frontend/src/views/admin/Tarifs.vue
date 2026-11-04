<script setup>
import { ref, onMounted } from 'vue'
import Layout from '@/components/admin/Layout.vue'
import { useFourchettesStore } from '@/stores/fourchettes'
import api from '@/api'

const storeFourchettes = useFourchettesStore()

// Listes chargées depuis la base de données
const listeTrajets = ref([])
const listeItinerairesUniques = ref([])
const listeTypesVehicules = ref([])

// Modale Ajout / Modification
const modalOuvert = ref(false)
const fourchetteEnCours = ref(null)
const messageErreur = ref('')
const enEnregistrement = ref(false)

const formulaire = ref({
  id_trajet: '',
  id_type_vehicule: '',
  prix: ''
})

async function chargerDonneesBase() {
  await storeFourchettes.chargerFourchettes()
  try {
    const [repTrajets, repTypes] = await Promise.all([
      api.get('/api/trajets'),
      api.get('/api/types-vehicules')
    ])
    listeTrajets.value = repTrajets.data
    
    // Regrouper par itinéraire unique (Ville Départ -> Ville Arrivée)
    const vu = new Set()
    listeItinerairesUniques.value = repTrajets.data.filter(t => {
      const cle = `${t.ville_depart.toLowerCase()}->${t.ville_arrivee.toLowerCase()}`
      if (vu.has(cle)) return false
      vu.add(cle)
      return true
    })

    listeTypesVehicules.value = repTypes.data
  } catch (err) {
    console.error('Erreur chargement trajets/types depuis la base', err)
  }
}

onMounted(() => {
  chargerDonneesBase()
})

function ouvrirModal(fourchette = null) {
  fourchetteEnCours.value = fourchette
  messageErreur.value = ''

  if (fourchette) {
    formulaire.value = {
      id_trajet: fourchette.id_trajet || (listeTrajets.value[0]?.id || ''),
      id_type_vehicule: fourchette.id_type_vehicule || (listeTypesVehicules.value[0]?.id || ''),
      prix: fourchette.prix ? fourchette.prix.replace(/[^\d]/g, '') : '20000'
    }
  } else {
    formulaire.value = {
      id_trajet: listeTrajets.value[0]?.id || '',
      id_type_vehicule: listeTypesVehicules.value[0]?.id || '',
      prix: '20000'
    }
  }
  modalOuvert.value = true
}

function fermerModal() {
  modalOuvert.value = false
  fourchetteEnCours.value = null
  messageErreur.value = ''
}

async function enregistrerFourchette() {
  if (!formulaire.value.id_trajet || !formulaire.value.id_type_vehicule || !formulaire.value.prix) {
    messageErreur.value = 'Veuillez sélectionner le trajet, le type de véhicule et renseigner le prix.'
    return
  }

  enEnregistrement.value = true
  messageErreur.value = ''

  try {
    if (fourchetteEnCours.value) {
      await storeFourchettes.modifierFourchette(fourchetteEnCours.value.id, { ...formulaire.value })
    } else {
      await storeFourchettes.ajouterFourchette({ ...formulaire.value })
    }
    fermerModal()
  } catch (err) {
    messageErreur.value = err.response?.data?.error || 'Erreur lors de l\'enregistrement en base de données.'
  } finally {
    enEnregistrement.value = false
  }
}

async function supprimer(id) {
  if (confirm('Voulez-vous vraiment supprimer ce tarif de la base de données ?')) {
    try {
      await storeFourchettes.supprimerFourchette(id)
    } catch (err) {
      alert('Erreur lors de la suppression en base de données.')
    }
  }
}
</script>

<template>
  <Layout title="Guide des Tarifs Indicatifs & Prix par Trajet (A ➔ B)">
    <template #actions>
      <button @click="ouvrirModal()" class="btn btn-primary btn-sm">
        + Nouveau Tarif Indicatif
      </button>
    </template>

    <div class="bloc-blanc">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="fw-bold mb-0">Grille des Prix & Trajets (A ➔ B)</h5>
          <span class="text-muted small">Tarifs indicatifs consultables par les voyageurs pour connaître le prix d'un trajet d'une ville à une autre.</span>
        </div>
      </div>

      <div v-if="storeFourchettes.chargement" class="text-center py-5 text-muted">
        Chargement des tarifs depuis la base de données...
      </div>

      <div v-else-if="storeFourchettes.listeFourchettes.length === 0" class="text-muted text-center py-5">
        Aucun tarif enregistré dans la table <code>tarif</code>. Cliquez sur "+ Nouveau Tarif" pour en ajouter.
      </div>

      <table v-else class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Trajet (Départ ➔ Arrivée)</th>
            <th>Type de Voiture</th>
            <th>Prix Fixe</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in storeFourchettes.listeFourchettes" :key="f.id">
            <td class="text-muted">#{{ f.id }}</td>
            <td class="fw-bold text-dark">{{ f.titre }}</td>
            <td>
              <span class="badge bg-secondary">{{ f.typeVehicule || 'Standard' }}</span>
            </td>
            <td class="fw-bold text-dark">{{ f.prix }}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button @click="ouvrirModal(f)" class="btn btn-outline-primary" title="Modifier"><img src="/icons/autres/document.png" alt="" class="icone-sur-fond-sombre" style="width: 14px; height: 14px;" /></button>
                <button @click="supprimer(f.id)" class="btn btn-outline-danger" title="Supprimer"><img src="/icons/autres/trash.png" alt="" class="icone-sur-fond-sombre" style="width: 14px; height: 14px;" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modale Ajout / Modification -->
    <div v-if="modalOuvert" class="fond-modale">
      <div class="boite-modale" style="max-width: 520px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">
            {{ fourchetteEnCours ? 'Modifier le Tarif en Base' : 'Nouveau Tarif dans la Base de données' }}
          </h5>
          <button @click="fermerModal" class="btn-close"></button>
        </div>

        <form @submit.prevent="enregistrerFourchette">
          <div class="mb-3">
            <label class="form-label small fw-bold">Itinéraire / Trajet (Départ ➔ Arrivée) *</label>
            <select v-model="formulaire.id_trajet" class="form-select" required>
              <option value="">-- Sélectionner un itinéraire --</option>
              <option v-for="t in listeItinerairesUniques" :key="t.id" :value="t.id">
                {{ t.ville_depart }} ➔ {{ t.ville_arrivee }}
              </option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Type de Voiture / Véhicule (Table `type_vehicule`) *</label>
            <select v-model="formulaire.id_type_vehicule" class="form-select" required>
              <option value="">-- Sélectionner un type de véhicule --</option>
              <option v-for="tv in listeTypesVehicules" :key="tv.id" :value="tv.id">
                {{ tv.nom }}
              </option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Montant du Tarif (Ariary) *</label>
            <input 
              v-model="formulaire.prix" 
              type="number" 
              class="form-control" 
              placeholder="ex: 20000" 
              min="1000" 
              required 
            />
          </div>

          <div v-if="messageErreur" class="alert alert-danger py-2">{{ messageErreur }}</div>

          <div class="d-flex gap-2 mt-4">
            <button type="button" @click="fermerModal" class="btn btn-light w-50">Annuler</button>
            <button type="submit" class="btn btn-primary w-50 fw-bold" :disabled="enEnregistrement">
              <span v-if="enEnregistrement" class="spinner-border spinner-border-sm me-1"></span>
              Enregistrer en Base
            </button>
          </div>
        </form>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
@import "@/assets/css/admin/layout.css";
@import "@/assets/css/admin/tables.css";
@import "@/assets/css/admin/modales.css";
@import "@/assets/css/admin/tarifs.css";
</style>

