<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
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

// ---------------- CARROUSEL HERO IMAGE ----------------
const diapoActuelle = ref(0)
let minuteurCarrousel = null

const diapositives = computed(() => [
  {
    id: 1,
    numero: '1',
    nomRegion: 'RN2 & RN4',
    surtitre: t('hero_slide1_surtitre'),
    titre: t('hero_slide1_titre'),
    imageFond: '/images/tana.jpg',
    boutonInfo: true
  },
  {
    id: 2,
    numero: '2',
    nomRegion: 'RN2 TOAMASINA',
    surtitre: t('hero_slide2_surtitre'),
    titre: t('hero_slide2_titre'),
    imageFond: '/images/rn2.jpg',
    boutonInfo: false
  },
  {
    id: 3,
    numero: '3',
    nomRegion: 'ANTANANARIVO',
    surtitre: t('hero_slide3_surtitre'),
    titre: t('hero_slide3_titre'),
    imageFond: '/images/tana.jpg',
    boutonInfo: false
  },
  {
    id: 4,
    numero: '4',
    nomRegion: 'RN4 MAHAJANGA',
    surtitre: t('hero_slide4_surtitre'),
    titre: t('hero_slide4_titre'),
    imageFond: '/images/rn4.jpg',
    boutonInfo: false
  }
])

function diapoSuivante() {
  diapoActuelle.value = (diapoActuelle.value + 1) % diapositives.value.length
}

function changerDiapo(index) {
  diapoActuelle.value = index
}

function demarrerCarrousel() {
  minuteurCarrousel = setInterval(() => {
    diapoSuivante()
  }, 4800)
}

function arreterCarrousel() {
  if (minuteurCarrousel) clearInterval(minuteurCarrousel)
}

onMounted(() => {
  demarrerCarrousel()
  storeFourchettes.chargerFourchettes()
})

onUnmounted(() => {
  arreterCarrousel()
})
</script>

