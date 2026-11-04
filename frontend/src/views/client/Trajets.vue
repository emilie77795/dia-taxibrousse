<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Navbar from '@/components/client/Navbar.vue'
import Footer from '@/components/client/Footer.vue'
import ChatBot from '@/components/client/ChatBot.vue'
import { useTrajetsStore } from '@/stores/trajets'
import { useThemeStore } from '@/stores/theme'
import { t, langueCourante } from '@/i18n'
import api from '@/api'

const route = useRoute()
const routeur = useRouter()
const storeTrajets = useTrajetsStore()
const themeStore = useThemeStore()
const modeSombre = computed(() => themeStore.modeSombre)

// Filtres de recherche
const villeDepart = ref('')
const villeArrivee = ref('')
const dateVoyage = ref('')
const filtreVehicule = ref('')
const listeTypesVehicules = ref([])

function appliquerFiltresDepuisUrl(query) {
  if (query.depart !== undefined) {
    villeDepart.value = String(query.depart || '').trim()
  }
  if (query.arrivee !== undefined) {
    villeArrivee.value = String(query.arrivee || '').trim()
  }
  if (query.date !== undefined) {
    dateVoyage.value = String(query.date || '').trim()
  }
  if (query.type !== undefined || query.vehicule !== undefined) {
    const val = String(query.type || query.vehicule || '').trim()
    if (!val) {
      filtreVehicule.value = ''
    } else {
      const valMin = val.toLowerCase()
      const trouve = listeTypesVehicules.value.find(t => 
        t.nom.toLowerCase() === valMin || 
        t.nom.toLowerCase().includes(valMin) || 
        valMin.includes(t.nom.toLowerCase())
      )
      filtreVehicule.value = trouve ? trouve.nom : val
    }
  }
}

function reinitialiserFiltres() {
  villeDepart.value = ''
  villeArrivee.value = ''
  dateVoyage.value = ''
  filtreVehicule.value = ''
  routeur.replace({ path: '/client/trajets' })
}

async function chargerTypesVehicules() {
  try {
    const rep = await api.get('/api/types-vehicules')
    listeTypesVehicules.value = rep.data
    // Réappliquer le filtre véhicule si présent dans l'URL pour assurer la correspondance avec le select
    if (route.query.type || route.query.vehicule) {
      appliquerFiltresDepuisUrl(route.query)
    }
  } catch (erreur) {
    console.error('Erreur chargement types de véhicules', erreur)
  }
}

onMounted(async () => {
  storeTrajets.chargerTrajets()
  await chargerTypesVehicules()
  appliquerFiltresDepuisUrl(route.query)
})

watch(() => route.query, (nouveauQuery) => {
  appliquerFiltresDepuisUrl(nouveauQuery)
}, { deep: true })

// Favoris locaux pour confort visuel
const favoris = ref(new Set())
function toggleFavori(id) {
  if (favoris.value.has(id)) {
    favoris.value.delete(id)
  } else {
    favoris.value.add(id)
  }
}

function dateLocaleDuJour() {
  const maintenant = new Date()
  const annee = maintenant.getFullYear()
  const mois = String(maintenant.getMonth() + 1).padStart(2, '0')
  const jour = String(maintenant.getDate()).padStart(2, '0')
  return `${annee}-${mois}-${jour}`
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

  // Si c'est aujourd'hui, vérifier si l'horaire de départ n'est pas encore dépassé
  if (!t.horaire) return true
  const heures = String(maintenant.getHours()).padStart(2, '0')
  const minutes = String(maintenant.getMinutes()).padStart(2, '0')
  const heureActuelle = `${heures}:${minutes}`
  const horaireTrajet = String(t.horaire).slice(0, 5)

  return horaireTrajet > heureActuelle
}

