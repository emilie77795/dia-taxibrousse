<script setup>
import { ref, computed, onMounted } from 'vue'
import Layout from '@/components/admin/Layout.vue'
import api from '@/api'

const reservations = ref([])
const chargement = ref(true)
const filtreStatut = ref('')
const recherche = ref('')

// Modale Remboursement Admin
const modalRemboursementOuvert = ref(false)
const reservationSelectionnee = ref(null)
const placesARembourser = ref(1)
const typeRemboursement = ref('total')
const enTraitement = ref(false)
const messageAction = ref('')
const erreurAction = ref('')

// Modale Report Admin
const modalReportOuvert = ref(false)
const trajetsDisponibles = ref([])
const nouveauTrajetId = ref('')

async function chargerReservations() {
  chargement.value = true
  try {
    const { data } = await api.get('/api/reservations')
    reservations.value = data
  } catch (err) {
    console.error('Erreur chargement réservations admin', err)
  } finally {
    chargement.value = false
  }
}

onMounted(() => {
  chargerReservations()
})

const demandesEnAttente = computed(() => {
  return reservations.value.filter(r => r.statut === 'demande_remboursement')
})

const reservationsFiltrees = computed(() => {
  return reservations.value.filter(r => {
    const matchStatut = !filtreStatut.value || r.statut === filtreStatut.value
    const texte = `${r.ticket} ${r.trajet} ${r.nom_client} ${r.user} ${r.matricule}`.toLowerCase()
    const matchRecherche = !recherche.value || texte.includes(recherche.value.toLowerCase())
    return matchStatut && matchRecherche
  })
})

// Action Rembourser
function ouvrirModalRembourser(res) {
  reservationSelectionnee.value = res
  placesARembourser.value = res.nombre_places || 1
  typeRemboursement.value = 'total'
  messageAction.value = ''
  erreurAction.value = ''
  modalRemboursementOuvert.value = true
}

async function validerRemboursementAdmin() {
  if (!reservationSelectionnee.value) return
  enTraitement.value = true
  erreurAction.value = ''
  messageAction.value = ''

  const nb = typeRemboursement.value === 'total'
    ? (reservationSelectionnee.value.nombre_places || 1)
    : parseInt(placesARembourser.value)

  try {
    const { data } = await api.post(`/api/reservations/${reservationSelectionnee.value.id}/rembourser`, {
      places_a_rembourser: nb
    })
    messageAction.value = data.message || 'Remboursement validé.'
    setTimeout(async () => {
      modalRemboursementOuvert.value = false
      await chargerReservations()
    }, 1500)
  } catch (err) {
    erreurAction.value = err.response?.data?.error || 'Erreur lors du remboursement.'
  } finally {
    enTraitement.value = false
  }
}

// Action Reporter
async function ouvrirModalReporter(res) {
  reservationSelectionnee.value = res
  messageAction.value = ''
  erreurAction.value = ''
  nouveauTrajetId.value = ''

  try {
    // Filtrer par le même type de véhicule
    const idType = res.id_type_vehicule
    let rep
    if (idType) {
      rep = await api.get(`/api/trajets/par-type/${idType}`)
    } else {
      rep = await api.get('/api/trajets')
    }
    trajetsDisponibles.value = rep.data.filter(t => t.id !== res.id_trajet)
    modalReportOuvert.value = true
  } catch (err) {
    alert('Impossible de charger les trajets compatibles pour le report.')
  }
}

async function validerReportAdmin() {
  if (!reservationSelectionnee.value || !nouveauTrajetId.value) {
    erreurAction.value = 'Veuillez sélectionner un nouveau départ.'
    return
  }
  enTraitement.value = true
  erreurAction.value = ''
  messageAction.value = ''

  try {
    const { data } = await api.post(`/api/reservations/${reservationSelectionnee.value.id}/reporter`, {
      nouveau_id_trajet: parseInt(nouveauTrajetId.value)
    })
    messageAction.value = `${data.message} Nouveau ticket : ${data.nouveau_ticket}`
    setTimeout(async () => {
      modalReportOuvert.value = false
      await chargerReservations()
    }, 1500)
  } catch (err) {
    erreurAction.value = err.response?.data?.error || 'Erreur lors du report.'
  } finally {
    enTraitement.value = false
  }
}
</script>