<template>
  <div class="page-client" :class="{ 'mode-sombre': modeSombre }">
    <!-- BARRE DE NAVIGATION COMMUNE -->
    <Navbar />

    <!-- CONTENU PRINCIPAL -->
    <main class="container-xl pt-4">
      <!-- GRAND CARROUSEL HERO IMAGE -->
      <section 
        class="cadre-carrousel-hero"
        @mouseenter="arreterCarrousel"
        @mouseleave="demarrerCarrousel"
      >
        <div 
          class="piste-carrousel"
          :style="{ transform: `translateX(-${diapoActuelle * 100}%)` }"
        >
          <div 
            v-for="slide in diapositives" 
            :key="slide.id"
            class="slide-hero"
            :style="{ backgroundImage: `url(${slide.imageFond})` }"
          >
            <div class="slide-voile-sombre"></div>

            <div class="slide-contenu">
              <span class="slide-surtitre">{{ slide.surtitre }}</span>
              <h1 class="slide-titre">{{ slide.titre }}</h1>

              <div class="groupe-actions-hero">
                <router-link to="/client/trajets" class="bouton-hero-principal">
                  <img src="/icons/autres/receipt.png" alt="Ticket" class="icone-sur-fond-sombre" />
                  <span>{{ t('hero_btn_departs') }}</span>
                </router-link>
                
                <a v-if="slide.boutonInfo" href="#services" class="bouton-hero-info">
                  <span>{{ t('hero_btn_services') }}</span>
                  <img src="/icons/autres/road.png" alt="Info" class="icone-sur-fond-sombre" />
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination verticale numérotée sur la gauche -->
        <div class="pagination-verticale">
          <button 
            v-for="(slide, index) in diapositives"
            :key="slide.id"
            class="pastille-numero"
            :class="{ active: diapoActuelle === index }"
            @click="changerDiapo(index)"
            :title="slide.nomRegion"
          >
            {{ slide.numero }}
          </button>
        </div>
      </section>

      <!-- 2. SECTION TARIFS FIXES (A ➔ B) -->
      <section class="mb-5">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
          <div>
            <span class="sous-titre-badge">{{ t('accueil_tarifs_badge') }}</span>
            <h2 class="titre-page mb-1">{{ t('accueil_tarifs_titre') }}</h2>
            <p class="paragraphe-guide mb-0">{{ t('accueil_tarifs_desc') }}</p>
          </div>
          <router-link to="/client/tarifs" class="btn btn-outline-primary px-4 d-inline-flex align-items-center gap-2">
            <span>{{ t('accueil_tarifs_btn_tous') }}</span>
            <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite" />
          </router-link>
        </div>

        <div v-if="storeFourchettes.chargement" class="text-center py-5 text-muted">
          {{ t('accueil_chargement_tarifs') }}
        </div>

        <!-- Tarifs affichés sous forme de cartes sans image externe Unsplash -->
        <div v-else class="row g-4">
          <!-- Carte Tamatave -->
          <div class="col-lg-4 col-md-6">
            <div class="carte-destination">
              <div class="image-destination-cadre">
                <img src="/images/rn2.jpg" alt="Tamatave" class="image-destination" />
              </div>
              <div class="corps-destination">
                <h3 class="nom-destination">Antananarivo ➔ Toamasina</h3>
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="localisation-region mb-0">
                    <img src="/icons/autres/marker.png" alt="Localisation" class="icone-auto" />
                    <span>RN2 · Atsinanana</span>
                  </div>
                  <span class="badge bg-light text-dark border small">Bowing, Sprinter, Sylver</span>
                </div>
                <div class="boite-fourchette-prix">
                  <div>
                    <span class="small text-muted d-block">{{ t('accueil_fourchette_prix') }}</span>
                    <span class="prix-chiffre">35 000 — 50 000 Ar</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Carte Majunga -->
          <div class="col-lg-4 col-md-6">
            <div class="carte-destination">
              <div class="image-destination-cadre">
                <img src="/images/rn4.jpg" alt="Majunga" class="image-destination" />
              </div>
              <div class="corps-destination">
                <h3 class="nom-destination">Antananarivo ➔ Mahajanga</h3>
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="localisation-region mb-0">
                    <img src="/icons/autres/marker.png" alt="Localisation" class="icone-auto" />
                    <span>RN4 · Boeny</span>
                  </div>
                  <span class="badge bg-light text-dark border small">Bowing, Sprinter</span>
                </div>
                <div class="boite-fourchette-prix">
                  <div>
                    <span class="small text-muted d-block">{{ t('accueil_fourchette_prix') }}</span>
                    <span class="prix-chiffre">45 000 — 75 000 Ar</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 3. SECTION SERVICES -->
      <section id="services" class="mb-5 py-4">
        <div class="text-center mb-5">
          <span class="sous-titre-badge">{{ t('accueil_services_badge') }}</span>
          <h2 class="titre-page mb-2">{{ t('accueil_services_titre') }}</h2>
          <p class="paragraphe-guide mx-auto" style="max-width: 620px;">
            {{ t('accueil_services_desc') }}
          </p>
        </div>

        <div class="row g-4 justify-content-center mx-auto">
          <div class="col-md-4">
            <div class="carte-service-style">
              <div class="rond-icone-service">
                <img src="/icons/autres/map-location-track.png" alt="Destination" class="icone-auto" style="width: 32px; height: 32px;" />
              </div>
              <div>
                <h3 class="titre-service-style">{{ t('service_1_titre') }}</h3>
                <p class="desc-service-style">{{ t('service_1_desc') }}</p>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="carte-service-style carte-vedette">
              <div class="rond-icone-service">
                <img src="/icons/autres/receipt.png" alt="Ticket" class="icone-sur-fond-sombre" style="width: 32px; height: 32px;" />
              </div>
              <div>
                <h3 class="titre-service-style">{{ t('service_2_titre') }}</h3>
                <p class="desc-service-style">{{ t('service_2_desc') }}</p>
              </div>
              <router-link to="/client/trajets" class="bouton-service-cta mt-2 d-inline-flex align-items-center justify-content-center gap-2">
                <span>{{ t('service_2_btn') }}</span>
                <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite icone-sur-fond-sombre" />
              </router-link>
            </div>
          </div>

          <div class="col-md-4">
            <div class="carte-service-style">
              <div class="rond-icone-service">
                <img src="/icons/autres/steering-wheel.png" alt="Securite" class="icone-auto" style="width: 32px; height: 32px;" />
              </div>
              <div>
                <h3 class="titre-service-style">{{ t('service_3_titre') }}</h3>
                <p class="desc-service-style">{{ t('service_3_desc') }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <Footer />

    <ChatBot />
  </div>
</template>

<style scoped>
@import "@/assets/css/client/base.css";
@import "@/assets/css/client/dashboard.css";
</style>
