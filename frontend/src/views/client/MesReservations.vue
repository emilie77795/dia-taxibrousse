<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import Navbar from '@/components/client/Navbar.vue'
import Footer from '@/components/client/Footer.vue'
import ChatBot from '@/components/client/ChatBot.vue'
import { useThemeStore } from '@/stores/theme'
import { t, langueCourante } from '@/i18n'
import api from '@/api'

const routeur = useRouter()
const themeStore = useThemeStore()
const modeSombre = computed(() => themeStore.modeSombre)

const mesReservations = ref([])
const chargement = ref(true)

// Filtre en haut par situation de billet
const filtreStatut = ref('tous')

const filtresStatutListe = computed(() => [
  { val: 'tous', label: t('billets_filtre_tous'), count: mesReservations.value.length },
  { val: 'confirme', label: t('billets_filtre_paye'), count: mesReservations.value.filter(r => r.statut === 'confirme').length },
  { val: 'avance', label: t('billets_filtre_avance'), count: mesReservations.value.filter(r => r.statut === 'avance').length },
  { val: 'rembourse', label: t('billets_filtre_rembourse'), count: mesReservations.value.filter(r => r.statut === 'rembourse').length },
  { val: 'demande_remboursement', label: t('billets_filtre_attente'), count: mesReservations.value.filter(r => r.statut === 'demande_remboursement').length },
  { val: 'reporte', label: t('billets_filtre_reporte'), count: mesReservations.value.filter(r => r.statut === 'reporte').length },
])

const reservationsFiltrees = computed(() => {
  if (filtreStatut.value === 'tous') return mesReservations.value
  return mesReservations.value.filter(res => res.statut === filtreStatut.value)
})

// Modale de remboursement
const modalRemboursementOuvert = ref(false)
const reservationEnCours = ref(null)
const placesARembourser = ref(1)
const typeRemboursement = ref('total')
const messageAction = ref('')
const erreurAction = ref('')
const enTraitement = ref(false)

async function chargerMesReservations() {
  chargement.value = true
  try {
    const { data } = await api.get('/api/mes-reservations')
    mesReservations.value = data
  } catch (erreur) {
    console.error('Erreur chargement des réservations', erreur)
  } finally {
    chargement.value = false
  }
}

function ouvrirModalRemboursement(res) {
  reservationEnCours.value = res
  placesARembourser.value = res.nombre_places || 1
  typeRemboursement.value = 'total'
  messageAction.value = ''
  erreurAction.value = ''
  modalRemboursementOuvert.value = true
}

async function validerRemboursement() {
  if (!reservationEnCours.value) return
  enTraitement.value = true
  erreurAction.value = ''
  messageAction.value = ''

  try {
    const { data } = await api.post(`/api/reservations/${reservationEnCours.value.id}/demander-remboursement`)
    messageAction.value = data.message || 'Votre demande a été transmise à l\'administrateur avec succès.'
    setTimeout(async () => {
      modalRemboursementOuvert.value = false
      await chargerMesReservations()
    }, 1800)
  } catch (err) {
    erreurAction.value = err.response?.data?.error || 'Erreur lors de la transmission de la demande.'
  } finally {
    enTraitement.value = false
  }
}

function allerVersReport(id) {
  routeur.push(`/client/reporter/${id}`)
}

function allerVersTicket(id) {
  routeur.push(`/client/ticket/${id}`)
}

function formaterDate(dateTexte) {
  if (!dateTexte) return '—'
  const locale = langueCourante.value === 'en' ? 'en-GB' : 'fr-FR'
  return new Date(dateTexte).toLocaleDateString(locale, {
    weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'
  })
}

function extraireVilles(trajet) {
  if (!trajet) return { depart: 'Départ', arrivee: 'Arrivée' }
  const separateur = trajet.includes('➔') ? '➔' : (trajet.includes('->') ? '->' : null)
  if (separateur) {
    const parties = trajet.split(separateur).map(p => p.trim())
    return { depart: parties[0] || 'Départ', arrivee: parties[1] || 'Arrivée' }
  }
  return { depart: trajet, arrivee: '' }
}

function codeVille(ville) {
  if (!ville) return 'DIA'
  const v = ville.trim().toLowerCase()
  if (v.includes('antananarivo') || v.includes('tana')) return 'TNR'
  if (v.includes('toamasina') || v.includes('tamatave')) return 'TMM'
  if (v.includes('mahajanga') || v.includes('majunga')) return 'MJN'
  if (v.includes('fianarantsoa')) return 'FIA'
  if (v.includes('toliara') || v.includes('tulear')) return 'TLE'
  if (v.includes('antsiranana') || v.includes('diego')) return 'DIE'
  if (v.includes('morondava')) return 'MOQ'
  if (v.includes('ambositra')) return 'AMB'
  if (v.includes('manakara')) return 'WMR'
  if (v.includes('antsirabe')) return 'ATB'
  return (ville.trim().slice(0, 3) || 'DIA').toUpperCase()
}

onMounted(() => {
  chargerMesReservations()
})
</script>

