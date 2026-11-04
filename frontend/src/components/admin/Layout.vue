<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/api'

defineProps({
  title: {
    type: String,
    default: 'Tableau de bord'
  }
})

const routeur = useRouter()
const route = useRoute()
const auth = useAuthStore()

// Notifications
const notifications = ref([])
const pannNotifOuvert = ref(false)

const nbNotifNonLues = computed(() =>
  notifications.value.filter(n => n.statut === 'non_lu').length
)

const dernieresNotifs = computed(() => notifications.value.slice(0, 6))

function iconeType(type) {
  const icons = {
    demande_remboursement: '/icons/autres/loading.png',
    report_voyage: '/icons/autres/undo.png',
    nouveau_voyageur: '/icons/utilisateur/user.png',
    nouvelle_reservation: '/icons/autres/receipt.png'
  }
  return icons[type] || '/icons/message/megaphone.png'
}

function formaterDateCourte(dateTexte) {
  if (!dateTexte) return ''
  return new Date(dateTexte).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

async function chargerNotifications() {
  try {
    const { data } = await api.get('/api/notifications')
    notifications.value = data || []
  } catch (e) {}
}

async function ouvrirNotifications() {
  pannNotifOuvert.value = !pannNotifOuvert.value
  if (!pannNotifOuvert.value || nbNotifNonLues.value === 0) return

  try {
    await api.post('/api/notifications/lire')
    notifications.value = notifications.value.map(notification => ({
      ...notification,
      statut: 'lu'
    }))
  } catch (e) {}
}

function seDeconnecter() {
  auth.deconnexion()
  routeur.push('/admin/login')
}

onMounted(() => {
  chargerNotifications()
  window.addEventListener('dia:notifications-lues', chargerNotifications)
})

onUnmounted(() => {
  window.removeEventListener('dia:notifications-lues', chargerNotifications)
})
</script>

<template>
  <div class="page-admin">
    <!-- Barre latérale -->
    <aside class="menu-lateral">
      <div class="logo-menu d-flex align-items-center gap-2">
        <img src="/logo-dia.png" alt="DIA Logo" style="height: 38px; width: auto;" />
        <div>
          <span class="titre">DIA Admin</span>
          <span class="sous-titre">Gestion TaxiBrousse</span>
        </div>
      </div>

      <nav class="navigation-menu">
        <router-link to="/admin/dashboard" class="lien-menu" :class="{ actif: route.path === '/admin/dashboard' }">
          <img src="/icons/autres/map-location-track.png" alt="" class="icone-menu" /> Tableau de bord
        </router-link>
        <router-link to="/admin/guichet" class="lien-menu" :class="{ actif: route.path === '/admin/guichet' }">
          <img src="/icons/autres/ticket.png" alt="" class="icone-menu" /> Réservation Guichet
        </router-link>
        <router-link to="/admin/checkpoint" class="lien-menu" :class="{ actif: route.path === '/admin/checkpoint' }">
          <img src="/icons/autres/receipt.png" alt="" class="icone-menu" /> Embarquement & Checkpoint
        </router-link>
        <router-link to="/admin/reservations" class="lien-menu" :class="{ actif: route.path === '/admin/reservations' }">
          <img src="/icons/autres/receipt.png" alt="" class="icone-menu" /> Réservations & Tickets
        </router-link>
        <router-link to="/admin/trajets" class="lien-menu" :class="{ actif: route.path === '/admin/trajets' }">
          <img src="/icons/autres/map-location-track.png" alt="" class="icone-menu" /> Gestion des Trajets
        </router-link>
        <router-link to="/admin/flotte" class="lien-menu" :class="{ actif: route.path === '/admin/flotte' }">
          <img src="/icons/autres/car-alt.png" alt="" class="icone-menu" /> Véhicules & Chauffeurs
        </router-link>
        <router-link to="/admin/fourchettes" class="lien-menu" :class="{ actif: route.path === '/admin/fourchettes' }">
          <img src="/icons/autres/money-bill-wave.png" alt="" class="icone-menu" /> Fourchettes de Prix
        </router-link>
        <router-link to="/admin/utilisateurs" class="lien-menu" :class="{ actif: route.path === '/admin/utilisateurs' }">
          <img src="/icons/utilisateur/users-alt.png" alt="" class="icone-menu" /> Voyageurs & Utilisateurs
        </router-link>
        <!-- Lien Notifications avec badge -->
        <router-link to="/admin/notifications" class="lien-menu lien-menu-notif" :class="{ actif: route.path === '/admin/notifications' }">
          <span><img src="/icons/message/megaphone.png" alt="" class="icone-menu" /> Notifications</span>
          <span v-if="nbNotifNonLues > 0" class="badge-notif-menu">{{ nbNotifNonLues }}</span>
        </router-link>
      </nav>

      <div class="bas-menu">
        <div class="profil-utilisateur mb-2">
          <div class="nom">{{ auth.utilisateur?.email || auth.user?.email }}</div>
        </div>
        <button @click="seDeconnecter" class="btn btn-outline-secondary btn-sm w-100 rounded-pill">
          Déconnexion
        </button>
      </div>
    </aside>

    <!-- Zone principale -->
    <div class="contenu-principal">
      <header class="barre-superieure">
        <h4 class="fw-bold mb-0">{{ title }}</h4>
        <div class="d-flex align-items-center gap-3">
          <slot name="actions"></slot>

          <!-- Cloche de notification -->
          <div class="position-relative">
            <button
              @click="ouvrirNotifications"
              class="btn-cloche"
              :class="{ actif: pannNotifOuvert }"
              title="Notifications"
            >
              <img src="/icons/message/megaphone.png" alt="Notifications" class="icone-notification" />
              <span v-if="nbNotifNonLues > 0" class="badge-cloche">{{ nbNotifNonLues }}</span>
            </button>

            <!-- Panneau déroulant notifications -->
            <div v-if="pannNotifOuvert" class="panneau-notif" @click.stop>
              <div class="panneau-notif-entete">
                <span class="fw-bold fs-6" style="color: #13385C;"><img src="/icons/message/megaphone.png" alt="" class="icone-notification" /> Notifications</span>
                <router-link to="/admin/notifications" @click="pannNotifOuvert = false" class="btn btn-sm btn-link p-0 d-inline-flex align-items-center gap-1" style="font-size:0.8rem; color: #DB5323; text-decoration: none; font-weight: 700;">
                  <span>Voir tout</span>
                  <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite" style="width: 13px; height: 13px;" />
                </router-link>
              </div>

              <div v-if="dernieresNotifs.length === 0" class="text-center text-muted py-3 small">
                Aucune notification
              </div>

              <div v-else class="panneau-notif-liste">
                <div
                  v-for="n in dernieresNotifs"
                  :key="n.id"
                  class="panneau-notif-item"
                >
                  <div class="d-flex align-items-start gap-2">
                    <img :src="iconeType(n.type)" alt="" class="icone-notification" />
                    <div style="min-width:0">
                      <div class="small fw-semibold text-truncate" style="max-width:200px; color: #13385C;" :title="n.message">
                        {{ n.message }}
                      </div>
                      <div class="text-muted" style="font-size:0.72rem">{{ formaterDateCourte(n.date_creation) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Fermer le panneau si clic ailleurs -->
      <div v-if="pannNotifOuvert" class="rideau-notif" @click="pannNotifOuvert = false"></div>

      <main class="p-4">
        <slot></slot>
      </main>

      <!-- Pied de page Administration -->
      <footer class="pied-page-admin">
        <div class="d-flex align-items-center gap-2">
          <img src="/logo-dia.png" alt="DIA Logo" style="height: 28px; width: auto;" />
          <span class="fw-bold" style="color: #13385C;">DIA TaxiBrousse</span>
        </div>
        <div>
          &copy; {{ new Date().getFullYear() }} DIA TaxiBrousse — Système de Gestion & Flotte
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/assets/css/admin/layout.css";
@import "@/assets/css/admin/modales.css";

/* === Lien Notifications dans la sidebar === */
.lien-menu-notif {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.icone-menu {
  width: 20px;
  height: 20px;
  object-fit: contain;
  vertical-align: middle;
  margin-right: 0.85rem;
  flex: 0 0 auto;
  filter: brightness(0) invert(1) !important;
  opacity: 0.95 !important;
}
.icone-notification {
  width: 17px;
  height: 17px;
  object-fit: contain;
  vertical-align: middle;
  margin-right: 0.5rem;
  flex: 0 0 auto;
  opacity: 0.9;
}
.badge-notif-menu {
  background-color: #DB5323;
  color: #fff;
  border-radius: 9999px;
  padding: 0.1rem 0.45rem;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.4;
}

/* === Cloche dans le header === */
.btn-cloche {
  position: relative;
  background: #ffffff;
  border: 1px solid #C0C0BF;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-cloche:hover,
.btn-cloche.actif {
  background: #FFE7A8;
  border-color: #DB5323;
}
.badge-cloche {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #DB5323;
  color: #fff;
  border-radius: 9999px;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  font-size: 0.65rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #fff;
}

/* === Panneau déroulant notifications === */
.panneau-notif {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 320px;
  background: #ffffff;
  border: 1px solid #C0C0BF;
  border-radius: 14px;
  box-shadow: 0 10px 30px rgba(19, 56, 92, 0.15);
  z-index: 2000;
  overflow: hidden;
}
.panneau-notif-entete {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.85rem 1rem 0.65rem;
  border-bottom: 1px solid #C0C0BF;
}
.panneau-notif-liste {
  max-height: 310px;
  overflow-y: auto;
}
.panneau-notif-item {
  padding: 0.65rem 1rem;
  border-bottom: 1px solid #C0C0BF;
  transition: background 0.15s;
}
.panneau-notif-item:last-child { border-bottom: none; }
.panneau-notif-item:hover { background: rgba(255, 231, 168, 0.2); }

/* Rideau transparent pour fermer le panneau */
.rideau-notif {
  position: fixed;
  inset: 0;
  z-index: 1999;
}
</style>
