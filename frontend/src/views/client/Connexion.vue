<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import { t } from '@/i18n'

const routeur = useRouter()
const route = useRoute()
const auth = useAuthStore()
const themeStore = useThemeStore()

const email = ref('')
const motDePasse = ref('')
const erreur = ref('')
const chargement = ref(false)

function determinerRedirection() {
  if (route.query.redirection && route.query.redirection !== '/') {
    return route.query.redirection
  }
  const enCours = sessionStorage.getItem('dia_reservation_en_cours')
  if (enCours) {
    try {
      const data = JSON.parse(enCours)
      if (data.id_trajet) {
        return `/client/places/${data.id_trajet}`
      }
    } catch (e) {}
  }
  const dernierTrajet = sessionStorage.getItem('dia_dernier_trajet_id')
  if (dernierTrajet) {
    return `/client/places/${dernierTrajet}`
  }
  return '/client/trajets'
}

async function seConnecter() {
  erreur.value = ''
  chargement.value = true
  try {
    await auth.connexion(email.value, motDePasse.value)
    if (auth.estAdmin) {
      erreur.value = 'Accès refusé. Ce formulaire est réservé uniquement aux voyageurs.'
      auth.deconnexion()
    } else {
      routeur.push(determinerRedirection())
    }
  } catch (err) {
    erreur.value = err.response?.data?.message || 'Identifiants incorrects.'
  } finally {
    chargement.value = false
  }
}
</script>

<template>
  <div class="page-auth" :class="{ 'mode-sombre': themeStore.modeSombre }">
    <div class="carte-auth">
      <div class="haut-auth">
        <router-link to="/" class="bouton-retour" :title="t('login_retour_accueil')">
          <img src="/icons/autres/caret-down.png" alt="Retour" class="icone-caret icone-caret-gauche" />
        </router-link>
        <img :src="themeStore.modeSombre ? '/logo-dia-dark.png' : '/logo.png'" alt="DIA Logo" class="logo-auth" />
      </div>

      <div class="bas-auth">
        <div class="entete-auth">
          <h1 class="titre-auth">{{ t('login_titre') }}</h1>
          <p class="sous-titre-auth">{{ t('login_sous_titre') }}</p>
        </div>

        <form @submit.prevent="seConnecter" class="formulaire-auth">
          <div class="champ-groupe">
            <label class="champ-label" for="login-email">{{ t('login_email') }}</label>
            <input
              id="login-email"
              v-model="email"
              type="email"
              class="champ-saisie"
              :placeholder="t('login_email_placeholder')"
              required
              autocomplete="email"
            />
          </div>

          <div class="champ-groupe">
            <label class="champ-label" for="login-password">{{ t('login_password') }}</label>
            <input
              id="login-password"
              v-model="motDePasse"
              type="password"
              class="champ-saisie"
              placeholder="••••••••••••"
              required
              autocomplete="current-password"
            />
          </div>

          <div v-if="erreur" class="boite-erreur">
            <span>{{ erreur }}</span>
          </div>

          <button type="submit" class="bouton-action" :disabled="chargement">
            <span v-if="chargement" class="spinner-border spinner-border-sm me-2"></span>
            <span v-else>{{ t('login_btn') }}</span>
          </button>
        </form>

        <div class="lien-bascule">
          <span>{{ t('login_pas_de_compte') }} </span>
          <router-link :to="{ path: '/client/register', query: route.query }">{{ t('login_sinscrire') }}</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import "@/assets/css/commun/auth.css";
</style>
