<script setup>
import { ref, onMounted } from 'vue'
import Layout from '@/components/admin/Layout.vue'
import api from '@/api'

const voyagesJour = ref([])
const chargement = ref(true)
const voyageSelectionne = ref(null)
const enValidation = ref(false)
const messageAction = ref('')

async function chargerVoyagesDuJour() {
  chargement.value = true
  try {
    const { data } = await api.get('/api/checkpoint/voyages-du-jour')
    voyagesJour.value = data
    if (data.length > 0 && !voyageSelectionne.value) {
      voyageSelectionne.value = data[0]
    } else if (voyageSelectionne.value) {
      voyageSelectionne.value = data.find(v => v.id === voyageSelectionne.value.id) || data[0]
    }
  } catch (err) {
    console.error('Erreur chargement des voyages du jour', err)
  } finally {
    chargement.value = false
  }
}

function selectionnerVoyage(voyage) {
  voyageSelectionne.value = voyage
  messageAction.value = ''
}

async function validerEmbarquementPassager(passager) {
  enValidation.value = true
  messageAction.value = ''
  try {
    const { data } = await api.post(`/api/reservations/${passager.id_reservation}/checkpoint`)
    passager.est_embarque = true
    if (passager.solde_a_payer > 0) {
      messageAction.value = `Pointage validé pour ${passager.client}. Solde de ${passager.solde_a_payer.toLocaleString()} Ar encaissé en espèces au guichet.`
      passager.solde_a_payer = 0
    } else {
      messageAction.value = `Embarquement confirmé pour ${passager.client} (Billet ${passager.ticket}).`
    }
    setTimeout(() => messageAction.value = '', 4500)
  } catch (err) {
    alert(err.response?.data?.error || 'Erreur lors de la validation de l\'embarquement.')
  } finally {
    enValidation.value = false
  }
}

onMounted(() => {
  chargerVoyagesDuJour()
})
</script>

