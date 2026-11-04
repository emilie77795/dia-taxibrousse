<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import { t, langueCourante, changerLangue } from '@/i18n'
import api from '@/api'

const props = defineProps({
  modeSombre: {
    type: Boolean,
    default: undefined
  }
})

const emit = defineEmits(['update:modeSombre', 'basculer-theme'])

const routeur = useRouter()
const route = useRoute()
const auth = useAuthStore()
const themeStore = useThemeStore()

// Notifications
const nbNotifsNonLues = ref(0)

async function chargerNbNotifs() {
  if (!auth.estConnecte || auth.estAdmin) return
  if (route.path.startsWith('/client/notifications')) {
    nbNotifsNonLues.value = 0
    return
  }
  try {
    const { data } = await api.get('/api/notifications')
    nbNotifsNonLues.value = (data || []).filter(n => n.statut === 'non_lu').length
  } catch (e) {}
}

async function consulterNotifications() {
  nbNotifsNonLues.value = 0
  try {
    await api.post('/api/notifications/lire')
  } catch (e) {
    console.error('Erreur marquage notifications lues', e)
  }
  window.dispatchEvent(new CustomEvent('dia:notifications-lues'))
}

function onNotifsLues() {
  nbNotifsNonLues.value = 0
}

const estSombre = computed(() => themeStore.modeSombre)

function basculerTheme() {
  themeStore.basculerTheme()
  emit('basculer-theme', themeStore.modeSombre)
  emit('update:modeSombre', themeStore.modeSombre)
}

function seDeconnecter() {
  auth.deconnexion()
  routeur.push('/')
}

watch(() => route.path, (nouvelleRoute) => {
  if (nouvelleRoute.startsWith('/client/notifications')) {
    nbNotifsNonLues.value = 0
  }
})

onMounted(() => {
  themeStore.synchroniser()
  chargerNbNotifs()
  window.addEventListener('dia:notifications-lues', onNotifsLues)
})

onUnmounted(() => {
  window.removeEventListener('dia:notifications-lues', onNotifsLues)
})
</script>

<template>
  <header class="barre-navigation">
    <div class="container-xl d-flex justify-content-between align-items-center">
      <!-- Logo DIA : Adaptatif mode sombre / clair -->
      <router-link to="/" class="logo-conteneur">
        <img 
          :src="estSombre ? '/logo-dia-dark.png' : '/logo-dia.png'" 
          alt="DIA Logo" 
          class="logo-image" 
        />
      </router-link>

      <!-- Liens principaux au centre -->
      <nav class="liens-navigation d-none d-lg-flex">
        <router-link 
          to="/" 
          class="lien-nav" 
          :class="{ actif: route.path === '/' || route.path === '/client/dashboard' }"
        >
          {{ t('nav_accueil') }}
        </router-link>

        <router-link 
          to="/client/trajets" 
          class="lien-nav" 
          :class="{ actif: route.path.startsWith('/client/trajets') || route.path.startsWith('/client/places') }"
        >
          {{ t('nav_trajets') }}
        </router-link>
        
        <router-link 
          to="/client/tarifs" 
          class="lien-nav" 
          :class="{ actif: route.path === '/client/tarifs' }"
        >
          {{ t('nav_tarifs') }}
        </router-link>

        <router-link 
          to="/client/mes-reservations" 
          class="lien-nav" 
          :class="{ actif: route.path.startsWith('/client/mes-reservations') || route.path.startsWith('/client/ticket') || route.path.startsWith('/client/reporter') }"
        >
          {{ t('nav_billets') }}
        </router-link>

        <a href="/#services" class="lien-nav">
          {{ t('nav_services') }}
        </a>
      </nav>

      <!-- Actions à droite : Langue, Dark/Light, Téléphone, CTA / Déconnexion -->
      <div class="d-flex align-items-center gap-2 gap-sm-3">
        <!-- Translateur de Langue (Français / Anglais) -->
        <button 
          @click="changerLangue(langueCourante === 'fr' ? 'en' : 'fr')" 
          class="bouton-theme bouton-langue" 
          :title="langueCourante === 'fr' ? 'Switch to English' : 'Passer en Français'"
        >
          <img src="/icons/autres/globe.png" alt="Langue" class="icone-auto" />
          <span class="fw-bold">{{ langueCourante === 'fr' ? 'EN' : 'FR' }}</span>
        </button>

        <!-- Bascule Mode Sombre / Clair -->
        <button 
          @click="basculerTheme" 
          class="bouton-theme" 
          :title="estSombre ? 'Mode Clair' : 'Mode Sombre'"
        >
          <img :src="estSombre ? '/icons/autres/sun.png' : '/icons/autres/moon.png'" alt="Theme" class="icone-auto" />
          <span class="d-none d-sm-inline">{{ estSombre ? t('nav_mode_clair') : t('nav_mode_sombre') }}</span>
        </button>

        <a href="tel:+261340012345" class="bouton-agence-nav d-none d-md-inline-flex" :title="t('nav_appeler_agence')">
          <img src="/icons/message/phone-call.png" alt="Téléphone" class="icone-auto" style="width: 18px; height: 18px;" />
          <span>+261 34 00 123 45</span>
        </a>

        <!-- Si connecté : Déconnexion -->
        <template v-if="auth.estConnecte">
          <!-- Cloche de notifications -->
          <router-link
            to="/client/notifications"
            class="bouton-notif-cloche position-relative"
            title="Mes notifications"
            @click="consulterNotifications"
          >
            <img src="/icons/message/megaphone.png" alt="Notifications" class="icone-auto" style="width:20px;height:20px;" />
            <span
              v-if="nbNotifsNonLues > 0 && !route.path.startsWith('/client/notifications')"
              class="badge-notif-nav"
            >{{ nbNotifsNonLues > 9 ? '9+' : nbNotifsNonLues }}</span>
          </router-link>

          <router-link to="/client/mes-reservations" class="btn btn-outline-primary d-none d-sm-inline-flex align-items-center gap-2">
            <img src="/icons/utilisateur/user.png" alt="User" class="icone-auto" style="width: 16px; height: 16px;" />
            <span>{{ t('nav_mon_compte') }}</span>
          </router-link>

          <button @click="seDeconnecter" class="btn btn-outline-danger px-3" :title="t('nav_deconnexion')">
            <img src="/icons/autres/sign-out-alt.png" alt="Deconnexion" class="icone-auto" />
          </button>
        </template>

        <!-- Si non connecté : Bouton Se connecter -->
        <template v-else>
          <router-link 
            :to="{ path: '/client/login', query: route.path.startsWith('/client/places') ? { redirection: route.fullPath } : {} }" 
            class="btn btn-primary fw-bold"
          >
            {{ t('nav_connexion') }}
          </router-link>
        </template>
      </div>
    </div>
  </header>
</template>

<style>
@import "@/assets/css/client/base.css";
@import "@/assets/css/client/navbar.css";

/* Cloche de notification dans la navbar */
.bouton-notif-cloche {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: rgba(19, 56, 92, 0.08);
  border: 1.5px solid rgba(19, 56, 92, 0.18);
  cursor: pointer;
  transition: background 0.2s;
  text-decoration: none;
}
.bouton-notif-cloche:hover {
  background: rgba(219, 83, 35, 0.12);
  border-color: #DB5323;
}
.badge-notif-nav {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #DC2626;
  color: #fff;
  font-size: 0.6rem;
  font-weight: 700;
  min-width: 17px;
  height: 17px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 3px;
  border: 2px solid #fff;
  line-height: 1;
}
</style>
