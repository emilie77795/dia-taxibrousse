<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import Navbar from '@/components/client/Navbar.vue'
import Footer from '@/components/client/Footer.vue'
import ChatBot from '@/components/client/ChatBot.vue'
import { useThemeStore } from '@/stores/theme'
import { t } from '@/i18n'
import api from '@/api'

const routeur = useRouter()
const themeStore = useThemeStore()
const modeSombre = computed(() => themeStore.modeSombre)

const notifications = ref([])
const chargement = ref(true)
const filtre = ref('tous')

const filtreOptions = computed(() => [
  { val: 'tous', label: t('notif_filtre_toutes') },
  { val: 'message_admin', label: t('notif_filtre_messages') },
  { val: 'remboursement_valide', label: t('notif_filtre_remboursements') },
  { val: 'remboursement_refuse', label: t('notif_filtre_refuses') },
  { val: 'report_voyage', label: t('notif_filtre_reports') },
  { val: 'nouvelle_reservation', label: t('notif_filtre_reservations') },
])

const notifsFiltrees = computed(() => {
  if (filtre.value === 'tous') return notifications.value
  return notifications.value.filter(n => n.type === filtre.value)
})

const nbNonLues = computed(() => notifications.value.filter(n => n.statut === 'non_lu').length)

async function chargerNotifications() {
  chargement.value = true
  try {
    const { data } = await api.get('/api/notifications')
    notifications.value = data || []
    const unreadCount = (data || []).filter(n => n.statut === 'non_lu').length
    if (unreadCount > 0) {
      try {
        await api.post('/api/notifications/lire')
        notifications.value = notifications.value.map(n => ({ ...n, statut: 'lu' }))
        window.dispatchEvent(new CustomEvent('dia:notifications-lues'))
      } catch (e) {}
    }
  } catch (err) {
    console.error('Erreur chargement notifications', err)
  } finally {
    chargement.value = false
  }
}

async function marquerToutesLues() {
  try {
    await api.post('/api/notifications/lire')
    notifications.value = notifications.value.map(n => ({ ...n, statut: 'lu' }))
    window.dispatchEvent(new CustomEvent('dia:notifications-lues'))
  } catch (err) {
    console.error('Erreur marquage lu', err)
  }
}

async function marquerUneLue(notif) {
  if (notif.statut === 'lu') return
  try {
    await api.post(`/api/notifications/${notif.id}/lire`)
    const idx = notifications.value.findIndex(n => n.id === notif.id)
    if (idx !== -1) notifications.value[idx] = { ...notifications.value[idx], statut: 'lu' }
    if (!notifications.value.some(n => n.statut === 'non_lu')) {
      window.dispatchEvent(new CustomEvent('dia:notifications-lues'))
    }
  } catch (err) {
    console.error('Erreur marquage lu', err)
  }
}

function formaterDateHeure(dateTexte) {
  if (!dateTexte) return '—'
  return new Date(dateTexte).toLocaleString('fr-FR', {
    weekday: 'short', day: '2-digit', month: 'short',
    year: 'numeric', hour: '2-digit', minute: '2-digit'
  })
}

function iconeType(type) {
  const icons = {
    message_admin: '/icons/message/megaphone.png',
    remboursement_valide: '/icons/autres/receipt.png',
    remboursement_refuse: '/icons/autres/loading.png',
    report_voyage: '/icons/autres/undo.png',
    nouvelle_reservation: '/icons/autres/receipt.png',
    demande_remboursement: '/icons/autres/loading.png',
    nouveau_voyageur: '/icons/utilisateur/user.png',
  }
  return icons[type] || '/icons/message/megaphone.png'
}

function labelType(type) {
  const labels = {
    message_admin: t('notif_filtre_messages'),
    remboursement_valide: t('notif_filtre_remboursements'),
    remboursement_refuse: t('notif_filtre_refuses'),
    report_voyage: t('notif_filtre_reports'),
    nouvelle_reservation: t('notif_filtre_reservations'),
    demande_remboursement: t('billets_filtre_attente'),
    nouveau_voyageur: t('notif_nouveau'),
  }
  return labels[type] || type
}

function badgeCouleur(type) {
  const couleurs = {
    message_admin: '#13385C',
    remboursement_valide: '#16a34a',
    remboursement_refuse: '#dc2626',
    report_voyage: '#f59e0b',
    nouvelle_reservation: '#13385C',
    demande_remboursement: '#f59e0b',
  }
  return couleurs[type] || '#64748b'
}

function lienAction(type) {
  const liens = {
    remboursement_valide: '/client/mes-reservations',
    remboursement_refuse: '/client/mes-reservations',
    report_voyage: '/client/mes-reservations',
    nouvelle_reservation: '/client/mes-reservations',
    demande_remboursement: '/client/mes-reservations',
  }
  return liens[type] || null
}

onMounted(async () => {
  await chargerNotifications()
})
</script>

