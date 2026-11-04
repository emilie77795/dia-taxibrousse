<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Navbar from '@/components/client/Navbar.vue'
import Footer from '@/components/client/Footer.vue'
import ChatBot from '@/components/client/ChatBot.vue'
import { useAuthStore } from '@/stores/auth'
import { useTrajetsStore } from '@/stores/trajets'
import { useThemeStore } from '@/stores/theme'
import { t } from '@/i18n'
import api from '@/api'

const routeur = useRouter()
const route = useRoute()
const auth = useAuthStore()
const storeTrajets = useTrajetsStore()
const themeStore = useThemeStore()
const modeSombre = computed(() => themeStore.modeSombre)


// Données du trajet et sélection
const trajet = ref(null)
const chargement = ref(true)
const placesSelectionnees = ref([])
const modePaiement = ref('MVola')
const numeroTelephone = ref('')
const messageSucces = ref('')
const messageErreur = ref('')
const enEnregistrement = ref(false)

// Option Paiement : 'total' (100%) ou 'avance' (50%)
const typePaiementChoisi = ref('total')

async function chargerTrajet() {
  chargement.value = true
  const id = parseInt(route.params.id)
  if (id) {
    sessionStorage.setItem('dia_dernier_trajet_id', id)
  }
  
  if (storeTrajets.trajets.length === 0) {
    await storeTrajets.chargerTrajets()
  }

  const trouve = storeTrajets.trajets.find(t => t.id === id)
  if (trouve) {
    trajet.value = trouve
  } else {
    try {
      const rep = await api.get('/api/trajets')
      trajet.value = rep.data.find(t => t.id === id)
    } catch (e) {
      console.error('Erreur chargement trajet', e)
    }
  }

  // Restaurer les choix s'il y a une réservation mémorisée avant connexion
  const sauvegardeTexte = sessionStorage.getItem('dia_reservation_en_cours')
  let restaure = false
  if (sauvegardeTexte) {
    try {
      const sauvegarde = JSON.parse(sauvegardeTexte)
      if (sauvegarde.id_trajet === id && Array.isArray(sauvegarde.places) && sauvegarde.places.length > 0) {
        placesSelectionnees.value = sauvegarde.places
        if (sauvegarde.type_paiement) typePaiementChoisi.value = sauvegarde.type_paiement
        if (sauvegarde.mode_paiement) modePaiement.value = sauvegarde.mode_paiement
        if (sauvegarde.numero_telephone) numeroTelephone.value = sauvegarde.numero_telephone
        restaure = true
        messageSucces.value = 'Votre sélection de places et formule a été conservée. Vous pouvez finaliser votre commande !'
      }
    } catch (e) {
      console.warn('Impossible de restaurer la réservation mémorisée', e)
    } finally {
      sessionStorage.removeItem('dia_reservation_en_cours')
    }
  }

  // Sélectionner la première place libre par défaut si non restauré
  if (!restaure && trajet.value) {
    const occupees = trajet.value.numeros_places_occupees || []
    const total = trajet.value.vehicule?.nombre_places || 14
    for (let i = 1; i <= total; i++) {
      if (!occupees.includes(i)) {
        placesSelectionnees.value = [i]
        break
      }
    }
  }

  if (!numeroTelephone.value) {
    numeroTelephone.value = auth.utilisateur?.telephone || ''
  }
  chargement.value = false
}

function estTrajetFutur(t) {
  if (!t || !t.date) return false
  const dateTrajet = String(t.date).slice(0, 10)
  const maintenant = new Date()
  const annee = maintenant.getFullYear()
  const mois = String(maintenant.getMonth() + 1).padStart(2, '0')
  const jour = String(maintenant.getDate()).padStart(2, '0')
  const dateDuJour = `${annee}-${mois}-${jour}`

  if (dateTrajet < dateDuJour) return false
  if (dateTrajet > dateDuJour) return true

  if (!t.horaire) return true
  const heures = String(maintenant.getHours()).padStart(2, '0')
  const minutes = String(maintenant.getMinutes()).padStart(2, '0')
  const heureActuelle = `${heures}:${minutes}`
  const horaireTrajet = String(t.horaire).slice(0, 5)

  return horaireTrajet > heureActuelle
}

const trajetEstPasse = computed(() => {
  return trajet.value ? !estTrajetFutur(trajet.value) : false
})

// Vérifier si une place est déjà occupée par une autre personne
function estPlaceOccupee(num) {
  const occupees = trajet.value?.numeros_places_occupees || []
  return occupees.includes(num)
}