<template>
  <div class="page-client" :class="{ 'mode-sombre': modeSombre }">
    <!-- Navbar identique -->
    <Navbar />

    <!-- Contenu Principal -->
    <main class="container-xl py-4">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
          <span class="sous-titre-badge">{{ t('billets_badge') }}</span>
          <h1 class="titre-page mb-1 fs-2">{{ t('billets_titre') }}</h1>
          <p class="paragraphe-guide mb-0">{{ t('billets_desc') }}</p>
        </div>

        <!-- Filtre liste déroulante à droite -->
        <div class="filtre-select-wrapper">
          <label class="filtre-select-label">{{ t('billets_filtre_label') }}</label>
          <select class="filtre-select-statut" v-model="filtreStatut">
            <option v-for="f in filtresStatutListe" :key="f.val" :value="f.val">
              {{ f.label }} ({{ f.count }})
            </option>
          </select>
        </div>
      </div>

      <!-- Chargement -->
      <div v-if="chargement" class="text-center py-5 text-muted bg-white rounded-3 border">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div>{{ t('billets_chargement') }}</div>
      </div>

      <!-- Aucune réservation -->
      <div v-else-if="reservationsFiltrees.length === 0" class="py-5 text-center bg-white rounded-3 border">
        <img src="/icons/autres/document.png" alt="Billet" style="width: 48px; height: 48px; opacity: 0.4;" class="mb-3" />
        <h4 class="fs-5 mb-2">{{ t('billets_aucun') }}</h4>
        <p class="text-muted small mb-3">{{ t('billets_aucun_desc') }}</p>
        <button @click="filtreStatut = 'tous'" class="btn btn-sm btn-outline-primary px-3 me-2">
          {{ t('billets_filtre_tous') }}
        </button>
        <router-link to="/client/trajets" class="btn btn-sm btn-primary px-3">
          + {{ t('billets_btn_reserver') }}
        </router-link>
      </div>

      <!-- Liste des Billets -->
      <div v-else class="d-flex flex-column gap-2">
        <div v-for="res in reservationsFiltrees" :key="res.id" class="carte-vol-billet">

          <!-- Colonne gauche : bande orange verticale -->
          <div class="badge-ticket-orange">
            <span class="ticket-ref-vertical">{{ res.ticket }}</span>
            <span class="ticket-label-vertical">{{ t('ticket_numero_label') }}</span>
          </div>

          <!-- Corps principal compact -->
          <div class="carte-vol-corps">

            <!-- Départ : code + ville + date + heure -->
            <div class="col-depart">
              <div class="ville-code-grand">{{ codeVille(extraireVilles(res.trajet).depart) }}</div>
              <div class="ville-nom-compact">{{ extraireVilles(res.trajet).depart }}</div>
              <div class="date-compact">{{ formaterDate(res.date) }}</div>
              <div class="heure-principale">{{ res.horaire || '—' }}</div>
            </div>

            <!-- Axe route avec minibus -->
            <div class="col-axe">
              <div class="axe-trajet">
                <span class="noeud noeud-depart"></span>
                <div class="segment-ligne"></div>
                <div class="icone-minibus-centre" title="Taxi-brousse DIA">
                  <svg class="svg-minibus" viewBox="0 0 24 14" fill="currentColor">
                    <path d="M1 2.5C1 1.7 1.7 1 2.5 1H15.5C16.3 1 17 1.4 17.5 2.1L20.8 6.2C21.1 6.6 21.3 7.1 21.3 7.7V10.5C21.3 11.3 20.6 12 19.8 12H19.2C18.8 10.8 17.6 10 16.3 10C14.9 10 13.7 10.8 13.3 12H9.7C9.3 10.8 8.1 10 6.8 10C5.4 10 4.2 10.8 3.8 12H2.5C1.7 12 1 11.3 1 10.5V2.5ZM4 3V6.2H7.5V3H4ZM9 3V6.2H13.8V3H9ZM15.3 3V6.2H18.8L16.4 3H15.3Z"/>
                    <circle cx="6.8" cy="12" r="1.8"/>
                    <circle cx="16.3" cy="12" r="1.8"/>
                  </svg>
                </div>
                <div class="segment-ligne"></div>
                <span class="noeud noeud-arrivee"></span>
              </div>
            </div>

            <!-- Arrivée : code + ville -->
            <div class="col-arrivee">
              <div class="ville-code-grand">{{ codeVille(extraireVilles(res.trajet).arrivee) }}</div>
              <div class="ville-nom-compact">{{ extraireVilles(res.trajet).arrivee }}</div>
            </div>

            <!-- Séparateur vertical -->
            <div class="separateur-vertical"></div>

            <!-- Infos compactes : véhicule, places, paiement -->
            <div class="col-infos-compact">
              <div class="info-compact-ligne">
                <strong>{{ res.type_vehicule }}</strong>
                <span class="immatriculation"> ({{ res.matricule }})</span>
              </div>
              <div class="info-compact-ligne">
                <span class="info-flat-label">{{ t('places_places_res') }}</span>
                <strong> {{ res.places && res.places.length > 0 ? res.places.map(p => 'N° ' + p).join(', ') : res.nombre_places + ' ' + (res.nombre_places > 1 ? t('billets_passagers') : t('billets_passager')) }}</strong>
              </div>
              <div class="info-compact-ligne">
                <span class="info-flat-label">{{ res.operateur || 'Mobile Money' }}</span>
                <span v-if="res.telephone" class="info-telephone"> ({{ res.telephone }})</span>
              </div>
            </div>

            <!-- Séparateur vertical -->
            <div class="separateur-vertical"></div>

            <!-- Prix + statut + bouton -->
            <div class="col-prix-action">
              <div class="prix-gras">{{ res.montant_paye_texte }}</div>
              <div class="passager-info">{{ res.nombre_places || 1 }} {{ (res.nombre_places || 1) > 1 ? t('billets_passagers') : t('billets_passager') }}</div>
              <div class="statut-ligne">
                <span v-if="res.statut === 'rembourse'" class="badge-statut-pill statut-rembourse">{{ t('billets_filtre_rembourse') }}</span>
                <span v-else-if="res.statut === 'confirme'" class="badge-statut-pill statut-paye">{{ t('billets_filtre_paye') }}</span>
                <span v-else-if="res.statut === 'avance'" class="badge-statut-pill statut-avance">{{ t('billets_filtre_avance') }}</span>
                <span v-else-if="res.statut === 'demande_remboursement'" class="badge-statut-pill statut-attente">{{ t('billets_filtre_attente') }}</span>
                <span v-else-if="res.statut === 'reporte'" class="badge-statut-pill statut-reporte">{{ t('billets_filtre_reporte') }}</span>
                <span v-else class="badge-statut-pill statut-autre">{{ res.statut_texte || res.statut }}</span>
              </div>
              <button @click="allerVersTicket(res.id)" class="btn-action-telecharger">
                <svg class="icone-btn-svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
                {{ t('billets_telecharger') }}
              </button>
              <div v-if="res.peut_reporter || res.peut_rembourser" class="actions-secondaires-discretes">
                <a v-if="res.peut_reporter" href="#" @click.prevent="allerVersReport(res.id)" class="lien-secondaire">{{ t('billets_reporter') }}</a>
                <span v-if="res.peut_reporter && res.peut_rembourser">•</span>
                <a v-if="res.peut_rembourser" href="#" @click.prevent="ouvrirModalRemboursement(res)" class="lien-secondaire text-danger">{{ t('billets_rembourser') }}</a>
              </div>
            </div>

          </div><!-- fin carte-vol-corps -->
        </div>
      </div>
    </main>

    <!-- MODALE DEMANDE DE REMBOURSEMENT -->
    <div v-if="modalRemboursementOuvert" class="fond-modale">
      <div class="boite-modale" style="max-width: 480px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">{{ t('billets_modal_titre') }}</h5>
          <button @click="modalRemboursementOuvert = false" class="btn-close"></button>
        </div>

        <div v-if="reservationEnCours" class="mb-3 p-3 bg-light rounded-3 small">
          <div><strong>{{ t('billets_modal_ticket') }}</strong> {{ reservationEnCours.ticket }}</div>
          <div><strong>{{ t('billets_modal_trajet') }}</strong> {{ reservationEnCours.trajet }}</div>
          <div><strong>{{ t('billets_modal_date') }}</strong> {{ formaterDate(reservationEnCours.date) }} à {{ reservationEnCours.horaire }}</div>
          <div><strong>{{ t('billets_modal_montant') }}</strong> {{ reservationEnCours.montant_paye_texte }} ({{ reservationEnCours.nombre_places }} {{ t('billets_modal_places') }})</div>
        </div>

        <div class="alert alert-info py-2 small mb-3">
          <img src="/icons/message/megaphone.png" alt="Information" class="icone-action" /> {{ t('billets_modal_info') }} ({{ reservationEnCours?.telephone || 'Mobile Money' }}).
        </div>

        <div v-if="erreurAction" class="alert alert-danger py-2 small">{{ erreurAction }}</div>
        <div v-if="messageAction" class="alert alert-success py-2 small">{{ messageAction }}</div>

        <div class="d-flex gap-2 mt-4">
          <button type="button" @click="modalRemboursementOuvert = false" class="btn btn-light w-50">{{ t('billets_modal_annuler') }}</button>
          <button 
            type="button" 
            @click="validerRemboursement" 
            class="btn btn-danger w-50 fw-bold"
            :disabled="enTraitement"
          >
            <span v-if="enTraitement" class="spinner-border spinner-border-sm me-1"></span>
            {{ t('billets_modal_transmettre') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Pied de page commun -->
    <Footer />

    <!-- ChatBot 24h/24 -->
    <ChatBot />
  </div>
</template>

<style scoped>
@import "@/assets/css/client/base.css";
@import "@/assets/css/client/reservation.css";
@import "@/assets/css/admin/modales.css";
</style>
