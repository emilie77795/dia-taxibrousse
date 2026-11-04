<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/api'
import Navbar from '@/components/client/Navbar.vue'
import Footer from '@/components/client/Footer.vue'
import ChatBot from '@/components/client/ChatBot.vue'
import { useFourchettesStore } from '@/stores/fourchettes'
import { useThemeStore } from '@/stores/theme'
import { t } from '@/i18n'

const routeur = useRouter()
const storeFourchettes = useFourchettesStore()
const themeStore = useThemeStore()
const modeSombre = computed(() => themeStore.modeSombre)


// Recherche & Filtres
const filtreRecherche = ref('')
const filtreVehicule = ref('')
const listeTypesVehicules = ref([])

async function chargerTypesVehicules() {
  try {
    const rep = await api.get('/api/types-vehicules')
    listeTypesVehicules.value = rep.data
  } catch (erreur) {
    console.error('Erreur chargement types de véhicules', erreur)
  }
}

onMounted(async () => {
  await storeFourchettes.chargerFourchettes()
  await chargerTypesVehicules()
})

// Filtrage puis regroupement par trajet (Départ ➔ Arrivée)
const tarifsFiltres = computed(() => {
  return storeFourchettes.listeFourchettes.filter(item => {
    const texte = (item.titre + ' ' + (item.region || '') + ' ' + (item.depart || '') + ' ' + (item.arrivee || '')).toLowerCase()
    const matchTexte = !filtreRecherche.value || texte.includes(filtreRecherche.value.toLowerCase())
    const matchType = !filtreVehicule.value || (item.typeVehicule && item.typeVehicule === filtreVehicule.value)
    return matchTexte && matchType
  })
})

const trajetsGroupes = computed(() => {
  const groupes = {}

  tarifsFiltres.value.forEach(item => {
    const cle = item.titre // ex: "Antananarivo ➔ Toamasina"
    if (!groupes[cle]) {
      groupes[cle] = {
        titre: item.titre,
        region: item.region,
        depart: item.depart,
        arrivee: item.arrivee,
        items: []
      }
    }
    groupes[cle].items.push(item)
  })

  // Trié par ordre alphabétique du trajet
  return Object.values(groupes).sort((a, b) => a.titre.localeCompare(b.titre))
})

function getImageVehicule(item) {
  const nomType = (item?.typeVehicule || item?.nom || '').toLowerCase()

  // 1. Détection directe selon le type de véhicule
  if (nomType.includes('sylver') || nomType.includes('silver')) {
    return '/images/sylver_class2.png'
  }
  if (nomType.includes('bowing') || nomType.includes('boeing') || nomType.includes('bus')) {
    return '/images/bowing3.png'
  }
  if (nomType.includes('sprinter')) {
    return '/images/sprinter1.png'
  }

  // 2. Si une image spécifique est fournie par le backend (ex: uploadée)
  if (item?.image && !item.image.includes('unsplash.com')) {
    if (item.image.startsWith('/uploads/')) {
      return `http://localhost:8000${item.image}`
    }
    return item.image
  }

  return '/images/sprinter1.png'
}

// Correspondance ville d'arrivée → route nationale (donnée géographique fixe à Madagascar)
const lignesNationales = {
  'toamasina': 'Ligne Nationale 2',
  'tamatave': 'Ligne Nationale 2',
  'mahajanga': 'Ligne Nationale 4',
  'majunga': 'Ligne Nationale 4',
  'antsirabe': 'Ligne Nationale 7',
  'fianarantsoa': 'Ligne Nationale 7',
  'toliara': 'Ligne Nationale 7',
  'tulear': 'Ligne Nationale 7',
  'antsiranana': 'Ligne Nationale 6',
  'diego': 'Ligne Nationale 6',
  'diego-suarez': 'Ligne Nationale 6',
  'morondava': 'Ligne Nationale 35',
  'ambatondrazaka': 'Ligne Nationale 44',
  'manakara': 'Ligne Nationale 25',
}

function getLigneNationale(villeArrivee) {
  if (!villeArrivee) return 'Ligne Nationale'
  const cle = String(villeArrivee).trim().toLowerCase()
  if (cle.includes('mahajanga') || cle.includes('majunga')) return 'Ligne Nationale 4'
  if (cle.includes('toamasina') || cle.includes('tamatave')) return 'Ligne Nationale 2'
  if (cle.includes('fianarantsoa') || cle.includes('toliara') || cle.includes('tulear') || cle.includes('antsirabe')) return 'Ligne Nationale 7'
  if (cle.includes('antsiranana') || cle.includes('diego')) return 'Ligne Nationale 6'
  if (cle.includes('morondava')) return 'Ligne Nationale 35'
  if (cle.includes('ambatondrazaka')) return 'Ligne Nationale 44'
  if (cle.includes('manakara')) return 'Ligne Nationale 25'

  return lignesNationales[cle] || 'Ligne Nationale'
}