function togglePlace(num) {
  if (estPlaceOccupee(num)) return

  const index = placesSelectionnees.value.indexOf(num)
  if (index > -1) {
    if (placesSelectionnees.value.length > 1) {
      placesSelectionnees.value.splice(index, 1)
    }
  } else {
    placesSelectionnees.value.push(num)
    placesSelectionnees.value.sort((a, b) => a - b)
  }
}

function selectionnerPaiement(mode) {
  modePaiement.value = mode
}

// Calculs financiers
const tarifUnitaire = computed(() => {
  return trajet.value?.prix_valeur || 20000
})

const montantTotalCalcul = computed(() => {
  return placesSelectionnees.value.length * tarifUnitaire.value
})

const montantAPayerMaintenant = computed(() => {
  if (typePaiementChoisi.value === 'avance') {
    return Math.round(montantTotalCalcul.value / 2) // 50% avance
  }
  return montantTotalCalcul.value
})

const soldeRestantCheckpoint = computed(() => {
  return Math.max(0, montantTotalCalcul.value - montantAPayerMaintenant.value)
})

async function finaliserReservation() {
  enEnregistrement.value = true
  messageErreur.value = ''

  try {
    const { data } = await api.post('/api/reservations', {
      id_trajet: trajet.value.id,
      places: placesSelectionnees.value,
      nombre_places: placesSelectionnees.value.length,
      type_paiement: typePaiementChoisi.value,
      montant_paye: montantAPayerMaintenant.value,
      operateur: modePaiement.value,
      numero_telephone: numeroTelephone.value
    })

    messageSucces.value = `Réservation validée avec succès ! N° Ticket : ${data.ticket}`
    
    setTimeout(() => {
      routeur.push('/client/mes-reservations')
    }, 1800)
  } catch (erreur) {
    messageErreur.value = erreur.response?.data?.error || 'Erreur lors de la validation de votre réservation.'
  } finally {
    enEnregistrement.value = false
  }
}

async function confirmerReservation() {
  if (!trajet.value) return

  if (trajetEstPasse.value) {
    messageErreur.value = 'Ce trajet a déjà pris le départ. Les réservations sont fermées pour ce créneau.'
    return
  }

  if (placesSelectionnees.value.length === 0) {
    messageErreur.value = 'Veuillez sélectionner au moins une place libre.'
    return
  }

  if (!numeroTelephone.value.trim()) {
    messageErreur.value = `Veuillez renseigner votre numéro de téléphone pour le règlement ${modePaiement.value}.`
    return
  }

  // Si le voyageur n'est pas connecté : mémoriser sa réservation et le rediriger vers la vraie page de connexion
  if (!auth.estConnecte) {
    sessionStorage.setItem('dia_reservation_en_cours', JSON.stringify({
      id_trajet: trajet.value.id,
      places: placesSelectionnees.value,
      type_paiement: typePaiementChoisi.value,
      mode_paiement: modePaiement.value,
      numero_telephone: numeroTelephone.value
    }))
    routeur.push({
      path: '/client/login',
      query: { redirection: `/client/places/${trajet.value.id}` }
    })
    return
  }

  await finaliserReservation()
}

// Code ville compact (TNR, TMM...) — même logique que la liste des billets
function codeVille(ville) {
  if (!ville) return 'DIA'
  const v = ville.trim().toLowerCase()
  if (v.includes('antananarivo') || v.includes('tana')) return 'TNR'
  if (v.includes('toamasina') || v.includes('tamatave')) return 'TMM'
  if (v.includes('mahajanga') || v.includes('majunga')) return 'MJN'
  return (ville.trim().slice(0, 3) || 'DIA').toUpperCase()
}

function formaterDate(dateTexte) {
  if (!dateTexte) return '—'
  return new Date(dateTexte).toLocaleDateString('fr-FR', {
    weekday: 'long', day: '2-digit', month: 'long', year: 'numeric'
  })
}

