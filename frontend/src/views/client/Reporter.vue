<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Navbar from '@/components/client/Navbar.vue'
import Footer from '@/components/client/Footer.vue'
import { useThemeStore } from '@/stores/theme'
import { t } from '@/i18n'
import api from '@/api'

const routeur = useRouter()
const route = useRoute()
const themeStore = useThemeStore()
const modeSombre = computed(() => themeStore.modeSombre)

const reservationOrigine = ref(null)
const trajetsMemeType = ref([])
const trajetSelectionne = ref(null)
const nouvellesPlaces = ref([])
const chargement = ref(true)
const enEnregistrement = ref(false)
const messageErreur = ref('')
const messageSucces = ref('')

async function chargerDonnees() {
  chargement.value = true
  const id = parseInt(route.params.id)

  try {
    const { data } = await api.get('/api/reservations/' + id)
    const trouve = data

    if (trouve) {
      reservationOrigine.value = trouve

      if (!trouve.peut_reporter) {
        messageErreur.value = 'Le report est impossible à moins de 2 jours de départ.'
      }

      const idTypeVehicule = trouve.id_type_vehicule
      let repTrajets
      if (idTypeVehicule) {
        repTrajets = await api.get('/api/trajets/par-type/' + idTypeVehicule)
      } else {
        repTrajets = await api.get('/api/trajets')
      }

      const idTrajetOrigine = trouve.id_trajet
      trajetsMemeType.value = repTrajets.data.filter(t => t.id !== idTrajetOrigine && estTrajetFutur(t))

      if (trajetsMemeType.value.length > 0) {
        choisirNouveauTrajet(trajetsMemeType.value[0])
      }
    }
  } catch (err) {
    console.error('Erreur chargement données de report', err)
  } finally {
    chargement.value = false
  }
}

function estTrajetFutur(t) {
  if (!t || !t.date) return false
  const dateTrajet = String(t.date).slice(0, 10)
  const maintenant = new Date()
  const annee = maintenant.getFullYear()
  const mois = String(maintenant.getMonth() + 1).padStart(2, '0')
  const jour = String(maintenant.getDate()).padStart(2, '0')
  const dateDuJour = annee + '-' + mois + '-' + jour

  if (dateTrajet < dateDuJour) return false
  if (dateTrajet > dateDuJour) return true

  if (!t.horaire) return true
  const heures = String(maintenant.getHours()).padStart(2, '0')
  const minutes = String(maintenant.getMinutes()).padStart(2, '0')
  const heureActuelle = heures + ':' + minutes
  const horaireTrajet = String(t.horaire).slice(0, 5)

  return horaireTrajet > heureActuelle
}

function choisirNouveauTrajet(trajet) {
  trajetSelectionne.value = trajet
  const nbPlacesNecessaires = reservationOrigine.value?.nombre_places || 1
  const occupees = trajet.numeros_places_occupees || []
  const placesLibres = []

  for (let i = 1; i <= (trajet.vehicule?.nombre_places || 8); i++) {
    if (!occupees.includes(i)) {
      placesLibres.push(i)
      if (placesLibres.length === nbPlacesNecessaires) break
    }
  }
  nouvellesPlaces.value = placesLibres
}

