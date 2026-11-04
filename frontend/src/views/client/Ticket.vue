<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Navbar from '@/components/client/Navbar.vue'
import Footer from '@/components/client/Footer.vue'
import { useThemeStore } from '@/stores/theme'
import { t, langueCourante } from '@/i18n'
import api from '@/api'
import QRCode from 'qrcode'

const routeur = useRouter()
const route = useRoute()
const themeStore = useThemeStore()
const modeSombre = computed(() => themeStore.modeSombre)

const reservation = ref(null)
const chargement = ref(true)
const qrCanvas = ref(null)

async function chargerTicket() {
  chargement.value = true
  const id = parseInt(route.params.id)
  try {
    const { data } = await api.get(`/api/reservations/${id}`)
    if (data) {
      reservation.value = data
    }
  } catch (err) {
    console.error('Erreur chargement du ticket', err)
  } finally {
    chargement.value = false
  }
}

async function genererQRCode() {
  if (!qrCanvas.value || !reservation.value?.ticket) return
  const contenuQR = reservation.value.ticket
  try {
    await QRCode.toCanvas(qrCanvas.value, contenuQR, {
      width: 130,
      margin: 1,
      color: {
        dark: '#0f172a',
        light: '#ffffff'
      },
      errorCorrectionLevel: 'M'
    })
  } catch (err) {
    console.error('Erreur génération QR code', err)
  }
}

// Regénérer le QR quand reservation change et que le canvas est monté
watch([reservation, qrCanvas], async ([res, canvas]) => {
  if (res && canvas) {
    await nextTick()
    genererQRCode()
  }
})

function imprimerTicket() {
  window.print()
}

