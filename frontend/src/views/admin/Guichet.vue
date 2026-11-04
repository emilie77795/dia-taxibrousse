<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import Layout from '@/components/admin/Layout.vue'
import api from '@/api'
import QRCode from 'qrcode'

// Données des trajets et filtres
const tousTrajets = ref([])
const chargement = ref(true)
const filtreDate = ref('')
const filtreTypeVehicule = ref('')
const trajetSelectionneId = ref('')
const placesSelectionnees = ref([])

// Formulaire client & encaissement
const nomClient = ref('')
const telephoneClient = ref('')
const enValidation = ref(false)
const messageErreur = ref('')
const messageSucces = ref('')

// Modale Ticket et QR Code
const modalTicketOuvert = ref(false)
const reservationConfirmee = ref(null)
const qrCanvas = ref(null)

// Vérifie si un trajet n'est pas encore passé par rapport à la date et heure actuelles
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

  // Si le départ est aujourd'hui, vérifier que l'horaire n'est pas encore dépassé
  if (!t.horaire) return true
  const heures = String(maintenant.getHours()).padStart(2, '0')
  const minutes = String(maintenant.getMinutes()).padStart(2, '0')
  const heureActuelle = `${heures}:${minutes}`
  const horaireTrajet = String(t.horaire).slice(0, 5)

  return horaireTrajet > heureActuelle
}

// Charger tous les trajets depuis l'API
async function chargerTrajets() {
  chargement.value = true
  messageErreur.value = ''
  try {
    const { data } = await api.get('/api/trajets')
    tousTrajets.value = data || []

    // Présélection automatique du premier départ disponible non passé
    if (!trajetSelectionneId.value && trajetsFiltres.value.length > 0) {
      trajetSelectionneId.value = String(trajetsFiltres.value[0].id)
    }
  } catch (err) {
    console.error('Erreur chargement des trajets', err)
    messageErreur.value = 'Impossible de charger la liste des trajets.'
  } finally {
    chargement.value = false
  }
}

onMounted(() => {
  chargerTrajets()
})

// Liste unique des types de véhicules disponibles pour le filtre
const typesVehiculesDisponibles = computed(() => {
  const types = new Set()
  tousTrajets.value.forEach(t => {
    const nom = t.vehicule?.type
    if (nom) types.add(nom)
  })
  return Array.from(types).sort()
})

// Filtrage strict : UNIQUEMENT les trajets non encore passés à la date actuelle
const trajetsFiltres = computed(() => {
  let list = tousTrajets.value || []

  // Règle stricte : Ne garder QUE les trajets à venir (non dépassés)
  list = list.filter(t => estTrajetFutur(t))

  // Filtre par type de véhicule
  if (filtreTypeVehicule.value) {
    list = list.filter(t => t.vehicule?.type === filtreTypeVehicule.value)
  }

  // Filtre par date sélectionnée
  if (filtreDate.value) {
    list = list.filter(t => t.date === filtreDate.value)
  }

  return list
})

// Synchroniser la sélection si le filtre change
watch(trajetsFiltres, (nouvelleListe) => {
  if (nouvelleListe.length === 0) {
    trajetSelectionneId.value = ''
    placesSelectionnees.value = []
  } else if (!nouvelleListe.some(t => String(t.id) === String(trajetSelectionneId.value))) {
    trajetSelectionneId.value = String(nouvelleListe[0].id)
    placesSelectionnees.value = []
  }
})

// Trajet actuellement sélectionné
const trajetActif = computed(() => {
  if (!trajetSelectionneId.value) return null
  return tousTrajets.value.find(t => String(t.id) === String(trajetSelectionneId.value)) || null
})

// Réinitialiser les places si le départ change
watch(trajetSelectionneId, () => {
  placesSelectionnees.value = []
  messageErreur.value = ''
})

// Gestion des places
function estPlaceOccupee(num) {
  const occupees = trajetActif.value?.numeros_places_occupees || []
  return occupees.includes(num)
}

