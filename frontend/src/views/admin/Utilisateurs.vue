<script setup>
import { ref, computed, onMounted } from 'vue'
import Layout from '@/components/admin/Layout.vue'
import api from '@/api'

const utilisateurs = ref([])
const reservations = ref([])
const chargement = ref(true)
const recherche = ref('')

// Modale de détail des réservations d'un utilisateur
const modalReservationsOuvert = ref(false)
const utilisateurSelectionne = ref(null)

async function chargerDonnees() {
  chargement.value = true
  try {
    const [repUsers, repRes] = await Promise.all([
      api.get('/api/utilisateurs'),
      api.get('/api/reservations')
    ])
    utilisateurs.value = repUsers.data
    reservations.value = repRes.data
  } catch (err) {
    console.error('Erreur chargement utilisateurs', err)
  } finally {
    chargement.value = false
  }
}

onMounted(() => {
  chargerDonnees()
})

const utilisateursFiltres = computed(() => {
  return utilisateurs.value.filter(u => {
    const texte = `${u.nom} ${u.prenom} ${u.email} ${u.telephone} ${u.role}`.toLowerCase()
    return !recherche.value || texte.includes(recherche.value.toLowerCase())
  })
})

function getReservationsUtilisateur(user) {
  return reservations.value.filter(r => r.user === user.email)
}

function ouvrirDetailsReservations(user) {
  utilisateurSelectionne.value = user
  modalReservationsOuvert.value = true
}
</script>

<template>
  <Layout title="Voyageurs & Utilisateurs Inscrits">
    <div class="bloc-blanc mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h5 class="fw-bold mb-0">Comptes Utilisateurs & Voyageurs</h5>
          <span class="text-muted small">Consultez les informations des voyageurs et l'historique de leurs réservations</span>
        </div>

        <button @click="chargerDonnees" class="btn btn-outline-secondary btn-sm rounded-pill">
          <img src="/icons/autres/loading.png" alt="" class="icone-action" /> Actualiser
        </button>
      </div>

      <div class="mb-3">
        <input 
          v-model="recherche" 
          type="text" 
          class="form-control" 
          placeholder="Rechercher par nom, prénom, email, téléphone ou rôle..." 
        />
      </div>

      <div v-if="chargement" class="text-center py-5 text-muted">
        Chargement de la liste des utilisateurs...
      </div>

      <div v-else-if="utilisateursFiltres.length === 0" class="text-center py-5 text-muted">
        Aucun utilisateur trouvé.
      </div>

      <div v-else class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Nom & Prénom</th>
              <th>Email</th>
              <th>Téléphone</th>
              <th>Rôle</th>
              <th>Réservations</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in utilisateursFiltres" :key="u.id">
              <td class="text-muted">#{{ u.id }}</td>
              <td>
                <strong class="d-block text-dark">{{ u.prenom }} {{ u.nom }}</strong>
              </td>
              <td>{{ u.email }}</td>
              <td>{{ u.telephone || '—' }}</td>
              <td>
                <span class="badge" :class="u.role === 'administrateur' ? 'bg-danger' : 'bg-primary'">
                  {{ u.role || 'voyageur' }}
                </span>
              </td>
              <td>
                <span class="badge bg-light border">
                  {{ getReservationsUtilisateur(u).length }} voyage(s)
                </span>
              </td>
              <td>
                <button 
                  @click="ouvrirDetailsReservations(u)" 
                  class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
                  title="Voir les détails des billets de ce voyageur"
                >
                  <span>Détails Réservations</span>
                  <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODALE HISTORIQUE DES RÉSERVATIONS DU VOYAGEUR -->
    <div v-if="modalReservationsOuvert" class="fond-modale">
      <div class="boite-modale" style="max-width: 680px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="fw-bold mb-0">Réservations de {{ utilisateurSelectionne?.prenom }} {{ utilisateurSelectionne?.nom }}</h5>
            <span class="small text-muted">{{ utilisateurSelectionne?.email }} • Tel: {{ utilisateurSelectionne?.telephone || '—' }}</span>
          </div>
          <button @click="modalReservationsOuvert = false" class="btn-close"></button>
        </div>

        <div v-if="getReservationsUtilisateur(utilisateurSelectionne).length === 0" class="py-4 text-center text-muted">
          Ce voyageur n'a encore effectué aucune réservation sur DIA TaxiBrousse.
        </div>

        <div v-else class="table-responsive" style="max-height: 400px; overflow-y: auto;">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Ticket</th>
                <th>Trajet</th>
                <th>Date & Heure</th>
                <th>Montant Payé</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in getReservationsUtilisateur(utilisateurSelectionne)" :key="r.id">
                <td><span class="badge bg-dark font-monospace">{{ r.ticket }}</span></td>
                <td class="fw-bold text-dark">{{ r.trajet }}</td>
                <td>{{ r.date }} ({{ r.horaire }})</td>
                <td class="fw-bold text-dark">{{ r.montant_paye_texte }}</td>
                <td>
                  <span v-if="r.statut === 'confirme'" class="badge bg-success">Payé 100%</span>
                  <span v-else-if="r.statut === 'avance'" class="badge bg-avance">Avance 50%</span>
                  <span v-else-if="r.statut === 'reporte'" class="badge bg-secondary">Reporté</span>
                  <span v-else-if="r.statut === 'rembourse'" class="badge bg-danger">Remboursé</span>
                  <span v-else-if="r.statut === 'embarque'" class="badge bg-primary"><img src="/icons/autres/receipt.png" alt="" class="icone-action" /> Embarqué</span>
                  <span v-else class="badge bg-primary">{{ r.statut }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-3 text-end">
          <button @click="modalReservationsOuvert = false" class="btn btn-secondary btn-sm px-4">Fermer</button>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
@import "@/assets/css/admin/layout.css";
@import "@/assets/css/admin/tables.css";
@import "@/assets/css/admin/modales.css";
</style>
