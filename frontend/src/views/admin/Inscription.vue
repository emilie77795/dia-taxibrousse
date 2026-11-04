<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routeur = useRouter()
const auth = useAuthStore()

const formulaire = ref({
  nom: '',
  prenom: '',
  telephone: '',
  email: '',
  lieu: 'Direction Générale',
  mot_de_passe: '',
})

const erreur = ref('')
const succes = ref('')
const chargement = ref(false)

async function sinscrireAdmin() {
  erreur.value = ''
  succes.value = ''
  chargement.value = true
  try {
    await auth.inscription({ ...formulaire.value, role: 'administrateur' })
    succes.value = 'Compte administrateur créé avec succès ! Redirection...'
    setTimeout(() => routeur.push('/admin/login'), 1500)
  } catch (err) {
    erreur.value = err.response?.data?.error || 'Erreur lors de la création du compte.'
  } finally {
    chargement.value = false
  }
}
</script>

<template>
  <div class="page-auth">
    <div class="carte-auth">
      <!-- En-tête avec logo et flèche retour -->
      <div class="haut-auth">
        <router-link to="/admin/login" class="bouton-retour" title="Retour à la connexion admin">
          <img src="/icons/autres/caret-down.png" alt="Retour" class="icone-caret icone-caret-gauche" />
        </router-link>
        <img src="/logo.png" alt="DIA Logo" class="logo-auth" />
      </div>

      <!-- Formulaire fond blanc -->
      <div class="bas-auth">
        <div class="entete-auth">
          <h1 class="titre-auth">Inscription Admin</h1>
          <p class="sous-titre-auth">Création Compte Gestionnaire</p>
        </div>

        <form @submit.prevent="sinscrireAdmin" class="formulaire-auth">
          <div class="ligne-deux-colonnes">
            <div class="champ-groupe">
              <label class="champ-label" for="admin-prenom">Prénom</label>
              <input
                id="admin-prenom"
                v-model="formulaire.prenom"
                type="text"
                class="champ-saisie"
                placeholder="Tojo"
                required
              />
            </div>
            <div class="champ-groupe">
              <label class="champ-label" for="admin-nom">Nom</label>
              <input
                id="admin-nom"
                v-model="formulaire.nom"
                type="text"
                class="champ-saisie"
                placeholder="Rabe"
                required
              />
            </div>
          </div>

          <div class="champ-groupe">
            <label class="champ-label" for="admin-reg-email">Email Admin</label>
            <input
              id="admin-reg-email"
              v-model="formulaire.email"
              type="email"
              class="champ-saisie"
              placeholder="admin@dia.mg"
              required
              autocomplete="email"
            />
          </div>

          <div class="ligne-deux-colonnes">
            <div class="champ-groupe">
              <label class="champ-label" for="admin-reg-tel">Téléphone</label>
              <input
                id="admin-reg-tel"
                v-model="formulaire.telephone"
                type="text"
                class="champ-saisie"
                placeholder="034 55 666 77"
                required
              />
            </div>
            <div class="champ-groupe">
              <label class="champ-label" for="admin-reg-lieu">Département</label>
              <input
                id="admin-reg-lieu"
                v-model="formulaire.lieu"
                type="text"
                class="champ-saisie"
                placeholder="Siège Antananarivo"
              />
            </div>
          </div>

          <div class="champ-groupe">
            <label class="champ-label" for="admin-reg-password">Mot de passe</label>
            <input
              id="admin-reg-password"
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
            <span v-else>Créer le compte Administrateur</span>
          </button>
        </form>

        <div class="lien-bascule">
          <span>Déjà administrateur ? </span>
          <router-link to="/admin/login">Se connecter ici</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import "@/assets/css/commun/auth.css";
</style>