// ---------------- RÈGLE 1 : AFFICHAGE PROGRESSIF (50% DE REMPLISSAGE) ----------------
// Pour un même créneau (même départ, même arrivée, même date, même heure) :
// La 2ème voiture n'apparaît que si la 1ère est remplie à >= 50%, la 3ème si la 2ème est à >= 50%, etc.
const trajetsFiltres = computed(() => {
  // 1. Filtrage standard par saisie et vérification que le trajet n'est pas dépassé
  const listeFiltree = storeTrajets.trajets.filter(t => {
    const matchDepart = !villeDepart.value || t.ville_depart.toLowerCase().includes(villeDepart.value.toLowerCase())
    const matchArrivee = !villeArrivee.value || t.ville_arrivee.toLowerCase().includes(villeArrivee.value.toLowerCase())
    const dateTrajet = t.date ? String(t.date).slice(0, 10) : ''
    const matchDisponible = estTrajetFutur(t)
    const matchDate = matchDisponible && (!dateVoyage.value || dateTrajet === dateVoyage.value)
    const typeNom = (t.vehicule?.type || '').toLowerCase()
    const filtreMin = (filtreVehicule.value || '').toLowerCase()
    const matchVehicule = !filtreMin || typeNom === filtreMin || typeNom.includes(filtreMin) || filtreMin.includes(typeNom)
    return matchDepart && matchArrivee && matchDate && matchVehicule
  })

  // 2. Regroupement par créneau (depart + arrivee + date + horaire) — SANS le type
  // Chaque type de véhicule différent dans un même créneau est traité comme un sous-groupe
  const groupesCreneaux = {}
  listeFiltree.forEach(trajet => {
    // Clé de créneau sans type : on regroupe tous les véhicules du même trajet ensemble
    const cleCreneau = `${trajet.ville_depart}_${trajet.ville_arrivee}_${trajet.date}_${trajet.horaire}`
    if (!groupesCreneaux[cleCreneau]) {
      groupesCreneaux[cleCreneau] = {}
    }
    // Sous-groupe par type pour que chaque type suive sa propre règle des 50%
    const typeVehicule = trajet.vehicule?.type || 'autre'
    if (!groupesCreneaux[cleCreneau][typeVehicule]) {
      groupesCreneaux[cleCreneau][typeVehicule] = []
    }
    groupesCreneaux[cleCreneau][typeVehicule].push(trajet)
  })

  // 3. Application de la règle des 50% : par type dans chaque créneau
  // Le 1er véhicule de chaque type apparaît toujours ; les suivants seulement si le précédent >= 50%
  const trajetsVisibles = []
  Object.values(groupesCreneaux).forEach(groupeParType => {
    Object.values(groupeParType).forEach(listeVoituresDuType => {
      for (let i = 0; i < listeVoituresDuType.length; i++) {
        const voitureCourante = listeVoituresDuType[i]
        if (i === 0) {
          // Le premier véhicule de chaque type sur le créneau apparaît toujours
          trajetsVisibles.push(voitureCourante)
        } else {
          // Les suivants apparaissent seulement si le précédent est rempli à >= 50%
          const voiturePrecedente = listeVoituresDuType[i - 1]
          const tauxPrec = voiturePrecedente.taux_remplissage ?? ((voiturePrecedente.places_occupees || 0) / (voiturePrecedente.places_totales || 8) * 100)
          if (tauxPrec >= 50) {
            trajetsVisibles.push(voitureCourante)
          }
        }
      }
    })
  })

  return trajetsVisibles
})

function getImageVehicule(trajet) {
  const type = (trajet.vehicule?.type || '').toLowerCase()
  if (type.includes('sprinter')) return '/images/sprinter1.png'
  if (type.includes('sylver') || type.includes('silver')) return '/images/sylver_class2.png'
  if (trajet.vehicule?.image) {
    return trajet.vehicule.image.startsWith('http') 
      ? trajet.vehicule.image 
      : 'http://localhost:8000' + trajet.vehicule.image
  }
  return '/images/rn2.jpg'
}

function formaterJourHoraire(dateTexte, horaireTexte) {
  if (!dateTexte) return horaireTexte || '—'
  const locale = langueCourante.value === 'en' ? 'en-GB' : 'fr-FR'
  const d = new Date(dateTexte)
  const jour = d.toLocaleDateString(locale, { weekday: 'long' })
  const jourCapitalise = jour.charAt(0).toUpperCase() + jour.slice(1)
  return `${jourCapitalise}, ${horaireTexte || ''}`
}