<template>
  <Layout title="Checkpoint & Validation Embarquement">
    <!-- Notice horaire -->
    <div class="carte-consigne">
      <p class="carte-consigne__texte">
        <strong>Consigne Checkpoint :</strong> Cette console liste les départs prévus aujourd'hui. Les passagers doivent se présenter 1h avant le départ pour le pointage des présences et le règlement du solde en espèces.
      </p>
      <button @click="chargerVoyagesDuJour" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold">
        <img src="/icons/autres/loading.png" alt="" class="icone-action" /> Actualiser la liste
      </button>
    </div>

    <div v-if="messageAction" class="alert alert-success py-2 mb-3 shadow-sm">
      <img src="/icons/autres/receipt.png" alt="" class="icone-action" /> {{ messageAction }}
    </div>

    <!-- Chargement -->
    <div v-if="chargement" class="text-center py-5 text-muted">
      Recherche des départs programmés pour la journée en cours...
    </div>

    <!-- Aucun voyage aujourd'hui -->
    <div v-else-if="voyagesJour.length === 0" class="py-5 text-center bg-white rounded-4 border shadow-sm">
      <h5 class="fw-bold mb-2">Aucun départ prévu aujourd'hui</h5>
      <p class="text-muted small">Aucun voyage n'est programmé à la date d'aujourd'hui dans la table des trajets.</p>
      <router-link to="/admin/trajets" class="btn btn-outline-primary btn-sm rounded-pill mt-2">
        Programmer des départs dans l'agenda
      </router-link>
    </div>

    <!-- Interface complète Checkpoint -->
    <div v-else class="row g-4">
      <!-- Colonne Gauche : Liste des départs du jour -->
      <div class="col-lg-4">
        <div class="carte-departs">
          <h6 class="carte-departs__titre">Départs du Jour ({{ voyagesJour.length }})</h6>

          <div class="d-flex flex-column gap-2">
            <div 
              v-for="v in voyagesJour" 
              :key="v.id"
              class="carte-depart"
              :class="{ 'actif': voyageSelectionne?.id === v.id }"
              @click="selectionnerVoyage(v)"
            >
              <div class="carte-depart__haut">
                <span class="horaire-depart">{{ v.horaire }}</span>
                <span class="nb-passagers">{{ v.total_passagers }} passager(s)</span>
              </div>
              <strong class="trajet-depart">{{ v.depart }} ➔ {{ v.arrivee }}</strong>
              <p class="meta-depart">
                Véhicule : <strong>{{ v.vehicule }}</strong> ({{ v.type_vehicule }})
              </p>
              <p class="meta-depart">
                Chauffeur : <strong>{{ v.chauffeur || 'Assigné' }}</strong>
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Colonne Droite : Liste des passagers du départ sélectionné -->
      <div class="col-lg-8" v-if="voyageSelectionne">
        <div class="bloc-blanc p-4 rounded-3 shadow-sm">
          <!-- En-tête du trajet sélectionné -->
          <div class="entete-depart">
            <div>
              <span class="heure-depart-pilule">Départ à {{ voyageSelectionne.horaire }}</span>
              <h4 class="titre-trajet">{{ voyageSelectionne.depart }} &rarr; {{ voyageSelectionne.arrivee }}</h4>
              <p class="details-trajet">
                Véhicule : <strong>{{ voyageSelectionne.vehicule }} ({{ voyageSelectionne.type_vehicule }})</strong> • 
                Chauffeur : <strong>{{ voyageSelectionne.chauffeur || 'Assigné' }}</strong>
              </p>
            </div>
            <div class="text-end">
              <span class="attendus-pilule">{{ voyageSelectionne.total_passagers }} Voyageur(s) attendu(s)</span>
            </div>
          </div>

          <!-- Liste des passagers -->
          <div v-if="voyageSelectionne.passagers.length === 0" class="py-4 text-center text-muted small">
            Aucun passager n'a encore réservé sur ce départ.
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle table-passagers">
              <thead class="table-light">
                <tr>
                  <th>Ticket</th>
                  <th>Passager</th>
                  <th>Places</th>
                  <th>Règlement &amp; Solde</th>
                  <th>Statut Embarquement</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="p in voyageSelectionne.passagers" 
                  :key="p.id_reservation"
                  :class="{ 'ligne-embarque': p.est_embarque }"
                >
                  <td>
                    <span class="ticket-pilule">{{ p.ticket }}</span>
                  </td>
                  <td>
                    <strong class="nom-passager">{{ p.client }}</strong>
                    <span class="tel-passager">{{ p.telephone || '—' }}</span>
                  </td>
                  <td>
                    <span class="place-pilule">
                      {{ p.places.length > 0 ? p.places.map(num => 'P' + num).join(', ') : 'Assignée' }}
                    </span>
                  </td>
                  <td>
                    <div v-if="p.solde_a_payer > 0" class="paiement-du">
                      À encaisser : {{ p.solde_a_payer.toLocaleString() }} Ar (Espèces)
                    </div>
                    <div v-else class="paiement-ok">
                      Payé Intégralement
                    </div>
                  </td>
                  <td>
                    <span v-if="p.est_embarque" class="statut-pilule statut-pilule--embarque">
                      Présent &amp; Embarqué
                    </span>
                    <span v-else class="statut-pilule statut-pilule--attente">
                      En attente de pointage
                    </span>
                  </td>
                  <td>
                    <button 
                      v-if="!p.est_embarque"
                      @click="validerEmbarquementPassager(p)" 
                      class="btn btn-sm btn-pointage rounded-pill px-3 fw-bold"
                      :disabled="enValidation"
                    >
                      {{ p.solde_a_payer > 0 ? 'Encaisser & Valider' : 'Valider Présence' }}
                    </button>
                    <span v-else class="validation-terminee">
                      Validé
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
@import "@/assets/css/admin/layout.css";
@import "@/assets/css/admin/tables.css";
@import "@/assets/css/admin/checkpoint.css";
</style>
