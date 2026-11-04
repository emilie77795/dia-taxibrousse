import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api'

export const useAuthStore = defineStore('auth', () => {
  // Données de session stockées dans le navigateur
  const jeton = ref(localStorage.getItem('token') || null)
  const utilisateur = ref(JSON.parse(localStorage.getItem('user') || 'null'))

  // Vérification de connexion et des droits administrateur
  const estConnecte = computed(() => !!jeton.value)
  const estAdmin = computed(() => {
    if (!utilisateur.value) return false
    const role = utilisateur.value.role
    const roles = utilisateur.value.roles || (role ? [role] : [])
    return roles.includes('ROLE_ADMIN') || ['administrateur', 'admin', 'super_admin'].includes(role)
  })

  // Connexion utilisateur (voyageur ou administrateur)
  async function connexion(email, motDePasse) {
    const { data } = await api.post('/api/login', { email, mot_de_passe: motDePasse })
    jeton.value = data.token
    localStorage.setItem('token', data.token)

    // Lecture du rôle depuis le jeton JWT
    const payload = JSON.parse(atob(data.token.split('.')[1]))
    const roles = payload.roles || (payload.role ? [payload.role] : [])
    const roleAdmin = roles.includes('ROLE_ADMIN') || ['administrateur', 'admin', 'super_admin'].includes(payload.role)

    utilisateur.value = {
      email: payload.username,
      roles: roles,
      role: roleAdmin ? 'administrateur' : 'voyageur',
    }
    localStorage.setItem('user', JSON.stringify(utilisateur.value))
  }

  async function inscription(donnees) {
    const { data } = await api.post('/api/register', donnees)
    return data
  }

  function deconnexion() {
    jeton.value = null
    utilisateur.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('user')
  }

  return {
    jeton,
    utilisateur,
    estConnecte,
    estAdmin,
    connexion,
    inscription,
    deconnexion,

    token: jeton,
    user: utilisateur,
    isLoggedIn: estConnecte,
    isAdmin: estAdmin,
    login: connexion,
    register: inscription,
    logout: deconnexion,
  }
})