function lienVoirDeparts(item, groupe) {
  let dep = item?.depart || groupe?.depart
  let arr = item?.arrivee || groupe?.arrivee
  if ((!dep || !arr) && (item?.titre || groupe?.titre)) {
    const titre = item?.titre || groupe?.titre
    const parties = titre.split('➔').map(p => p.trim())
    if (parties.length >= 2) {
      dep = dep || parties[0]
      arr = arr || parties[1]
    }
  }

  const query = {}
  if (dep) query.depart = dep
  if (arr) query.arrivee = arr
  if (item?.typeVehicule) query.type = item.typeVehicule

  return {
    path: '/client/trajets',
    query
  }
}
</script>

<template>
  <div class="page-client" :class="{ 'mode-sombre': modeSombre }">
    <!-- Navbar identique -->
    <Navbar />

    <!-- Contenu Principal -->
    <main class="container-xl py-4">
      <div class="carte-recherche mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <div>
            <span class="sous-titre-badge">{{ t('tarifs_badge') }}</span>
            <h1 class="titre-page mb-2 fs-2">{{ t('tarifs_titre') }}</h1>
            <p class="paragraphe-guide mb-0">{{ t('tarifs_desc') }}</p>
          </div>
          <router-link to="/client/trajets" class="btn btn-primary px-4 py-2 fw-bold d-inline-flex align-items-center gap-2">
            <img src="/icons/autres/receipt.png" alt="Ticket" class="icone-sur-fond-sombre" style="width: 18px; height: 18px;" />
            <span>{{ t('tarifs_reserver_btn') }}</span>
          </router-link>
        </div>

        <!-- Filtres rapides -->
        <div class="row g-3 mt-3 pt-3 border-top">
          <div class="col-md-7">
            <label for="tarifs-recherche-ville" class="form-label text-secondary small fw-bold">{{ t('tarifs_recherche_ville') }}</label>
            <div class="input-group">
              <span class="input-group-text bg-light">
                <img src="/icons/autres/marker.png" alt="Search" class="icone-auto" style="width: 16px; height: 16px;" />
              </span>
              <input 
                id="tarifs-recherche-ville"
                name="tarifs-recherche-ville"
                v-model="filtreRecherche" 
                type="text" 
                class="form-control" 
                :placeholder="t('tarifs_placeholder_recherche')"
                autocomplete="off"
              />
            </div>
          </div>
          <div class="col-md-5">
            <label for="tarifs-filtre-vehicule" class="form-label text-secondary small fw-bold">{{ t('tarifs_filtre_type') }}</label>
            <select id="tarifs-filtre-vehicule" name="tarifs-filtre-vehicule" v-model="filtreVehicule" class="form-select">
              <option value="">{{ t('tarifs_tous_types') }}</option>
              <option v-for="type in listeTypesVehicules" :key="type.id" :value="type.nom">
                {{ type.nom }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Chargement -->
      <div v-if="storeFourchettes.chargement" class="text-center py-5 text-muted">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div>{{ t('tarifs_chargement') }}</div>
      </div>

      <!-- Liste vide -->
      <div v-else-if="trajetsGroupes.length === 0" class="py-5 text-center bg-white rounded-4 border">
        <h4 class="titre-page fs-5">{{ t('tarifs_aucun') }}</h4>
        <p class="paragraphe-guide small">{{ t('tarifs_aucun_desc') }}</p>
      </div>

      <!-- Groupes par trajet -->
      <div v-else class="mb-5">
        <div v-for="groupe in trajetsGroupes" :key="groupe.titre" class="mb-5">

          <!-- Titre de section du trajet -->
          <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
            <h2 class="titre-page fs-4 mb-0">{{ groupe.titre }}</h2>
            <span class="badge bg-light text-dark border ms-2">{{ groupe.items.length }} {{ t('trajets_type').toLowerCase() }}</span>
          </div>

          <!-- Cartes des véhicules pour ce trajet -->
          <div class="row g-4">
            <div
              v-for="item in groupe.items"
              :key="item.id"
              class="col-md-6 col-lg-4"
            >
              <div class="carte-destination h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="cadre-photo-vehicule">
                    <img
                      :src="getImageVehicule(item)"
                      :alt="item.typeVehicule || item.titre"
                      class="photo-vehicule-tarifs"
                    />
                  </div>

                  <div class="corps-destination pb-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <div class="localisation-region mb-0">
                        <img src="/icons/autres/marker.png" alt="Localisation" class="icone-auto" />
                        <span>{{ getLigneNationale(item.arrivee || groupe.arrivee || item.titre) }}</span>
                      </div>
                      <span class="badge bg-light text-dark border small">{{ item.typeVehicule || 'Standard' }}</span>
                    </div>

                    <div class="boite-fourchette-prix my-3">
                      <div>
                        <span class="small text-muted d-block">{{ t('tarifs_officiel') }}</span>
                        <span class="prix-chiffre">{{ item.prix }}</span>
                      </div>
                    </div>

                    <router-link :to="lienVoirDeparts(item, groupe)" class="btn btn-outline-primary btn-sm w-100 mt-2 d-inline-flex align-items-center justify-content-center gap-2">
                      <span>{{ t('tarifs_voir_departs') }}</span>
                      <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite" />
                    </router-link>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Pied de page commun -->
    <Footer />

    <!-- Chatbot 24h/24 -->
    <ChatBot />
  </div>
</template>

<style scoped>
@import "@/assets/css/client/base.css";
@import "@/assets/css/client/dashboard.css";
@import "@/assets/css/client/tarifs.css";
</style>