<template>
  <Layout title="Gestion des Réservations & Billets">
    <div class="bloc-blanc mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h5 class="fw-bold mb-0">Toutes les Réservations Voyageurs</h5>
          <span class="text-muted small">Suivi des paiements, traitement des remboursements et des reports de voyage</span>
        </div>

        <button @click="chargerReservations" class="btn btn-outline-secondary btn-sm rounded-pill">
          <img src="/icons/autres/loading.png" alt="" class="icone-action" /> Actualiser
        </button>
      </div>

      <!-- Alerte demandes de remboursement en attente -->
      <div v-if="demandesEnAttente.length > 0" class="alert alert-warning py-2 mb-3 d-flex justify-content-between align-items-center rounded-3">
        <div>
          <strong><img src="/icons/autres/loading.png" alt="" class="icone-action" /> {{ demandesEnAttente.length }} demande(s) de remboursement en attente :</strong> action requise de votre part pour valider le remboursement Mobile Money.
        </div>
        <button 
          @click="filtreStatut = 'demande_remboursement'" 
          class="btn btn-primary btn-sm rounded-pill fw-bold"
        >
          Afficher ces demandes
        </button>
      </div>

      <!-- Filtres -->
      <div class="row g-2 mb-3">
        <div class="col-md-7">
          <input 
            v-model="recherche" 
            type="text" 
            class="form-control" 
            placeholder="Rechercher par N° ticket, passager, trajet, matricule..." 
          />
        </div>
        <div class="col-md-5">
          <select v-model="filtreStatut" class="form-select">
            <option value="">Tous les statuts</option>
            <option value="demande_remboursement">Demandes de Remboursement ({{ demandesEnAttente.length }})</option>
            <option value="confirme">Confirmé (Payé 100%)</option>
            <option value="avance">Avance (50% payé)</option>
            <option value="embarque">Embarqué (Checkpoint OK)</option>
            <option value="reporte">Reporté</option>
            <option value="rembourse">Remboursé</option>
          </select>
        </div>
      </div>

      <!-- Chargement -->
      <div v-if="chargement" class="text-center py-5 text-muted">
        Chargement des réservations...
      </div>

      <!-- Liste vide -->
      <div v-else-if="reservationsFiltrees.length === 0" class="text-center py-5 text-muted">
        Aucune réservation ne correspond à vos critères de recherche.
      </div>

      <!-- Tableau des réservations -->
      <div v-else class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Ticket</th>
              <th>Voyageur</th>
              <th>Trajet & Départ</th>
              <th>Véhicule / Places</th>
              <th>Paiement & Montant</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in reservationsFiltrees" :key="r.id" :class="{ 'table-warning': r.statut === 'demande_remboursement' }">
              <td>
                <span class="badge bg-dark font-monospace fs-6">{{ r.ticket }}</span>
                <span v-if="r.source === 'guichet'" class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Guichet</span>
                <div class="small text-muted mt-1">{{ r.dateReservation }}</div>
              </td>
              <td>
                <strong class="d-block text-dark">{{ r.nom_client }}</strong>
                <span class="small text-muted">{{ r.telephone_client || r.telephone || r.user }}</span>
              </td>
              <td>
                <div class="fw-bold text-dark">{{ r.trajet }}</div>
                <div class="small text-muted">{{ r.date }} à {{ r.horaire }}</div>
              </td>
              <td>
                <div class="text-dark">{{ r.type_vehicule }} ({{ r.matricule }})</div>
                <div class="badge bg-light border small mt-1">
                  {{ r.places.length > 0 ? r.places.map(p => 'P' + p).join(', ') : r.nombre_places + ' pl.' }}
                </div>
              </td>
              <td>
                <div class="fw-bold text-dark">{{ r.montant_paye_texte }}</div>
                <div v-if="r.montant_reste > 0" class="small fw-bold text-danger">
                  Solde dû : {{ r.montant_reste_texte }}
                </div>
                <div class="small text-muted">{{ r.operateur || 'Mobile Money' }}</div>
              </td>
              <td>
                <span v-if="r.statut === 'demande_remboursement'" class="badge bg-danger fw-bold">
                  <img src="/icons/autres/loading.png" alt="" class="icone-action" /> Demande Remboursement
                </span>
                <span v-else-if="r.statut === 'confirme'" class="badge bg-success">Payé 100%</span>
                <span v-else-if="r.statut === 'avance'" class="badge bg-avance">Avance 50%</span>
                <span v-else-if="r.statut === 'reporte'" class="badge bg-secondary">Reporté</span>
                <span v-else-if="r.statut === 'rembourse'" class="badge bg-danger">Remboursé</span>
                <span v-else-if="r.statut === 'embarque'" class="badge bg-primary"><img src="/icons/autres/receipt.png" alt="" class="icone-action" /> Embarqué</span>
                <span v-else class="badge bg-primary">{{ r.statut }}</span>
              </td>
              <td>
                <!-- Bouton prioritaire si demande de remboursement reçue -->
                <button 
                  v-if="r.statut === 'demande_remboursement'" 
                  @click="ouvrirModalRembourser(r)" 
                  class="btn btn-primary btn-sm fw-bold rounded-pill px-3"
                  title="Valider la demande et effectuer le remboursement"
                >
                  Valider Remboursement
                </button>

                <div v-else class="btn-group btn-group-sm">
                  <!-- Bouton Reporter Admin -->
                  <button 
                    v-if="!['reporte', 'rembourse', 'embarque'].includes(r.statut)" 
                    @click="ouvrirModalReporter(r)" 
                    class="btn btn-outline-secondary"
                    title="Changer la date de ce voyage"
                  >
                    Reporter
                  </button>

                  <!-- Bouton Rembourser Admin -->
                  <button 
                    v-if="!['reporte', 'rembourse', 'embarque'].includes(r.statut)" 
                    @click="ouvrirModalRembourser(r)" 
                    class="btn btn-outline-danger"
                    title="Rembourser totalement ou partiellement"
                  >
                    Rembourser
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODALE REMBOURSEMENT ADMIN -->
    <div v-if="modalRemboursementOuvert" class="fond-modale">
      <div class="boite-modale" style="max-width: 480px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Remboursement (Billet {{ reservationSelectionnee?.ticket }})</h5>
          <button @click="modalRemboursementOuvert = false" class="btn-close"></button>
        </div>

        <div v-if="reservationSelectionnee" class="mb-3 p-3 bg-light rounded-3 small">
          <div>Client : <strong>{{ reservationSelectionnee.nom_client }}</strong></div>
          <div>Trajet : <strong>{{ reservationSelectionnee.trajet }}</strong></div>
          <div>Départ : <strong>{{ reservationSelectionnee.date }} à {{ reservationSelectionnee.horaire }}</strong></div>
          <div>Montant Payé : <strong>{{ reservationSelectionnee.montant_paye_texte }}</strong> ({{ reservationSelectionnee.nombre_places }} places)</div>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-bold">Formule :</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" value="total" v-model="typeRemboursement" id="admRembTotal" />
              <label class="form-check-label small" for="admRembTotal">Totalité</label>
            </div>
            <div class="form-check" v-if="reservationSelectionnee?.nombre_places > 1">
              <input class="form-check-input" type="radio" value="partiel" v-model="typeRemboursement" id="admRembPartiel" />
              <label class="form-check-label small" for="admRembPartiel">Partiel</label>
            </div>
          </div>
        </div>

        <div v-if="typeRemboursement === 'partiel'" class="mb-3">
          <label class="form-label small fw-bold">Places à rembourser :</label>
          <input v-model="placesARembourser" type="number" min="1" :max="reservationSelectionnee ? reservationSelectionnee.nombre_places - 1 : 1" class="form-control" />
        </div>

        <div v-if="erreurAction" class="alert alert-danger py-2 small">{{ erreurAction }}</div>
        <div v-if="messageAction" class="alert alert-success py-2 small">{{ messageAction }}</div>

        <div class="d-flex gap-2 mt-4">
          <button type="button" @click="modalRemboursementOuvert = false" class="btn btn-light w-50">Annuler</button>
          <button type="button" @click="validerRemboursementAdmin" class="btn btn-danger w-50" :disabled="enTraitement">
            <span v-if="enTraitement" class="spinner-border spinner-border-sm me-1"></span>
            Valider Remboursement
          </button>
        </div>
      </div>
    </div>

    <!-- MODALE REPORT ADMIN -->
    <div v-if="modalReportOuvert" class="fond-modale">
      <div class="boite-modale" style="max-width: 520px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Reporter le Voyage (Billet {{ reservationSelectionnee?.ticket }})</h5>
          <button @click="modalReportOuvert = false" class="btn-close"></button>
        </div>

        <div v-if="reservationSelectionnee" class="mb-3 p-3 bg-light rounded-3 small">
          <div>Client : <strong>{{ reservationSelectionnee.nom_client }}</strong></div>
          <div>Véhicule imposé (même type) : <strong class="text-primary">{{ reservationSelectionnee.type_vehicule }}</strong></div>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-bold">Sélectionner le nouveau créneau disponible :</label>
          <select v-model="nouveauTrajetId" class="form-select" required>
            <option value="">-- Choisir un départ futur --</option>
            <option v-for="t in trajetsDisponibles" :key="t.id" :value="t.id">
              {{ t.date }} à {{ t.horaire }} — {{ t.ville_depart }} ➔ {{ t.ville_arrivee }} ({{ t.vehicule?.matricule }})
            </option>
          </select>
          <span class="small text-muted mt-1 d-block">
            Seuls les trajets avec le même type de véhicule sont proposés.
          </span>
        </div>

        <div v-if="erreurAction" class="alert alert-danger py-2 small">{{ erreurAction }}</div>
        <div v-if="messageAction" class="alert alert-success py-2 small">{{ messageAction }}</div>

        <div class="d-flex gap-2 mt-4">
          <button type="button" @click="modalReportOuvert = false" class="btn btn-light w-50">Annuler</button>
          <button type="button" @click="validerReportAdmin" class="btn btn-primary w-50" :disabled="enTraitement || !nouveauTrajetId">
            <span v-if="enTraitement" class="spinner-border spinner-border-sm me-1"></span>
            Valider le Report
          </button>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
@import "@/assets/css/admin/layout.css";
@import "@/assets/css/admin/tables.css";
@import "@/assets/css/admin/modales.css";
@import "@/assets/css/admin/reservations.css";
</style>