function getDispositionSieges(type, nombrePlaces) {
  const t = (type || '').toLowerCase()
  const total = nombrePlaces || 14

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

  if (t.includes('sylver') || t.includes('silver')) {
    return [
      ['conducteur', 'vide', 'vide', 1],
      [2, 3, 4, 5],
      [6, 7, 'vide', 'vide'],
      [8, 9, 'vide', 10],
      [11, 12, 13, 14]
    ]
  }

  if (t.includes('sprinter')) {
    return [
      ['conducteur', 'vide', 1, 2],
      [3, 4, 5, 6],
      [7, 8, 'vide', 'vide'],
      [9, 10, 'vide', 11],
      [12, 13, 'vide', 14],
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

onMounted(() => {
  chargerTrajet()
})
</script>

<template>
  <div class="page-client" :class="{ 'mode-sombre': modeSombre }">
    <!-- Navbar identique -->
    <Navbar />

    <!-- Contenu Principal -->
    <main class="container-xl py-4">
      <div class="mb-4">
        <router-link to="/client/trajets" class="btn btn-outline-secondary px-3 d-inline-flex align-items-center gap-2">
          <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-gauche" />
          <span>{{ t('places_retour') }}</span>
        </router-link>
      </div>

      <!-- Chargement -->
      <div v-if="chargement" class="text-center py-5 text-muted">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div>{{ t('places_chargement') }}</div>
      </div>

      <!-- Trajet introuvable -->
      <div v-else-if="!trajet" class="py-5 text-center bg-white rounded-4 border">
        <h4 class="titre-page fs-5">{{ t('places_introuvable') }}</h4>
        <p class="paragraphe-guide small">{{ t('places_introuvable_desc') }}</p>
        <router-link to="/client/trajets" class="btn btn-primary mt-3">
          {{ t('places_voir_disponibles') }}
        </router-link>
      </div>

      <div v-else class="row g-4">
        <div class="col-lg-8">
          <div class="carte-recherche mb-4">
            <div class="resume-meta-vehicule">
              <div class="meta-vehicule-gauche">
                <span class="vehicule-type">{{ trajet.vehicule?.type || 'Véhicule' }}</span>
                <span class="vehicule-matricule">Matricule : {{ trajet.vehicule?.matricule }}</span>
              </div>
              <div class="meta-depart-droite">
                <span class="depart-label">{{ t('places_depart_label') }}</span>
                <span class="depart-heure">{{ trajet.horaire }}</span>
              </div>
            </div>

            <div class="resume-trajet-billet">
              <div class="col-depart">
                <div class="ville-code-grand">{{ codeVille(trajet.ville_depart) }}</div>
                <div class="ville-nom-compact">{{ trajet.ville_depart }}</div>
              </div>
              <div class="col-axe">
                <div class="axe-trajet">
                  <span class="noeud noeud-depart"></span>
                  <div class="segment-ligne"></div>
                  <div class="icone-minibus-centre" title="Taxi-brousse DIA">
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
                <div class="ville-code-grand">{{ codeVille(trajet.ville_arrivee) }}</div>
                <div class="ville-nom-compact">{{ trajet.ville_arrivee }}</div>
              </div>
            </div>
            <div class="text-secondary small">
              {{ t('places_date_label') }} <strong>{{ formaterDate(trajet.date) }}</strong> • {{ t('places_chauffeur_label') }} <strong>{{ trajet.vehicule?.chauffeur || t('places_chauffeur_assigne') }}</strong>
            </div>
          </div>

          <!-- 1. PLAN DES PLACES DU VÉHICULE -->
          <div class="carte-recherche mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
              <h3 class="titre-page fs-5 mb-0">{{ t('places_plan_titre') }}</h3>
              <span class="compte-places">{{ placesSelectionnees.length }} {{ t('places_selectionnees') }}</span>
            </div>

            <p class="paragraphe-guide small mb-3">
              {{ t('places_consigne') }}
            </p>

            <!-- Légende -->
            <div class="d-flex gap-3 mb-3 flex-wrap">
              <span class="d-flex align-items-center gap-1 small"><span class="badge-legende libre"></span> {{ t('places_libre') }}</span>
              <span class="d-flex align-items-center gap-1 small"><span class="badge-legende selectionne"></span> {{ t('places_selectionne') }}</span>
              <span class="d-flex align-items-center gap-1 small"><span class="badge-legende occupe"></span> {{ t('places_occupe') }}</span>
              <span class="d-flex align-items-center gap-1 small"><span class="badge-legende conducteur"></span> {{ t('places_conducteur') }}</span>
            </div>

            <!-- Plan visuel du véhicule -->
            <div class="plan-vehicule-visuel">
              <!-- Rangées de sièges selon le type de véhicule (la 1ère rangée inclut le conducteur et les places avant) -->
              <template v-for="(rangee, ri) in getDispositionSieges(trajet.vehicule?.type, trajet.vehicule?.nombre_places)" :key="ri">
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
                        'siege-selectionne': placesSelectionnees.includes(cellule),
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
                <span class="text-muted">{{ t('places_sieges_choisis') }} </span>
                <strong class="text-primary fs-6">
                  {{ placesSelectionnees.length > 0 ? placesSelectionnees.map(n => 'N° ' + n).join(', ') : t('places_aucun_choisi') }}
                </strong>
              </div>
              <div class="small text-muted">
                {{ t('places_capacite') }} <strong>{{ trajet.vehicule?.nombre_places || 8 }} {{ t('places_places_texte') }}</strong>
              </div>
            </div>
          </div>

          <!-- 2. FORMULE DE PAIEMENT (TOTAL OU 50% AVANCE) -->
          <div class="carte-recherche mb-4">
            <h3 class="titre-page fs-5 mb-2">{{ t('places_formule_titre') }}</h3>
            <p class="paragraphe-guide small mb-3">
              {{ t('places_formule_desc') }}
            </p>

            <div class="row g-3 mb-4">
              <!-- Option 1 : Payer Total -->
              <div class="col-md-6">
                <div 
                  class="p-3 border rounded-3 h-100" 
                  :class="typePaiementChoisi === 'total' ? 'border-primary bg-primary bg-opacity-10' : 'bg-white'"
                  style="cursor: pointer;"
                  @click="typePaiementChoisi = 'total'"
                >
                  <div class="form-check">
                    <input 
                      class="form-check-input" 
                      type="radio" 
                      name="formulePaiement" 
                      id="optTotal" 
                      value="total" 
                      v-model="typePaiementChoisi" 
                    />
                    <label class="form-check-label fw-bold text-dark fs-6" for="optTotal">
                      {{ t('places_payer_total') }}
                    </label>
                  </div>
                  <p class="small text-muted mb-0 mt-2">
                    {{ t('places_payer_total_desc') }} ({{ montantTotalCalcul.toLocaleString() }} Ar)
                  </p>
                </div>
              </div>

              <!-- Option 2 : Avance 50% -->
              <div class="col-md-6">
                <div 
                  class="p-3 border rounded-3 h-100" 
                  :class="typePaiementChoisi === 'avance' ? 'border-warning bg-warning bg-opacity-10' : 'bg-white'"
                  style="cursor: pointer;"
                  @click="typePaiementChoisi = 'avance'"
                >
                  <div class="form-check">
                    <input 
                      class="form-check-input" 
                      type="radio" 
                      name="formulePaiement" 
                      id="optAvance" 
                      value="avance" 
                      v-model="typePaiementChoisi" 
                    />
                    <label class="form-check-label fw-bold text-dark fs-6" for="optAvance">
                      {{ t('places_payer_avance') }}
                    </label>
                  </div>
                  <p class="small text-muted mb-0 mt-2">
                    Payez <strong>{{ Math.round(montantTotalCalcul / 2).toLocaleString() }} Ar</strong> maintenant. {{ t('places_reste_especes_hint') }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Opérateurs de Mobile Money -->
            <h4 class="titre-page fs-6 mb-2">{{ t('places_choix_operateur') }}</h4>
            <div class="grille-cartes-paiement mb-3">
              <div 
                class="carte-option-paiement carte-logo-operateur fond-mvola" 
                :class="{ 'active': modePaiement === 'MVola' }"
                @click="selectionnerPaiement('MVola')"
              >
                <img src="/images/mvola.jpg" alt="MVola" class="logo-operateur" />
              </div>

              <div 
                class="carte-option-paiement carte-logo-operateur fond-orange" 
                :class="{ 'active': modePaiement === 'Orange Money' }"
                @click="selectionnerPaiement('Orange Money')"
              >
                <img src="/images/ORANGE MONEY.jpg" alt="Orange Money" class="logo-operateur" />
              </div>

              <div 
                class="carte-option-paiement carte-logo-operateur fond-airtel" 
                :class="{ 'active': modePaiement === 'Airtel Money' }"
                @click="selectionnerPaiement('Airtel Money')"
              >
                <img src="/images/airtel money.jpg" alt="Airtel Money" class="logo-operateur" />
              </div>
            </div>

            <div class="p-3 bg-light rounded-3">
              <label for="numero-telephone-paiement" class="form-label small fw-bold text-dark">
                {{ t('places_num_tel') }} ({{ modePaiement }}) :
              </label>
              <input 
                id="numero-telephone-paiement"
                name="numero-telephone-paiement"
                v-model="numeroTelephone" 
                type="tel" 
                class="form-control" 
                :placeholder="t('places_num_placeholder')"
                autocomplete="tel"
                required 
              />
              <span class="small text-muted mt-1 d-block">
                {{ t('places_num_tel_hint') }}
              </span>
            </div>
          </div>
        </div>

        <!-- COLONNE DROITE : Récapitulatif et Validation -->
        <div class="col-lg-4">
          <div class="carte-recherche position-sticky" style="top: 20px;">
            <h4 class="titre-page fs-5 mb-3 border-bottom pb-2">{{ t('places_recap_titre') }}</h4>

            <div class="mb-2">
              <div class="text-secondary small">{{ t('places_trajet') }}</div>
              <div class="fw-bold">{{ trajet.ville_depart }} &rarr; {{ trajet.ville_arrivee }}</div>
            </div>

            <div class="mb-2">
              <div class="text-secondary small">{{ t('places_date_dep') }}</div>
              <div class="fw-bold">{{ trajet.date }} à {{ trajet.horaire }}</div>
            </div>

            <div class="mb-2">
              <div class="text-secondary small">{{ t('places_vehicule_type') }}</div>
              <div class="fw-bold">{{ trajet.vehicule?.type }} ({{ trajet.vehicule?.matricule }})</div>
            </div>

            <div class="mb-3">
              <div class="text-secondary small">{{ t('places_places_res') }}</div>
              <div class="fw-bold text-primary">
                {{ placesSelectionnees.length }} {{ t('places_selectionnees') }}
                <span v-if="placesSelectionnees.length > 0">
                  ({{ placesSelectionnees.map(n => 'N° ' + n).join(', ') }})
                </span>
              </div>
            </div>

            <hr />

            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">{{ t('places_prix_unit') }}</span>
              <span class="fw-bold">{{ tarifUnitaire.toLocaleString() }} Ar</span>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">{{ t('places_total') }}</span>
              <span class="fw-bold">{{ montantTotalCalcul.toLocaleString() }} Ar</span>
            </div>

            <!-- Détail selon formule de paiement -->
            <div class="p-2 rounded mb-3" :class="typePaiementChoisi === 'avance' ? 'bg-warning bg-opacity-25' : 'bg-light'">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">{{ t('places_a_regler') }}</span>
                <span class="fs-5 fw-bold text-success">{{ montantAPayerMaintenant.toLocaleString() }} Ar</span>
              </div>
              <div v-if="typePaiementChoisi === 'avance'" class="d-flex justify-content-between align-items-center mt-1 text-danger small">
                <span>{{ t('places_reste_checkpoint') }}</span>
                <strong>{{ soldeRestantCheckpoint.toLocaleString() }} Ar</strong>
              </div>
            </div>

            <div v-if="messageErreur" class="alert alert-danger py-2 mb-3">{{ messageErreur }}</div>
            <div v-if="messageSucces" class="alert alert-success py-2 mb-3">{{ messageSucces }}</div>

            <!-- Indicateur voyageur connecté ou visiteur -->
            <div v-if="auth.estConnecte" class="p-2 rounded bg-light border mb-3 small d-flex justify-content-between align-items-center">
              <div>
                <span class="text-muted d-block" style="font-size: 0.75rem;">{{ t('places_voyageur_connecte') }}</span>
                <strong class="text-primary">{{ auth.utilisateur?.email || 'Mon compte' }}</strong>
              </div>
              <button 
                type="button" 
                @click="auth.deconnexion()" 
                class="btn btn-sm btn-outline-secondary py-1 px-2"
                title="Se déconnecter pour changer de compte ou réserver pour un autre voyageur"
              >
                {{ t('places_changer_compte') }}
              </button>
            </div>
            <div v-else class="alert alert-info py-2 small mb-3">
              <img src="/icons/message/megaphone.png" alt="Information" class="icone-action" /> {{ t('places_connexion_requise') }}
            </div>

            <div v-if="trajetEstPasse" class="alert alert-warning py-2 small mb-3 fw-bold text-center">
               {{ t('places_trajet_passe') }}
            </div>

            <button 
              @click="confirmerReservation" 
              class="btn btn-primary w-100 py-3 fw-bold fs-6 shadow-sm"
              :disabled="enEnregistrement || placesSelectionnees.length === 0 || trajetEstPasse"
            >
              <span v-if="enEnregistrement" class="spinner-border spinner-border-sm me-2"></span>
              <span>
                {{ trajetEstPasse ? t('places_depart_effectue') : (auth.estConnecte ? `${t('places_confirmer_payer')} ${montantAPayerMaintenant.toLocaleString()} Ar` : t('places_continuer_paiement')) }}
              </span>
            </button>

            <div class="text-center text-muted small mt-3">
              <img src="/icons/autres/document.png" alt="Paiement sécurisé" class="icone-action" /> {{ t('places_paiement_securise') }}
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Pied de page commun -->
    <Footer />

    <!-- ChatBot 24h/24 -->
    <ChatBot />
  </div>
</template>

<style scoped>
@import "@/assets/css/client/base.css";
@import "@/assets/css/client/reservation.css";
@import "@/assets/css/client/places.css";
</style>