<template>
  <div class="page-client" :class="{ 'mode-sombre': modeSombre }">
    <Navbar />

    <main class="container-xl py-4">
      <!-- En-tête -->
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
          <span class="sous-titre-badge">{{ t('notif_badge') }}</span>
          <h1 class="titre-page mb-1 fs-2">{{ t('notif_titre') }}</h1>
          <p class="paragraphe-guide mb-0">
            {{ t('notif_desc') }}
          </p>
        </div>
        <div class="d-flex gap-2">
          <button
            v-if="nbNonLues > 0"
            @click="marquerToutesLues"
            class="btn btn-sm px-3 fw-bold"
            style="background:#13385C;color:#FFE7A8;border:none;"
          >
            ✓ {{ t('notif_tout_marquer_lu') }}
          </button>
          <router-link to="/client/mes-reservations" class="btn btn-outline-secondary btn-sm px-3 d-inline-flex align-items-center gap-2">
            <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-gauche" />
            <span>{{ t('notif_mes_billets') }}</span>
          </router-link>
        </div>
      </div>

      <!-- Filtres -->
      <div class="d-flex flex-wrap gap-2 mb-4">
        <button
          v-for="opt in filtreOptions"
          :key="opt.val"
          @click="filtre = opt.val"
          class="btn btn-sm px-3"
          :class="filtre === opt.val ? 'btn-primary' : 'btn-outline-secondary'"
        >
          {{ opt.label }}
          <span
            v-if="opt.val !== 'tous' && notifications.filter(n => n.type === opt.val && n.statut === 'non_lu').length > 0"
            class="badge bg-danger ms-1"
            style="font-size:0.65rem;"
          >
            {{ notifications.filter(n => n.type === opt.val && n.statut === 'non_lu').length }}
          </span>
        </button>
      </div>

      <!-- Chargement -->
      <div v-if="chargement" class="text-center py-5 text-muted">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div>{{ t('notif_chargement') }}</div>
      </div>

      <!-- Aucune notification -->
      <div v-else-if="notifsFiltrees.length === 0" class="py-5 text-center bg-white rounded-4 border">
        <img src="/icons/message/megaphone.png" alt="Notifications" style="width:52px;height:52px;opacity:0.35;" class="mb-3" />
        <h4 class="titre-page fs-5">{{ t('notif_aucun') }}</h4>
        <p class="paragraphe-guide small">{{ t('notif_aucun_desc') }}</p>
        <router-link to="/client/trajets" class="btn btn-primary px-4 mt-2">
          {{ t('notif_voir_trajets') }}
        </router-link>
      </div>

      <!-- Liste des notifications -->
      <div v-else class="d-flex flex-column gap-3">
        <div
          v-for="n in notifsFiltrees"
          :key="n.id"
          class="carte-notif bg-white rounded-4 border p-3 p-md-4 shadow-sm"
          :class="n.statut === 'non_lu' ? 'carte-notif-nonlue' : ''"
          @click="marquerUneLue(n)"
        >
          <div class="d-flex align-items-start gap-3">
            <!-- Icône colorée -->
            <div
              class="notif-icone-cercle flex-shrink-0"
              :style="{ background: badgeCouleur(n.type) + '22', border: '2px solid ' + badgeCouleur(n.type) }"
            >
              <img :src="iconeType(n.type)" alt="" style="width:22px;height:22px;" />
            </div>

            <!-- Corps -->
            <div class="flex-grow-1 min-w-0">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                <span
                  class="badge rounded-pill px-2 py-1 small fw-bold"
                  :style="{ background: badgeCouleur(n.type), color: '#fff' }"
                >
                  {{ labelType(n.type) }}
                </span>
                <span class="small text-muted text-nowrap">{{ formaterDateHeure(n.date_creation) }}</span>
              </div>

              <p class="mb-2 text-dark" style="line-height:1.5;">{{ n.message }}</p>

              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <!-- Badge non-lu -->
                <span v-if="n.statut === 'non_lu'" class="badge bg-warning text-dark" style="font-size:0.7rem;">
                  {{ t('notif_nouveau') }}
                </span>
                <span v-else class="small text-muted">{{ t('notif_lu') }}</span>

                <!-- Bouton d'action -->
                <router-link
                  v-if="lienAction(n.type)"
                  :to="lienAction(n.type)"
                  class="btn btn-sm px-3 fw-bold d-inline-flex align-items-center gap-1"
                  style="background:#13385C;color:#FFE7A8;border:none;font-size:0.78rem;"
                  @click.stop
                >
                  <span>{{ t('notif_voir_reservations') }}</span>
                  <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite icone-sur-fond-sombre" />
                </router-link>
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>

    <Footer />
    <ChatBot />
  </div>
</template>

<style scoped>
@import "@/assets/css/client/base.css";
@import "@/assets/css/client/notifications.css";
</style>