function togglePlace(num) {
  if (estPlaceOccupee(num)) return
  const index = placesSelectionnees.value.indexOf(num)
  if (index > -1) {
    placesSelectionnees.value.splice(index, 1)
  } else {
    placesSelectionnees.value.push(num)
    placesSelectionnees.value.sort((a, b) => a - b)
  }
}

// Calcul financier
const prixUnitaire = computed(() => {
  return trajetActif.value?.prix_valeur || 20000
})

const montantTotal = computed(() => {
  return placesSelectionnees.value.length * prixUnitaire.value
})

// Disposition visuelle des sièges selon le type de minibus / car
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

// Validation de la réservation au comptoir (guichet)
async function validerReservationGuichet() {
  messageErreur.value = ''
  messageSucces.value = ''

  if (!trajetActif.value) {
    messageErreur.value = 'Veuillez sélectionner un trajet.'
    return
  }

  if (placesSelectionnees.value.length === 0) {
    messageErreur.value = 'Veuillez sélectionner au moins une place libre sur le plan.'
    return
  }

  if (!nomClient.value.trim()) {
    messageErreur.value = 'Veuillez saisir le nom du voyageur.'
    return
  }

  if (!telephoneClient.value.trim()) {
    messageErreur.value = 'Veuillez saisir le numéro de téléphone du client.'
    return
  }

  enValidation.value = true
  try {
    const { data } = await api.post('/api/admin/guichet', {
      id_trajet: trajetActif.value.id,
      places: placesSelectionnees.value,
      nom_client: nomClient.value.trim(),
      telephone_client: telephoneClient.value.trim()
    })

    reservationConfirmee.value = data
    modalTicketOuvert.value = true
    messageSucces.value = 'Réservation confirmée avec succès !'

    // Actualiser les places en temps réel
    await chargerTrajets()
  } catch (err) {
    messageErreur.value = err.response?.data?.error || 'Erreur lors de l\'enregistrement de la réservation au guichet.'
    await chargerTrajets()
  } finally {
    enValidation.value = false
  }
}

// Génération du QR Code
async function genererQRCode() {
  if (!qrCanvas.value || !reservationConfirmee.value?.ticket) return
  try {
    await QRCode.toCanvas(qrCanvas.value, reservationConfirmee.value.ticket, {
      width: 140,
      margin: 1,
      color: {
        dark: '#13385C',
        light: '#ffffff'
      },
      errorCorrectionLevel: 'M'
    })
  } catch (err) {
    console.error('Erreur génération QR Code', err)
  }
}

watch([reservationConfirmee, qrCanvas], async ([res, canvas]) => {
  if (res && canvas) {
    await nextTick()
    genererQRCode()
  }
})

function imprimerTicket() {
  window.print()
}

function nouvelleReservation() {
  modalTicketOuvert.value = false
  reservationConfirmee.value = null
  placesSelectionnees.value = []
  nomClient.value = ''
  telephoneClient.value = ''
  messageErreur.value = ''
  messageSucces.value = ''
}

function formaterDate(dateTexte) {
  if (!dateTexte) return '—'
  return new Date(dateTexte).toLocaleDateString('fr-FR', {
    weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'
  })
}

function formaterAriary(montant) {
  return Number(montant || 0).toLocaleString('fr-FR') + ' Ar'
}
</script>

