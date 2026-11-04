<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routeur = useRouter()
const auth = useAuthStore()

const email = ref('admin@dia.mg')
const motDePasse = ref('1230')
const erreur = ref('')
const chargement = ref(false)

async function seConnecterAdmin() {
  erreur.value = ''
  chargement.value = true
  try {
    await auth.connexion(email.value, motDePasse.value)
    if (auth.estAdmin) {
      routeur.push('/admin/dashboard')
    } else {
      erreur.value = 'Accès refusé. Ce compte ne possède pas les privilèges administrateur.'
      auth.deconnexion()
    }
  } catch (err) {
    erreur.value = err.response?.data?.message || 'Identifiants administrateur incorrects.'
  } finally {
    chargement.value = false
  }
}
</script>

<template>
  <div class="page-auth">
    <div class="carte-auth">
      <div class="haut-auth">
        <router-link to="/client/dashboard" class="bouton-retour" title="Retour au site voyageur">
          <img src="/icons/autres/caret-down.png" alt="Retour" class="icone-caret icone-caret-gauche" />
        </router-link>
        <img src="/logo.png" alt="DIA Logo" class="logo-auth" />
      </div>

      <div class="bas-auth">
        <div class="entete-auth">
          <h1 class="titre-auth">Console Admin</h1>
          <p class="sous-titre-auth">Gestion DIA TaxiBrousse</p>
        </div>

        <form @submit.prevent="seConnecterAdmin" class="formulaire-auth">
          <div class="champ-groupe">
            <label class="champ-label" for="admin-email">Email administrateur</label>
            <input
              id="admin-email"
              v-model="email"
              type="email"
              class="champ-saisie"
              placeholder="admin@dia.mg"
              required
              autocomplete="email"
            />
          </div>

          <div class="champ-groupe">
            <label class="champ-label" for="admin-password">Mot de passe</label>
            <input
              id="admin-password"
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
            <span v-else>Connexion Administrateur</span>
          </button>
        </form>

        <div class="lien-bascule">
          <span>Nouveau gestionnaire ? </span>
          <router-link to="/admin/register">Créer un compte</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import "@/assets/css/commun/auth.css";
</style>
