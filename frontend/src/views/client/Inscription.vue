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

const formulaire = ref({
  nom: '',
  prenom: '',
  telephone: '',
  email: '',
  lieu: '',
  mot_de_passe: '',
})

const erreur = ref('')
const succes = ref('')
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

async function sinscrire() {
  erreur.value = ''
  succes.value = ''
  chargement.value = true
  try {
    await auth.inscription({ ...formulaire.value, role: 'voyageur' })
    // Connexion automatique immédiate après la création de compte
    await auth.connexion(formulaire.value.email, formulaire.value.mot_de_passe)
    succes.value = 'Compte voyageur créé avec succès ! Redirection...'
    setTimeout(() => {
      routeur.push(determinerRedirection())
    }, 600)
  } catch (err) {
    erreur.value = err.response?.data?.error || "Erreur lors de l'inscription."
  } finally {
    chargement.value = false
  }
}
</script>

<template>
  <div class="page-auth" :class="{ 'mode-sombre': themeStore.modeSombre }">
    <div class="carte-auth">
      <!-- En-tête avec logo et flèche retour -->
      <div class="haut-auth">
        <router-link :to="{ path: '/client/login', query: route.query }" class="bouton-retour" :title="t('register_se_connecter')">
          <img src="/icons/autres/caret-down.png" alt="Retour" class="icone-caret icone-caret-gauche" />
        </router-link>
        <img :src="themeStore.modeSombre ? '/logo-dia-dark.png' : '/logo.png'" alt="DIA Logo" class="logo-auth" />
      </div>

      <!-- Formulaire fond blanc -->
      <div class="bas-auth">
        <div class="entete-auth">
          <h1 class="titre-auth">{{ t('register_titre') }}</h1>
          <p class="sous-titre-auth">{{ t('register_sous_titre') }}</p>
        </div>

        <form @submit.prevent="sinscrire" class="formulaire-auth">
          <div class="ligne-deux-colonnes">
            <div class="champ-groupe">
              <label class="champ-label" for="reg-prenom">{{ t('register_prenom') }}</label>
              <input
                id="reg-prenom"
                v-model="formulaire.prenom"
                type="text"
                class="champ-saisie"
                placeholder="Fanja"
                required
              />
            </div>
            <div class="champ-groupe">
              <label class="champ-label" for="reg-nom">{{ t('register_nom') }}</label>
              <input
                id="reg-nom"
                v-model="formulaire.nom"
                type="text"
                class="champ-saisie"
                placeholder="Andria"
                required
              />
            </div>
          </div>

          <div class="champ-groupe">
            <label class="champ-label" for="reg-email">{{ t('login_email') }}</label>
            <input
              id="reg-email"
              v-model="formulaire.email"
              type="email"
              class="champ-saisie"
              :placeholder="t('login_email_placeholder')"
              required
              autocomplete="email"
            />
          </div>

          <div class="ligne-deux-colonnes">
            <div class="champ-groupe">
              <label class="champ-label" for="reg-tel">{{ t('register_tel') }}</label>
              <input
                id="reg-tel"
                v-model="formulaire.telephone"
                type="text"
                class="champ-saisie"
                placeholder="034 12 345 67"
                required
              />
            </div>
            <div class="champ-groupe">
              <label class="champ-label" for="reg-lieu">{{ t('register_ville') }}</label>
              <input
                id="reg-lieu"
                v-model="formulaire.lieu"
                type="text"
                class="champ-saisie"
                placeholder="Antananarivo"
              />
            </div>
          </div>

          <div class="champ-groupe">
            <label class="champ-label" for="reg-password">{{ t('login_password') }}</label>
            <input
              id="reg-password"
              v-model="formulaire.mot_de_passe"
              type="password"
              class="champ-saisie"
              placeholder="••••••••••••"
              required
              autocomplete="new-password"
            />
          </div>

          <div v-if="erreur" class="boite-erreur">
            <span>{{ erreur }}</span>
          </div>
          <div v-if="succes" class="boite-succes">
            <span>{{ succes }}</span>
          </div>

          <button type="submit" class="bouton-action" :disabled="chargement">
            <span v-if="chargement" class="spinner-border spinner-border-sm me-2"></span>
            <span v-else>{{ t('register_btn') }}</span>
          </button>
        </form>

        <div class="lien-bascule">
          <span>{{ t('register_deja_compte') }} </span>
          <router-link :to="{ path: '/client/login', query: route.query }">{{ t('register_se_connecter') }}</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import "@/assets/css/commun/auth.css";
</style>