<template>
  <Layout title="Réservation au Guichet (Comptoir)">
    <div class="container-fluid px-0">
      <!-- En-tête et descriptif -->
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
          <h5 class="fw-bold mb-1" style="color: #13385C;">Comptoir d'Enregistrement</h5>
          <p class="text-muted small mb-0">
            Vente directe de billets • Départs à venir uniquement • Paiement immédiat en espèces • Ticket QR imprimable.
          </p>
        </div>

        <button @click="chargerTrajets" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2 rounded-pill px-3">
          <img src="/icons/autres/loading.png" alt="" style="width: 14px; height: 14px;" />
          <span>Actualiser les départs</span>
        </button>
      </div>

      <!-- Messages d'erreur ou de succès -->
      <div v-if="messageErreur" class="alert alert-danger py-2 mb-3 rounded-3 d-flex align-items-center justify-content-between">
        <span><strong>Attention :</strong> {{ messageErreur }}</span>
        <button type="button" class="btn-close btn-sm" @click="messageErreur = ''"></button>
      </div>

      <div v-if="messageSucces" class="alert alert-success py-2 mb-3 rounded-3 d-flex align-items-center justify-content-between">
        <span><strong>Succès :</strong> {{ messageSucces }}</span>
        <button type="button" class="btn-close btn-sm" @click="messageSucces = ''"></button>
      </div>

      <!-- Chargement initial -->
      <div v-if="chargement" class="text-center py-5 text-muted">
        <div class="spinner-border text-primary mb-2" role="status"></div>
        <div>Chargement des départs disponibles...</div>
      </div>

      <div v-else class="row g-4">
        <!-- COLONNE GAUCHE : Étape 1 & Étape 2 -->
        <div class="col-lg-7">
          <!-- ÉTAPE 1 : CHOIX DU TRAJET & FILTRES -->
          <div class="carte-guichet">
            <div class="carte-guichet-titre">
              <div class="d-flex align-items-center gap-2">
                <span class="numero-etape">1</span>
                <span>Choisir le Trajet & Date de Départ</span>
              </div>
              <span class="badge bg-primary rounded-pill px-3 py-1 font-monospace" style="font-size: 0.78rem;">
                {{ trajetsFiltres.length }} départ(s) à venir
              </span>
            </div>

            <!-- Barre de filtres : Type de véhicule + Date -->
            <div class="barre-filtres-guichet">
              <div class="row g-2 align-items-center">
                <!-- Filtre Type de véhicule -->
                <div class="col-md-6">
                  <label class="form-label small fw-bold text-muted mb-1">Filtrer par type de véhicule</label>
                  <select v-model="filtreTypeVehicule" class="form-select form-select-sm">
                    <option value="">Tous les types de véhicules</option>
                    <option v-for="type in typesVehiculesDisponibles" :key="type" :value="type">
                      {{ type }}
                    </option>
                  </select>
                </div>

                <!-- Filtre Date précise -->
                <div class="col-md-6">
                  <label class="form-label small fw-bold text-muted mb-1">Filtrer par date de départ</label>
                  <div class="input-group input-group-sm">
                    <input 
                      v-model="filtreDate" 
                      type="date" 
                      class="form-control"
                    />
                    <button 
                      v-if="filtreDate" 
                      @click="filtreDate = ''" 
                      class="btn btn-outline-secondary" 
                      type="button"
                      title="Effacer la date"
                    >×</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Sélecteur liste déroulante : Affiche UNIQUEMENT les départs non encore passés -->
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Sélectionner le départ prévu *</label>
              <select 
                v-model="trajetSelectionneId" 
                class="form-select form-select-lg fw-semibold"
                style="color: #13385C; border-color: #CBD5E1;"
              >
                <option value="" disabled>-- Sélectionnez un départ programmé --</option>
                <option 
                  v-for="t in trajetsFiltres" 
                  :key="t.id" 
                  :value="String(t.id)"
                >
                  {{ t.ville_depart }} ➔ {{ t.ville_arrivee }} | {{ formaterDate(t.date) }} à {{ t.horaire }} ({{ t.vehicule?.type || 'Minibus' }} - {{ t.places_disponibles }} pl. libres) - {{ t.prix }}
                </option>
              </select>
              <div v-if="trajetsFiltres.length === 0" class="text-danger small mt-2">
                Aucun départ à venir ne correspond aux critères sélectionnés.
              </div>
            </div>

            <!-- Détail du départ actif -->
            <div v-if="trajetActif" class="p-3 bg-light rounded-3 border">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                  <h6 class="fw-bold mb-1" style="color: #13385C;">
                    {{ trajetActif.ville_depart }} ➔ {{ trajetActif.ville_arrivee }}
                  </h6>
                  <div class="small text-muted">
                    <span>Date : <strong>{{ formaterDate(trajetActif.date) }} à {{ trajetActif.horaire }}</strong></span>
                    <span class="mx-2">•</span>
                    <span>Véhicule : <strong>{{ trajetActif.vehicule?.type }} ({{ trajetActif.vehicule?.matricule }})</strong></span>
                  </div>
                </div>
                <div class="text-end">
                  <span class="badge-places-dispo" :class="{ 'faible': trajetActif.places_disponibles <= 3, 'complet': trajetActif.places_disponibles === 0 }">
                    {{ trajetActif.places_disponibles }} place(s) libre(s)
                  </span>
                  <div class="fw-bold text-primary mt-1">{{ trajetActif.prix }} / place</div>
                </div>
              </div>
            </div>
          </div>

          <!-- ÉTAPE 2 : PLAN DES PLACES EN TEMPS RÉEL -->
          <div v-if="trajetActif" class="carte-guichet">
            <div class="carte-guichet-titre d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-2">
                <span class="numero-etape">2</span>
                <span>Plan des Places en Temps Réel</span>
              </div>
              <span class="badge bg-primary rounded-pill px-3 py-2">
                {{ placesSelectionnees.length }} place(s) sélectionnée(s)
              </span>
            </div>

            <p class="text-muted small mb-3">
              Cliquez sur les sièges libres pour les attribuer au client. Les sièges barrés sont déjà réservés.
            </p>

            <!-- Légende -->
            <div class="d-flex gap-3 mb-3 justify-content-center flex-wrap small">
              <span><span class="legende-pastille libre"></span> Libre</span>
              <span><span class="legende-pastille selectionne"></span> Sélectionné</span>
              <span><span class="legende-pastille occupe"></span> Déjà réservé</span>
              <span><span class="legende-pastille conducteur"></span> Conducteur</span>
            </div>

            <!-- Grille du véhicule -->
            <div class="plan-vehicule-cadre shadow-sm">
              <div class="text-center small text-muted mb-2 fw-semibold text-uppercase" style="letter-spacing: 1px;">
                ▲ Avant du véhicule ▲
              </div>

              <template v-for="(rangee, ri) in getDispositionSieges(trajetActif.vehicule?.type, trajetActif.vehicule?.nombre_places)" :key="ri">
                <div class="plan-rangee">
                  <template v-for="(cellule, ci) in rangee" :key="ci">
                    <div v-if="cellule === 'couloir'" class="plan-couloir"></div>
                    <div v-else-if="cellule === 'vide'" class="plan-siege invisible"></div>
                    <div v-else-if="cellule === 'conducteur'" class="plan-siege conducteur-siege" title="Chauffeur">C</div>
                    <div 
                      v-else 
                      class="plan-siege"
                      :class="{
                        'siege-selectionne': placesSelectionnees.includes(cellule),
                        'siege-occupe': estPlaceOccupee(cellule)
                      }"
                      @click="togglePlace(cellule)"
                    >
                      {{ cellule }}
                    </div>
                  </template>
                </div>
              </template>

              <div class="text-center small text-muted mt-2 fw-semibold text-uppercase" style="letter-spacing: 1px;">
                ▼ Arrière du véhicule ▼
              </div>
            </div>

            <!-- Récap places choisies -->
            <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div class="small">
                <span class="text-muted">Sièges choisis : </span>
                <strong class="text-primary fs-6">
                  {{ placesSelectionnees.length > 0 ? placesSelectionnees.map(n => 'N° ' + n).join(', ') : 'Aucune place' }}
                </strong>
              </div>
              <button 
                v-if="placesSelectionnees.length > 0" 
                @click="placesSelectionnees = []" 
                class="btn btn-sm btn-link text-danger text-decoration-none p-0"
              >
                Désélectionner tout
              </button>
            </div>
          </div>
        </div>

        <!-- COLONNE DROITE : Étape 3 - CLIENT & PAIEMENT ESPÈCES -->
        <div class="col-lg-5">
          <div class="carte-guichet">
            <div class="carte-guichet-titre">
              <div class="d-flex align-items-center gap-2">
                <span class="numero-etape">3</span>
                <span>Voyageur & Paiement Espèces</span>
              </div>
            </div>

            <!-- Formulaire voyageur -->
            <div class="mb-3">
              <label class="form-label small fw-bold">Nom complet du voyageur *</label>
              <input 
                v-model="nomClient" 
                type="text" 
                class="form-control" 
                placeholder="Ex : Jean Rasoanaivo"
                required
              />
              <div class="form-text small">Inscrit sur le ticket physique ou numérique.</div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold">Numéro de téléphone *</label>
              <input 
                v-model="telephoneClient" 
                type="tel" 
                class="form-control" 
                placeholder="Ex : 034 00 123 45"
                required
              />
              <div class="form-text small">Utile pour retrouver le ticket ou en cas d'imprévu.</div>
            </div>

            <!-- Mode de règlement : Espèces au comptoir -->
            <div class="mb-3 p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #E2E8F0;">
              <label class="form-label small fw-bold mb-2 d-block text-secondary">
                Mode d'Encaissement
              </label>
              <div class="form-check">
                <input class="form-check-input" type="radio" checked disabled id="paiementEspeces" />
                <label class="form-check-label fw-bold text-dark" for="paiementEspeces">
                  Espèces au Guichet (Comptoir)
                </label>
              </div>
              <div class="small text-muted mt-1">
                Le montant intégral est immédiatement validé et intégré aux statistiques et au chiffre d'affaires de la coopérative.
              </div>
            </div>

            <!-- Récapitulatif Total -->
            <div class="recap-total-box mb-4">
              <div class="ligne-recap">
                <span>Nombre de places</span>
                <span class="valeur-recap">{{ placesSelectionnees.length }}</span>
              </div>
              <div class="ligne-recap">
                <span>Prix unitaire</span>
                <span class="valeur-recap">{{ formaterAriary(prixUnitaire) }}</span>
              </div>
              <div class="separateur-recap"></div>
              <div class="d-flex justify-content-between align-items-baseline">
                <span class="fw-bold text-dark">Total à encaisser :</span>
                <span class="montant-grand">{{ formaterAriary(montantTotal) }}</span>
              </div>
            </div>

            <!-- Bouton de validation -->
            <button 
              @click="validerReservationGuichet" 
              :disabled="enValidation || placesSelectionnees.length === 0 || !nomClient.trim() || !telephoneClient.trim()"
              class="btn-valider-guichet"
            >
              <span v-if="enValidation" class="spinner-border spinner-border-sm me-2" role="status"></span>
              <span>{{ enValidation ? 'Validation en cours...' : 'Encaisser & Confirmer la Réservation' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODALE TICKET IMPRIMABLE AVEC QR CODE -->
    <div v-if="modalTicketOuvert && reservationConfirmee" class="rideau-modal-ticket" @click.self="modalTicketOuvert = false">
      <div class="boite-modal-ticket">
        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center d-print-none">
          <h5 class="fw-bold mb-0 text-success d-flex align-items-center gap-2">
            <span>✓ Billet Validé avec Succès</span>
          </h5>
          <button @click="modalTicketOuvert = false" class="btn-close" type="button"></button>
        </div>

        <!-- BILLET IMPRIMABLE OFFICIEL -->
        <div class="billet-guichet-imprimable">
          <div class="billet-entete">
            <div class="d-flex align-items-center gap-2">
              <img src="/logo-dia.png" alt="DIA Logo" style="height: 32px; width: auto; filter: brightness(0) invert(1);" />
              <div>
                <strong class="d-block" style="font-size: 0.95rem; letter-spacing: 1px;">DIA TAXI-BROUSSE</strong>
                <span class="small opacity-75" style="font-size: 0.7rem;">BILLET OFFICIEL DE GUICHET</span>
              </div>
            </div>
            <div class="text-end">
              <span class="badge bg-warning text-dark fw-bold font-monospace fs-6">
                {{ reservationConfirmee.ticket }}
              </span>
            </div>
          </div>

          <div class="billet-corps">
            <!-- Trajet & Horaires -->
            <div class="row g-2 mb-3 align-items-center text-center">
              <div class="col-5 text-start">
                <span class="text-muted small d-block">DÉPART</span>
                <strong class="fs-5" style="color: #13385C;">{{ reservationConfirmee.ville_depart }}</strong>
                <div class="small text-muted">{{ reservationConfirmee.horaire }}</div>
              </div>
              <div class="col-2 text-center text-muted">➔</div>
              <div class="col-5 text-end">
                <span class="text-muted small d-block">ARRIVÉE</span>
                <strong class="fs-5" style="color: #13385C;">{{ reservationConfirmee.ville_arrivee }}</strong>
                <div class="small text-muted">{{ formaterDate(reservationConfirmee.date) }}</div>
              </div>
            </div>

            <div class="billet-separateur"></div>

            <!-- Détails voyageur & véhicule -->
            <div class="row g-2 mb-3">
              <div class="col-6">
                <div class="text-muted small">VOYAGEUR</div>
                <strong class="d-block text-dark">{{ reservationConfirmee.nom_client }}</strong>
                <div class="small text-muted">{{ reservationConfirmee.telephone_client }}</div>
              </div>
              <div class="col-6 text-end">
                <div class="text-muted small">VÉHICULE & PLACES</div>
                <strong class="d-block text-primary">
                  Places : {{ reservationConfirmee.places?.map(p => 'N°' + p).join(', ') }}
                </strong>
                <div class="small text-muted">{{ reservationConfirmee.type_vehicule }} ({{ reservationConfirmee.matricule }})</div>
              </div>
            </div>

            <!-- QR Code & Paiement -->
            <div class="p-3 rounded-3 bg-light d-flex align-items-center justify-content-between gap-3">
              <div>
                <span class="badge bg-success mb-1">Payé en Espèces (100%)</span>
                <div class="fs-5 fw-bold" style="color: #13385C;">{{ reservationConfirmee.montant_total_texte }}</div>
                <div class="small text-muted" style="font-size: 0.72rem;">Émis le {{ reservationConfirmee.date_reservation }}</div>
              </div>

              <!-- Zone QR Code -->
              <div class="text-center">
                <canvas ref="qrCanvas" style="width: 100px; height: 100px; border-radius: 8px;"></canvas>
                <div class="small font-monospace text-muted mt-1" style="font-size: 0.7rem;">{{ reservationConfirmee.ticket }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Boutons d'action dans la modale -->
        <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center gap-2 flex-wrap d-print-none">
          <button @click="nouvelleReservation" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            + Nouvelle Réservation Guichet
          </button>

          <div class="d-flex gap-2">
            <router-link to="/admin/reservations" class="btn btn-outline-primary btn-sm rounded-pill px-3">
              Voir la Liste des Réservations
            </router-link>
            <button @click="imprimerTicket" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold d-inline-flex align-items-center gap-1" style="background-color: #13385C; border-color: #13385C;">
              <img src="/icons/autres/download.png" alt="" class="icone-sur-fond-sombre" style="width: 14px; height: 14px;" />
              <span>Imprimer le Ticket</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
@import "@/assets/css/admin/guichet.css";
</style>