function getDispositionSieges(type, nombrePlaces) {
  const t = (type || '').toLowerCase()
  const total = nombrePlaces || 14

  // --- 1. BOWING : 24 places passagers ---
  if (t.includes('bowing') || t.includes('boeing') || t.includes('bus')) {
    return [
      ['conducteur', 'vide', 'couloir', 'vide', 'vide'],
      [1, 2, 'couloir', 3, 4],
      [5, 6, 'couloir', 7, 8],
      [9, 10, 'couloir', 11, 12],
      [13, 14, 'couloir', 15, 16],
      [17, 18, 'couloir', 19, 20],
      [21, 22, 'couloir', 23, 24]
    ]
  }

  // --- 2. SYLVER CLASS : 14 places passagers (forme exacte de l'image) ---
  if (t.includes('sylver') || t.includes('silver')) {
    return [
      // Rangée 0 (avant) : Conducteur à gauche, vide, vide, siège 1 tout à droite
      ['conducteur', 'vide', 'vide', 1],
      // Rangée 1 : 4 sièges complets
      [2, 3, 4, 5],
      // Rangée 2 : 2 sièges à gauche, vide à droite (passage porte)
      [6, 7, 'vide', 'vide'],
      // Rangée 3 : 2 sièges à gauche, vide au milieu, 1 siège à droite
      [8, 9, 'vide', 10],
      // Rangée 4 (fond) : 4 sièges complets
      [11, 12, 13, 14]
    ]
  }

  // --- 3. SPRINTER : 18 places passagers (forme exacte de l'image) ---
  if (t.includes('sprinter')) {
    return [
      // Rangée 0 (avant) : Conducteur à gauche, vide, siège 1 et siège 2 à droite
      ['conducteur', 'vide', 1, 2],
      // Rangée 1 : 4 sièges complets
      [3, 4, 5, 6],
      // Rangée 2 : 2 sièges à gauche, vide à droite (passage porte)
      [7, 8, 'vide', 'vide'],
      // Rangée 3 : 2 sièges à gauche, vide au milieu, 1 siège à droite
      [9, 10, 'vide', 11],
      // Rangée 4 : 2 sièges à gauche, vide au milieu, 1 siège à droite
      [12, 13, 'vide', 14],
      // Rangée 5 (fond) : 4 sièges complets
      [15, 16, 17, 18]
    ]
  }

  // Disposition par défaut
  const rangees = [['conducteur', 'vide', 'vide', 1]]
  let num = 2
  while (num <= total) {
    const rangee = []
    rangee.push(num <= total ? num++ : 'vide')
    rangee.push(num <= total ? num++ : 'vide')
    rangee.push(num <= total ? num++ : 'vide')
    rangee.push(num <= total ? num++ : 'vide')
    rangees.push(rangee)
  }
  return rangees
}

function estPlaceOccupee(num) {
  if (typeof num !== 'number') return true
  const occupees = trajetSelectionne.value?.numeros_places_occupees || []
  return occupees.includes(num)
}

function togglePlace(num) {
  if (typeof num !== 'number' || estPlaceOccupee(num)) return

  const nbMax = reservationOrigine.value?.nombre_places || 1
  const index = nouvellesPlaces.value.indexOf(num)

  if (index > -1) {
    if (nouvellesPlaces.value.length > 1) {
      nouvellesPlaces.value.splice(index, 1)
    }
  } else {
    if (nouvellesPlaces.value.length < nbMax) {
      nouvellesPlaces.value.push(num)
      nouvellesPlaces.value.sort((a, b) => a - b)
    } else {
      nouvellesPlaces.value.pop()
      nouvellesPlaces.value.push(num)
      nouvellesPlaces.value.sort((a, b) => a - b)
    }
  }
}

async function validerReport() {
  if (!reservationOrigine.value || !trajetSelectionne.value) return

  const nbMax = reservationOrigine.value.nombre_places || 1
  if (nouvellesPlaces.value.length !== nbMax) {
    messageErreur.value = 'Veuillez sélectionner exactement ' + nbMax + ' place(s) libre(s).'
    return
  }

  enEnregistrement.value = true
  messageErreur.value = ''

  try {
    const { data } = await api.post('/api/reservations/' + reservationOrigine.value.id + '/reporter', {
      nouveau_id_trajet: trajetSelectionne.value.id,
      nouvelles_places: nouvellesPlaces.value
    })

    messageSucces.value = data.message + ' Nouveau billet : ' + data.nouveau_ticket
    setTimeout(() => {
      routeur.push('/client/mes-reservations')
    }, 1800)
  } catch (err) {
    messageErreur.value = err.response?.data?.error || 'Erreur lors du report de votre réservation.'
  } finally {
    enEnregistrement.value = false
  }
}