function formaterDate(dateTexte) {
  if (!dateTexte) return '—'
  const locale = langueCourante.value === 'en' ? 'en-GB' : 'fr-FR'
  return new Date(dateTexte).toLocaleDateString(locale, {
    weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'
  })
}
</script>

<template>
  <div class="page-client" :class="{ 'mode-sombre': modeSombre }">
    <!-- Navbar identique -->
    <Navbar />

    <!-- Contenu Principal -->
    <main class="container-xl py-4">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
          <span class="sous-titre-badge">{{ t('trajets_badge') }}</span>
          <h1 class="titre-page mb-1 fs-2">{{ t('trajets_titre') }}</h1>
          <p class="paragraphe-guide mb-0">{{ t('trajets_desc') }}</p>
        </div>

        <router-link to="/client/mes-reservations" class="btn btn-outline-primary px-4 fw-bold">
          <img src="/icons/autres/receipt.png" alt="Ticket" class="icone-auto me-1" />
          <span>{{ t('trajets_mes_billets_btn') }}</span>
        </router-link>
      </div>

      <!-- Barre de Recherche et Filtres -->
      <div class="carte-recherche mb-4">
        <div class="row g-3">
          <div class="col-md-3">
            <label for="filtre-ville-depart" class="form-label text-secondary small fw-bold">{{ t('trajets_ville_dep') }}</label>
            <input id="filtre-ville-depart" v-model="villeDepart" type="text" class="form-control" :placeholder="t('trajets_placeholder_dep')" />
          </div>
          <div class="col-md-3">
            <label for="filtre-ville-arrivee" class="form-label text-secondary small fw-bold">{{ t('trajets_ville_arr') }}</label>
            <input id="filtre-ville-arrivee" v-model="villeArrivee" type="text" class="form-control" :placeholder="t('trajets_placeholder_arr')" />
          </div>
          <div class="col-md-3">
            <label for="filtre-date-voyage" class="form-label text-secondary small fw-bold">{{ t('trajets_date') }}</label>
            <input id="filtre-date-voyage" v-model="dateVoyage" type="date" class="form-control" />
          </div>
          <div class="col-md-3">
            <label for="filtre-type-vehicule" class="form-label text-secondary small fw-bold">{{ t('trajets_type') }}</label>
            <select id="filtre-type-vehicule" v-model="filtreVehicule" class="form-select">
              <option value="">{{ t('trajets_tous_types') }}</option>
              <option v-for="type in listeTypesVehicules" :key="type.id" :value="type.nom">
                {{ type.nom }}
              </option>
            </select>
          </div>

          <!-- Indicateur de filtres actifs & Bouton réinitialiser -->
          <div v-if="villeDepart || villeArrivee || dateVoyage || filtreVehicule" class="col-12 mt-2 pt-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="small text-secondary d-flex align-items-center flex-wrap gap-1">
              <span class="fw-bold">{{ t('trajets_filtre_applique') }}</span>
              <span v-if="villeDepart" class="badge bg-light text-dark border">{{ villeDepart }}</span>
              <span v-if="villeDepart && villeArrivee" class="text-muted">➔</span>
              <span v-if="villeArrivee" class="badge bg-light text-dark border">{{ villeArrivee }}</span>
              <span v-if="filtreVehicule" class="badge bg-primary text-white ms-1">{{ filtreVehicule }}</span>
              <span v-if="dateVoyage" class="badge bg-light text-dark border ms-1">{{ formaterDate(dateVoyage) }}</span>
            </div>
            <button @click="reinitialiserFiltres" type="button" class="btn btn-sm btn-outline-secondary px-3 d-inline-flex align-items-center gap-1">
              <span>✕</span>
              <span>{{ t('trajets_effacer_filtre') }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Chargement -->
      <div v-if="storeTrajets.chargement" class="text-center py-5 text-muted">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div>{{ t('trajets_chargement') }}</div>
      </div>

      <!-- Liste vide -->
      <div v-else-if="trajetsFiltres.length === 0" class="py-5 text-center bg-white rounded-4 border">
        <h4 class="titre-page fs-5">{{ t('trajets_aucun') }}</h4>
        <p class="paragraphe-guide small">{{ t('trajets_aucun_desc') }}</p>
      </div>

      <!-- Grille des Cartes de Trajets -->
      <div v-else class="row g-4">
        <div v-for="trajet in trajetsFiltres" :key="trajet.id" class="col-md-6 col-lg-4">
          <div class="carte-trajet-moderne">
            <div>
              <!-- En-tête : Date & Favori -->
              <div class="entete-carte-haut">
                <span class="date-trajet-haut">{{ formaterJourHoraire(trajet.date, trajet.horaire) }}</span>
                <button 
                  @click="toggleFavori(trajet.id)" 
                  class="btn-favori-trajet" 
                  :title="favoris.has(trajet.id) ? t('trajets_favori_retirer') : t('trajets_favori_ajouter')"
                >
                  <span v-if="favoris.has(trajet.id)" style="color: #f59e0b;">★</span>
                  <span v-else>☆</span>
                </button>
              </div>

              <!-- Titre Modèle / Trajet -->
              <h4 class="titre-modele-trajet">
                {{ trajet.vehicule?.type || 'Taxi-Brousse' }} ({{ trajet.vehicule?.matricule || 'TBA' }})
              </h4>

              <!-- Cadre Photo du Véhicule -->
              <div class="cadre-photo-vehicule">
                <img 
                  :src="getImageVehicule(trajet)" 
                  :alt="trajet.vehicule?.type || 'Véhicule'" 
                  class="photo-vehicule-img"
                />
              </div>

              <!-- Caractéristiques -->
              <div class="grille-caracteristiques">
                <div class="item-caracteristique">
                  <span class="label-carac">{{ t('trajets_label_trajet') }}</span>
                  <span class="valeur-carac">{{ trajet.ville_depart }} ➔ {{ trajet.ville_arrivee }}</span>
                </div>
                <div class="item-caracteristique">
                  <span class="label-carac">{{ t('trajets_label_chauffeur') }}</span>
                  <span class="valeur-carac">{{ trajet.vehicule?.chauffeur || 'Assigné' }}</span>
                </div>
                <div class="item-caracteristique">
                  <span class="label-carac">{{ t('trajets_label_dispo') }}</span>
                  <span class="valeur-carac fw-bold" :class="trajet.places_disponibles <= 2 ? 'text-danger' : 'text-success'">
                    {{ trajet.places_disponibles ?? trajet.vehicule?.nombre_places ?? 8 }} {{ t('trajets_places_libres') }}
                  </span>
                </div>
                <div class="item-caracteristique">
                  <span class="label-carac">{{ t('trajets_label_date') }}</span>
                  <span class="valeur-carac">{{ formaterDate(trajet.date) }}</span>
                </div>
              </div>

              <!-- Jauge de Remplissage (Règle 1 visuelle) -->
              <div class="mt-3 px-3">
                <div class="d-flex justify-content-between small text-muted mb-1">
                  <span>{{ t('trajets_remplissage') }}</span>
                  <span>{{ trajet.taux_remplissage ?? 0 }}%</span>
                </div>
                <div class="progress" style="height: 6px;">
                  <div 
                    class="progress-bar" 
                    :class="(trajet.taux_remplissage || 0) >= 80 ? 'bg-danger' : ((trajet.taux_remplissage || 0) >= 50 ? 'bg-warning' : 'bg-primary')" 
                    :style="{ width: (trajet.taux_remplissage || 0) + '%' }"
                  ></div>
                </div>
              </div>
            </div>

            <!-- Pied de carte : Prix et Bouton de réservation -->
            <div class="pied-carte-trajet mt-3">
              <div class="prix-trajet-grand">
                {{ trajet.prix || '-- Ar' }}
              </div>
              <router-link :to="'/client/places/' + trajet.id" class="btn-reserver-action d-inline-flex align-items-center gap-2">
                <span>{{ t('trajets_btn_choisir') }}</span>
                <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite icone-sur-fond-sombre" />
              </router-link>
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
</style>
