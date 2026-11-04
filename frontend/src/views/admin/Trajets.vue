<script setup>
import { ref, onMounted } from 'vue'
import { useTrajetsStore } from '@/stores/trajets'
import api from '@/api'
import Layout from '@/components/admin/Layout.vue'

const storeTrajets = useTrajetsStore()
const listeVehicules = ref([])
const modalOuvert = ref(false)
const trajetEnCours = ref(null)
const messageErreur = ref('')
const enEnvoi = ref(false)

const formulaireTrajet = ref({
  ville_depart: '',
  ville_arrivee: '',
  date: '',
  horaire: '',
  matricule: '',
  prix: ''
})

async function chargerVehicules() {
  try {
    const { data } = await api.get('/api/vehicules')
    listeVehicules.value = data
  } catch (erreur) {
    console.error('Erreur chargement véhicules', erreur)
  }
}

onMounted(() => {
  storeTrajets.chargerTrajets()
  chargerVehicules()
})

function ouvrirModal(trajet = null) {
  trajetEnCours.value = trajet
  messageErreur.value = ''
  if (trajet) {
    formulaireTrajet.value = {
      ville_depart: trajet.ville_depart,
      ville_arrivee: trajet.ville_arrivee,
      date: trajet.date,
      horaire: trajet.horaire,
      matricule: trajet.vehicule?.matricule || '',
      prix: trajet.prix_valeur || (trajet.prix ? trajet.prix.replace(/[^\d]/g, '') : '')
    }
  } else {
    formulaireTrajet.value = { ville_depart: '', ville_arrivee: '', date: '', horaire: '', matricule: '', prix: '' }
  }
  modalOuvert.value = true
}

async function enregistrerTrajet() {
  if (!formulaireTrajet.value.ville_depart || !formulaireTrajet.value.ville_arrivee || !formulaireTrajet.value.date || !formulaireTrajet.value.horaire) {
    messageErreur.value = 'Veuillez remplir tous les champs obligatoires.'
    return
  }

  enEnvoi.value = true
  messageErreur.value = ''

  try {
    if (trajetEnCours.value) {
      await api.put(`/api/trajets/${trajetEnCours.value.id}`, formulaireTrajet.value)
    } else {
      await api.post('/api/trajets', formulaireTrajet.value)
    }

    modalOuvert.value = false
    trajetEnCours.value = null
    await storeTrajets.chargerTrajets()
  } catch (erreur) {
    messageErreur.value = erreur.response?.data?.error || 'Erreur lors de l\'enregistrement du trajet.'
  } finally {
    enEnvoi.value = false
  }
}

async function supprimerTrajet(id) {
  if (confirm('Voulez-vous vraiment supprimer ce trajet ? Cette action est irréversible.')) {
    try {
      await api.delete(`/api/trajets/${id}`)
      await storeTrajets.chargerTrajets()
    } catch (erreur) {
      alert(erreur.response?.data?.error || 'Impossible de supprimer le trajet. Il possède probablement déjà des réservations actives.')
    }
  }
}
</script>