function formaterDate(dateTexte) {
  if (!dateTexte) return '—'
  return new Date(dateTexte).toLocaleDateString('fr-FR', {
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
  return (ville.trim().slice(0, 3) || 'DIA').toUpperCase()
}

onMounted(() => {
  chargerDonnees()
})

</script>

<template>
  <div class="page-client" :class="{ 'mode-sombre': modeSombre }">
    <Navbar />

    <main class="container-xl py-4">
      <div class="mb-4">
        <router-link to="/client/mes-reservations" class="btn btn-outline-secondary px-3 d-inline-flex align-items-center gap-2">
          <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-gauche" />
          <span>{{ t('reporter_retour') }}</span>
        </router-link>
      </div>

      <div v-if="chargement" class="text-center py-5 text-muted">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div>{{ t('reporter_chargement') }}</div>
      </div>

      <div v-else-if="!reservationOrigine" class="py-5 text-center bg-white rounded-4 border">
        <h4 class="titre-page fs-5">{{ t('places_introuvable') }}</h4>
        <router-link to="/client/mes-reservations" class="btn btn-primary mt-2 d-inline-flex align-items-center gap-2">
          <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-gauche" />
          <span>{{ t('reporter_retour') }}</span>
        </router-link>
      </div>

      <div v-else class="row g-4">
        <div class="col-lg-8">
          <div class="carte-recherche mb-4">
            <h4 class="titre-page fs-5 mb-3">{{ t('reporter_origine_label') }} : {{ reservationOrigine.ticket }}</h4>

            <div class="resume-trajet-billet">
              <div class="col-depart">
                <div class="ville-code-grand">{{ codeVille(extraireVilles(reservationOrigine.trajet).depart) }}</div>
                <div class="ville-nom-compact">{{ extraireVilles(reservationOrigine.trajet).depart }}</div>
              </div>
              <div class="col-axe">
                <div class="axe-trajet">
                  <span class="noeud noeud-depart"></span>
                  <div class="segment-ligne"></div>
                  <div class="icone-minibus-centre">
                    <svg class="svg-minibus" viewBox="0 0 24 14" fill="currentColor">
                      <path d="M1 2.5C1 1.7 1.7 1 2.5 1H15.5C16.3 1 17 1.4 17.5 2.1L20.8 6.2C21.1 6.6 21.3 7.1 21.3 7.7V10.5C21.3 11.3 20.6 12 19.8 12H19.2C18.8 10.8 17.6 10 16.3 10C14.9 10 13.7 10.8 13.3 12H9.7C9.3 10.8 8.1 10 6.8 10C5.4 10 4.2 10.8 3.8 12H2.5C1.7 12 1 11.3 1 10.5V2.5ZM4 3V6.2H7.5V3H4ZM9 3V6.2H13.8V3H9ZM15.3 3V6.2H18.8L16.4 3H15.3Z" />
                      <circle cx="6.8" cy="12.2" r="1.6" />
                      <circle cx="16.3" cy="12.2" r="1.6" />
                    </svg>
                  </div>
                  <div class="segment-ligne"></div>
                  <span class="noeud noeud-arrivee"></span>
                </div>
              </div>
              <div class="col-arrivee">
                <div class="ville-code-grand">{{ codeVille(extraireVilles(reservationOrigine.trajet).arrivee) }}</div>
                <div class="ville-nom-compact">{{ extraireVilles(reservationOrigine.trajet).arrivee }}</div>
              </div>
            </div>

            <div class="small text-muted info-ligne">
              <span class="info-element">{{ t('places_date_dep') }} <strong>{{ formaterDate(reservationOrigine.date) }} à {{ reservationOrigine.horaire }}</strong></span>
              <span class="info-element">{{ t('places_vehicule_type') }} <strong>{{ reservationOrigine.type_vehicule }}</strong></span>
              <span class="info-element">{{ t('reporter_places_retenues') }} <strong>{{ reservationOrigine.nombre_places }} {{ reservationOrigine.nombre_places > 1 ? t('billets_passagers') : t('billets_passager') }}</strong></span>
            </div>
          </div>
          <div class="carte-recherche mb-4">
            <h4 class="titre-page fs-5 mb-3">{{ t('reporter_choisir_trajet') }}</h4>
            <div v-if="trajetsMemeType.length === 0" class="py-4 text-center text-muted">
              {{ t('reporter_aucun_trajet') }}
            </div>
            <div v-else class="row g-3">
              <div v-for="tItem in trajetsMemeType" :key="tItem.id" class="col-md-6">
                <div
                  class="p-3 border rounded-3 h-100"
                  :class="trajetSelectionne?.id === tItem.id ? 'border-primary bg-primary bg-opacity-10 shadow-sm' : 'bg-white'"
                  style="cursor: pointer;"
                  @click="choisirNouveauTrajet(tItem)"
                >
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-primary">{{ tItem.horaire }}</span>
                    <span class="badge bg-light text-dark border small">{{ tItem.places_disponibles ?? 8 }} {{ t('trajets_places_libres') }}</span>
                  </div>
                  <strong class="text-dark d-block mb-1">{{ formaterDate(tItem.date) }}</strong>
                  <div class="small text-muted">{{ tItem.ville_depart }} → {{ tItem.ville_arrivee }}</div>
                  <div class="small text-muted">{{ tItem.vehicule_type }} — {{ tItem.places_disponibles ?? 0 }} {{ t('trajets_places_libres') }}</div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="badge bg-light text-dark border small">{{ t('places_sieges_choisis') }} {{ nouvellesPlaces.map(p => p).join(', ') }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="carte-recherche mb-4" v-if="trajetSelectionne">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
              <h4 class="titre-page fs-5 mb-0">{{ t('reporter_plan_titre') }}</h4>
              <span class="badge bg-primary">{{ nouvellesPlaces.length }} / {{ reservationOrigine.nombre_places }} {{ t('places_places_texte') }}</span>
            </div>

            <p class="paragraphe-guide small mb-3">
              {{ t('reporter_plan_desc') }}
            </p>

            <div class="d-flex gap-3 mb-3 flex-wrap">
              <span class="d-flex align-items-center gap-1 small"><span class="badge-legende libre"></span> {{ t('places_libre') }}</span>
              <span class="d-flex align-items-center gap-1 small"><span class="badge-legende selectionne"></span> {{ t('places_selectionne') }}</span>
              <span class="d-flex align-items-center gap-1 small"><span class="badge-legende occupe"></span> {{ t('places_occupe') }}</span>
              <span class="d-flex align-items-center gap-1 small"><span class="badge-legende conducteur"></span> {{ t('places_conducteur') }}</span>
            </div>

            <!-- Plan visuel du véhicule -->
            <div class="plan-vehicule-visuel">
              <!-- Rangées de sièges selon le type de véhicule (la 1ère rangée inclut le conducteur et les places avant) -->
              <template v-for="(rangee, ri) in getDispositionSieges(trajetSelectionne.vehicule?.type, trajetSelectionne.vehicule?.nombre_places)" :key="ri">
                <div class="plan-rangee" :class="{ 'plan-rangee-avant': ri === 0 }">
                  <template v-for="(cellule, ci) in rangee" :key="ci">
                    <div
                      v-if="cellule === 'couloir'"
                      class="plan-couloir"
                    ></div>
                    <div
                      v-else-if="cellule === 'vide'"
                      class="plan-siege invisible"
                    ></div>
                    <div
                      v-else-if="cellule === 'conducteur'"
                      class="plan-siege conducteur-siege"
                      :title="t('places_conducteur')"
                    >C</div>
                    <div
                      v-else
                      class="plan-siege"
                      :class="{
                        'siege-selectionne': nouvellesPlaces.includes(cellule),
                        'siege-occupe': estPlaceOccupee(cellule)
                      }"
                      @click="togglePlace(cellule)"
                    >{{ cellule }}</div>
                  </template>
                </div>
              </template>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top flex-wrap gap-2">
              <div class="small">
                <span class="text-muted">{{ t('reporter_places_selectionnees_label') }} </span>
                <strong class="text-primary fs-6">
                  {{ nouvellesPlaces.length > 0 ? nouvellesPlaces.map(n => 'N° ' + n).join(', ') : t('places_aucun_choisi') }}
                </strong>
              </div>
              <div class="small text-muted">
                {{ t('places_capacite') }} <strong>{{ trajetSelectionne.vehicule?.nombre_places || 8 }} {{ t('places_places_texte') }}</strong>
              </div>
            </div>
          </div>

        </div>

        <!-- Colonne Droite : Récapitulatif du Report -->
        <div class="col-lg-4">
          <div class="carte-recherche position-sticky" style="top: 20px;">
            <h4 class="titre-page fs-5 mb-3 border-bottom pb-2">{{ t('reporter_recap_titre') }}</h4>

            <div class="mb-3">
              <span class="small text-muted d-block">{{ t('reporter_ancienne_date') }}</span>
              <del class="text-secondary">{{ formaterDate(reservationOrigine.date) }} à {{ reservationOrigine.horaire }}</del>
            </div>

            <div v-if="trajetSelectionne" class="mb-3 p-3 bg-light rounded-3">
              <span class="small text-primary fw-bold d-block">{{ t('reporter_nouvelle_date') }}</span>
              <strong class="fs-6 text-dark">{{ formaterDate(trajetSelectionne.date) }} à {{ trajetSelectionne.horaire }}</strong>
              <div class="small text-muted mt-1">{{ t('reporter_nouvelles_places') }} <strong>{{ nouvellesPlaces.map(n => 'N° ' + n).join(', ') }}</strong></div>
            </div>

            <hr />

            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">{{ t('reporter_paiement_effectue') }}</span>
              <strong class="text-dark">{{ reservationOrigine.montant_paye_texte }}</strong>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
              <span class="fw-bold text-dark">{{ t('reporter_supplement') }}</span>
              <strong class="fs-4 text-success">{{ t('reporter_gratuit') }}</strong>
            </div>

            <div v-if="messageErreur" class="alert alert-danger py-2 small mb-3">{{ messageErreur }}</div>
            <div v-if="messageSucces" class="alert alert-success py-2 small mb-3">{{ messageSucces }}</div>

            <div v-if="reservationOrigine && !reservationOrigine.peut_reporter" class="alert alert-danger py-2 small mb-3 fw-bold">
              <img src="/icons/autres/calendar.png" alt="Départ proche" class="icone-action" /> {{ t('reporter_regle_48h') }}
            </div>

            <button 
              @click="validerReport" 
              class="btn btn-primary w-100 py-3 fw-bold fs-6 shadow-sm"
              :disabled="enEnregistrement || !trajetSelectionne || nouvellesPlaces.length !== reservationOrigine.nombre_places || !reservationOrigine?.peut_reporter"
            >
              <span v-if="enEnregistrement" class="spinner-border spinner-border-sm me-2"></span>
              <span>{{ t('reporter_btn_confirmer') }}</span>
            </button>

            <div class="text-muted small text-center mt-3">
              {{ t('reporter_note_places') }}
            </div>
          </div>
        </div>
      </div>
    </main>

    <Footer />
  </div>
</template>

<style scoped>
@import "@/assets/css/client/base.css";
@import "@/assets/css/client/reservation.css";
@import "@/assets/css/client/reporter.css";
</style>