function formaterDate(dateTexte) {
  if (!dateTexte) return '—'
  const locale = langueCourante.value === 'en' ? 'en-GB' : 'fr-FR'
  return new Date(dateTexte).toLocaleDateString(locale, {
    weekday: 'long', day: '2-digit', month: 'long', year: 'numeric'
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
  const v = String(ville).trim().toLowerCase()
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
  return (String(ville).trim().slice(0, 3) || 'DIA').toUpperCase()
}

const villeDepartNom = computed(() => {
  if (!reservation.value) return '—'
  return reservation.value.ville_depart || extraireVilles(reservation.value.trajet).depart || '—'
})

const villeArriveeNom = computed(() => {
  if (!reservation.value) return '—'
  return reservation.value.ville_arrivee || extraireVilles(reservation.value.trajet).arrivee || '—'
})

const siegesTexte = computed(() => {
  if (!reservation.value) return '—'
  if (Array.isArray(reservation.value.places) && reservation.value.places.length > 0) {
    return reservation.value.places.map(p => 'N° ' + p).join(', ')
  }
  if (reservation.value.numero_siege) return 'N° ' + reservation.value.numero_siege
  return `${reservation.value.nombre_places || 1} place(s)`
})

onMounted(() => {
  chargerTicket()
})
</script>

<template>
  <div class="page-client" :class="{ 'mode-sombre': modeSombre }">
    <!-- Navbar (masquée à l'impression via CSS print) -->
    <div class="d-print-none">
      <Navbar />
    </div>

    <!-- Contenu du ticket -->
    <main class="container py-4">
      <!-- Boutons d'action haut -->
      <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <router-link to="/client/mes-reservations" class="btn btn-outline-secondary px-3 d-inline-flex align-items-center gap-2">
          <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-gauche" />
          <span>{{ t('ticket_retour') }}</span>
        </router-link>

        <button @click="imprimerTicket" class="btn btn-primary px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
          <img src="/icons/autres/download.png" alt="Print" class="icone-sur-fond-sombre" style="width: 16px; height: 16px;" />
          <span>{{ t('ticket_imprimer') }}</span>
        </button>
      </div>

      <!-- Chargement -->
      <div v-if="chargement" class="text-center py-5 text-muted">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div>{{ t('ticket_chargement') }}</div>
      </div>

      <!-- Introuvable -->
      <div v-else-if="!reservation" class="py-5 text-center bg-white rounded-4 border">
        <h4 class="titre-page fs-5">{{ t('places_introuvable') }}</h4>
        <p class="paragraphe-guide small">{{ t('places_introuvable_desc') }}</p>
        <router-link to="/client/mes-reservations" class="btn btn-primary mt-2">
          {{ t('billets_titre') }}
        </router-link>
      </div>

      <!-- LE TICKET NUMÉRIQUE OFFICIEL DIA -->
      <div v-else class="cadre-billet-impression mx-auto">
        <div class="carte-billet-dia shadow-sm">

          <!-- En-tête compagnie -->
          <div class="entete-billet p-3 d-flex align-items-center justify-content-between">
            <img src="/logo.png" alt="DIA Logo" style="height: 100px; width: auto;" />
            <div class="text-center flex-grow-1">
              <h6 class="mb-0 fw-bold text-uppercase" style="font-size: 0.85rem; letter-spacing: 1.5px;">dia taxi-brousse</h6>
              <div class="small text-muted mt-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">{{ t('ticket_billet_electronique') }}</div>
            </div>
            <div class="text-end pe-3 d-flex flex-column align-items-end">
              <span class="text-muted small text-uppercase mb-1">{{ t('ticket_billet_numero') }}</span>
              <span class="badge bg-dark text-white fw-bold fs-6">{{ reservation.ticket }}</span>
            </div>
          </div>

          <!-- Corps du billet (style carte d'embarquement) -->
          <div class="corps-billet p-4 bg-white">
            <!-- Ligne FROM / voiture / TO -->
            <div class="row g-3 align-items-center mb-3">
              <div class="col-4 text-start">
                <span class="text-muted small d-block text-uppercase">{{ t('ticket_depart') }}</span>
                <span class="code-ville fw-bold fs-4">{{ codeVille(villeDepartNom) }}</span>
                <div class="nom-ville small text-truncate">{{ villeDepartNom }}</div>
                <div class="heure-ville small text-muted">{{ reservation.horaire || '—' }}</div>
              </div>
              <div class="col-4 text-center d-flex align-items-center justify-content-center gap-2">
                <div class="segment-trajet"></div>
                <div class="icone-vehicule">
                  <svg class="svg-minibus" viewBox="0 0 24 14" fill="currentColor">
                    <path d="M1 2.5C1 1.7 1.7 1 2.5 1H15.5C16.3 1 17 1.4 17.5 2.1L20.8 6.2C21.1 6.6 21.3 7.1 21.3 7.7V10.5C21.3 11.3 20.6 12 19.8 12H19.2C18.8 10.8 17.6 10 16.3 10C14.9 10 13.7 10.8 13.3 12H9.7C9.3 10.8 8.1 10 6.8 10C5.4 10 4.2 10.8 3.8 12H2.5C1.7 12 1 11.3 1 10.5V2.5ZM4 3V6.2H7.5V3H4ZM9 3V6.2H13.8V3H9ZM15.3 3V6.2H18.8L16.4 3H15.3Z"/>
                    <circle cx="6.8" cy="12" r="1.8"/>
                    <circle cx="16.3" cy="12" r="1.8"/>
                  </svg>
                </div>
                <div class="segment-trajet"></div>
              </div>
              <div class="col-4 text-end">
                <span class="text-muted small d-block text-uppercase">{{ t('ticket_arrivee') }}</span>
                <span class="code-ville fw-bold fs-4">{{ codeVille(villeArriveeNom) }}</span>
                <div class="nom-ville small text-truncate">{{ villeArriveeNom }}</div>
                <div class="heure-ville small text-muted">{{ formaterDate(reservation.date) }}</div>
              </div>
            </div>

            <!-- Ligne de rétention (stub) -->
            <div class="ligne-stub my-4"></div>

            <!-- Informations passager + QR code -->
            <div class="section-passager-qr">
              <!-- Infos passager à gauche -->
              <div class="infos-passager-col">
                <!-- Passager -->
                <div class="info-ligne-ticket">
                  <span class="info-label-ticket">{{ t('ticket_passager') }}</span>
                  <strong class="info-valeur-ticket">{{ reservation.nom_client || '—' }}</strong>
                </div>

                <!-- Sièges -->
                <div class="info-ligne-ticket">
                  <span class="info-label-ticket">{{ t('ticket_sieges') }}</span>
                  <strong class="info-valeur-ticket text-primary">{{ siegesTexte }}</strong>
                </div>

                <!-- Véhicule -->
                <div class="info-ligne-ticket">
                  <span class="info-label-ticket">{{ t('ticket_vehicule_label') }}</span>
                  <strong class="info-valeur-ticket">{{ reservation.type_vehicule || '—' }}</strong>
                </div>

                <!-- Séparateur -->
                <div class="ligne-stub-mini mt-3 mb-3"></div>

                <!-- Tarif & opérateur -->
                <div class="d-flex justify-content-between align-items-start gap-3">
                  <div>
                    <span class="info-label-ticket">{{ t('ticket_tarif') }}</span>
                    <strong class="info-valeur-ticket d-block">{{ reservation.montant_paye_texte }}</strong>
                  </div>
                  <div class="text-end">
                    <span class="info-label-ticket">{{ t('ticket_operateur') }}</span>
                    <strong class="info-valeur-ticket d-block">{{ reservation.operateur || 'Mobile Money' }}</strong>
                  </div>
                </div>
              </div>

              <!-- Séparateur pointillé vertical -->
              <div class="separateur-stub-vertical"></div>

              <!-- Zone QR code à droite -->
              <div class="zone-qr-code">
                <div class="qr-cadre">
                  <canvas ref="qrCanvas" class="qr-canvas"></canvas>
                </div>
                <div class="qr-legende">
                  <div class="qr-legende-titre">{{ t('ticket_qr_scan_titre') }}</div>
                  <div class="qr-ticket-ref">{{ reservation.ticket }}</div>
                  <div class="qr-legende-info">{{ t('ticket_qr_scan_info') }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Bandeau bas -->
          <div class="p-3 text-center small text-muted border-top bg-light">
            {{ t('ticket_footer_service') }}
          </div>
        </div>

        <!-- Note anti-fraude (visible uniquement à l'écran) -->
        <div class="note-antifraude d-print-none mt-3 mx-auto">
          <svg class="icone-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            <path d="M9 12l2 2 4-4"/>
          </svg>
          <span>{{ t('ticket_antifraude') }}</span>
        </div>
      </div>
    </main>

    <!-- Footer (masqué à l'impression) -->
    <div class="d-print-none">
      <Footer />
    </div>
  </div>
</template>

<style scoped>
@import "@/assets/css/client/base.css";
@import "@/assets/css/client/reservation.css";
@import "@/assets/css/client/ticket.css";
</style>