<template>
  <Layout title="Gestion des Trajets & Départs">
    <template #actions>
      <button @click="ouvrirModal()" class="btn btn-primary btn-sm">
        + Nouveau Trajet
      </button>
    </template>

    <div class="bloc-blanc">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="fw-bold mb-0">Tous les Trajets Programmés</h5>
          <span class="text-muted small">Planifiez les départs, assignez les véhicules et suivez le taux de remplissage</span>
        </div>
      </div>

      <div v-if="storeTrajets.chargement" class="text-center py-5 text-muted">
        Chargement des trajets depuis la base de données...
      </div>

      <div v-else-if="storeTrajets.trajets.length === 0" class="text-muted text-center py-5">
        Aucun trajet programmé pour l'instant. Cliquez sur "+ Nouveau Trajet" pour en créer un.
      </div>

      <table v-else class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Itinéraire</th>
            <th>Date & Horaire</th>
            <th>Véhicule Assigné</th>
            <th>Chauffeur</th>
            <th>Remplissage</th>
            <th>Prix Indicatif</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in storeTrajets.trajets" :key="t.id">
            <td class="text-muted">#{{ t.id }}</td>
            <td>
              <span class="fw-bold text-dark">{{ t.ville_depart }}</span>
              <span class="text-muted mx-1">&rarr;</span>
              <span class="fw-bold text-dark">{{ t.ville_arrivee }}</span>
            </td>
            <td>
              <div>{{ t.date }}</div>
              <div class="badge bg-primary">{{ t.horaire }}</div>
            </td>
            <td>
              <span class="badge bg-secondary">{{ t.vehicule?.type || 'Standard' }}</span>
              <span class="small ms-1 font-monospace">({{ t.vehicule?.matricule || 'TBA' }})</span>
            </td>
            <td>{{ t.vehicule?.chauffeur || 'Non assigné' }}</td>
            <td style="min-width: 140px;">
              <div class="d-flex justify-content-between small mb-1">
                <span>{{ t.places_occupees ?? 0 }} / {{ t.places_totales ?? 8 }} places</span>
                <span class="fw-bold text-dark">{{ t.taux_remplissage ?? 0 }}%</span>
              </div>
              <div class="progress" style="height: 6px; background-color: #C0C0BF;">
                <div 
                  class="progress-bar" 
                  :class="(t.taux_remplissage || 0) >= 80 ? 'bg-danger' : ((t.taux_remplissage || 0) >= 50 ? 'bg-warning' : 'bg-primary')"
                  :style="{ width: (t.taux_remplissage || 0) + '%' }"
                ></div>
              </div>
            </td>
            <td class="fw-bold text-dark">{{ t.prix || '20 000 Ar' }}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button @click="ouvrirModal(t)" class="btn btn-outline-primary" title="Modifier"><img src="/icons/autres/document.png" alt="" class="icone-sur-fond-sombre" style="width: 14px; height: 14px;" /></button>
                <button @click="supprimerTrajet(t.id)" class="btn btn-outline-danger" title="Supprimer"><img src="/icons/autres/trash.png" alt="" class="icone-sur-fond-sombre" style="width: 14px; height: 14px;" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modale Ajout / Modification de Trajet -->
    <div v-if="modalOuvert" class="fond-modale">
      <div class="boite-modale" style="max-width: 500px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">{{ trajetEnCours ? 'Modifier le Trajet' : 'Programmer un Nouveau Trajet' }}</h5>
          <button @click="modalOuvert = false" class="btn-close"></button>
        </div>

        <form @submit.prevent="enregistrerTrajet">
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Ville de départ *</label>
              <input v-model="formulaireTrajet.ville_depart" type="text" class="form-control" placeholder="ex: Antananarivo" required />
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Ville d'arrivée *</label>
              <input v-model="formulaireTrajet.ville_arrivee" type="text" class="form-control" placeholder="ex: Toamasina" required />
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Date du départ *</label>
              <input v-model="formulaireTrajet.date" type="date" class="form-control" required />
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Horaire *</label>
              <input v-model="formulaireTrajet.horaire" type="time" class="form-control" required />
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Prix indicatif (Ariary)</label>
            <input 
              v-model="formulaireTrajet.prix" 
              type="number" 
              class="form-control" 
              placeholder="ex: 45000" 
              min="1000" 
            />
            <span class="small text-muted mt-1 d-block">
              Si renseigné, ce prix sera appliqué à ce trajet et ajouté aux Fourchettes de prix.
            </span>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Véhicule assigné</label>
            <select v-model="formulaireTrajet.matricule" class="form-select">
              <option value="">-- Attribuer automatiquement --</option>
              <option v-for="v in listeVehicules" :key="v.id" :value="v.matricule">
                {{ v.matricule }} ({{ v.type }} - {{ v.nombre_places }} places - Chauffeur: {{ v.chauffeur || 'Aucun' }})
              </option>
            </select>
            <span class="small text-muted mt-1 d-block">
              Si aucun véhicule n'est choisi, le système assignera le premier véhicule disponible.
            </span>
          </div>

          <div v-if="messageErreur" class="alert alert-danger py-2 small">{{ messageErreur }}</div>

          <div class="d-flex gap-2 mt-4">
            <button type="button" @click="modalOuvert = false" class="btn btn-light w-50">Annuler</button>
            <button type="submit" class="btn btn-primary w-50 fw-bold" :disabled="enEnvoi">
              <span v-if="enEnvoi" class="spinner-border spinner-border-sm me-1"></span>
              Enregistrer
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
@import "@/assets/css/admin/trajets.css";
</style>
