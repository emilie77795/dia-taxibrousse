import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routeur = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      alias: '/client/dashboard',
      name: 'accueil',
      component: () => import('@/views/client/Accueil.vue'),
    },

    {
      path: '/client/trajets',
      name: 'client-trajets',
      component: () => import('@/views/client/Trajets.vue'),
    },
    {
      path: '/client/tarifs',
      name: 'client-tarifs',
      component: () => import('@/views/client/Tarifs.vue'),
    },
    {
      path: '/client/reservation',
      redirect: '/client/trajets',
    },
    {
      path: '/client/places/:id',
      name: 'client-places',
      component: () => import('@/views/client/Places.vue'),
    },
    {
      path: '/client/reservation/places/:id',
      redirect: to => `/client/places/${to.params.id}`,
    },

    // =========================================================================
    // 3. AUTHENTIFICATION VOYAGEUR (DÉCLENCHÉE AU MOMENT DU PAIEMENT)
    // =========================================================================
    {
      path: '/client/login',
      name: 'client-login',
      component: () => import('@/views/client/Connexion.vue'),
      meta: { invite: true },
    },
    {
      path: '/client/register',
      name: 'client-register',
      component: () => import('@/views/client/Inscription.vue'),
      meta: { invite: true },
    },

    // =========================================================================
    // 4. ESPACE PERSONNEL VOYAGEUR (NÉCESSITE CONNEXION)
    // =========================================================================
    {
      path: '/client/mes-reservations',
      name: 'client-mes-reservations',
      component: () => import('@/views/client/MesReservations.vue'),
      meta: { necessiteConnexion: true },
    },
    {
      path: '/client/ticket/:id',
      name: 'client-ticket',
      component: () => import('@/views/client/Ticket.vue'),
      meta: { necessiteConnexion: true },
    },
    {
      path: '/client/reporter/:id',
      name: 'client-reporter',
      component: () => import('@/views/client/Reporter.vue'),
      meta: { necessiteConnexion: true },
    },
    {
      path: '/client/notifications',
      name: 'client-notifications',
      component: () => import('@/views/client/Notifications.vue'),
      meta: { necessiteConnexion: true },
    },

    // ---------------- ESPACE ADMINISTRATEUR ----------------
    {
      path: '/admin/login',
      name: 'admin-login',
      component: () => import('@/views/admin/Connexion.vue'),
      meta: { invite: true },
    },
    {
      path: '/admin/register',
      name: 'admin-register',
      component: () => import('@/views/admin/Inscription.vue'),
      meta: { invite: true },
    },
    {
      path: '/admin/dashboard',
      name: 'admin-dashboard',
      component: () => import('@/views/admin/Tableau.vue'),
      meta: { necessiteConnexion: true, necessiteAdmin: true },
    },
    {
      path: '/admin/guichet',
      name: 'admin-guichet',
      component: () => import('@/views/admin/Guichet.vue'),
      meta: { necessiteConnexion: true, necessiteAdmin: true },
    },
    {
      path: '/admin/checkpoint',
      name: 'admin-checkpoint',
      component: () => import('@/views/admin/Checkpoint.vue'),
      meta: { necessiteConnexion: true, necessiteAdmin: true },
    },
    {
      path: '/admin/trajets',
      name: 'admin-trajets',
      component: () => import('@/views/admin/Trajets.vue'),
      meta: { necessiteConnexion: true, necessiteAdmin: true },
    },
    {
      path: '/admin/fourchettes',
      name: 'admin-fourchettes',
      component: () => import('@/views/admin/Tarifs.vue'),
      meta: { necessiteConnexion: true, necessiteAdmin: true },
    },
    {
      path: '/admin/flotte',
      name: 'admin-flotte',
      component: () => import('@/views/admin/Flotte.vue'),
      meta: { necessiteConnexion: true, necessiteAdmin: true },
    },
    {
      path: '/admin/reservations',
      name: 'admin-reservations',
      component: () => import('@/views/admin/Reservations.vue'),
      meta: { necessiteConnexion: true, necessiteAdmin: true },
    },
    {
      path: '/admin/utilisateurs',
      name: 'admin-utilisateurs',
      component: () => import('@/views/admin/Utilisateurs.vue'),
      meta: { necessiteConnexion: true, necessiteAdmin: true },
    },
    {
      path: '/admin/notifications',
      name: 'admin-notifications',
      component: () => import('@/views/admin/Notifications.vue'),
      meta: { necessiteConnexion: true, necessiteAdmin: true },
    },
    {
      path: '/admin/notifications/nouveau',
      name: 'admin-notifications-nouveau',
      component: () => import('@/views/admin/EnvoyerNotification.vue'),
      meta: { necessiteConnexion: true, necessiteAdmin: true },
    },

    // ---------------- REDIRECTIONS UTILES ----------------
    { path: '/login', redirect: '/client/login' },
    { path: '/register', redirect: '/client/register' },
    { path: '/admin', redirect: '/admin/login' },
    {
      path: '/dashboard',
      redirect: () => {
        const auth = useAuthStore()
        return auth.estAdmin ? '/admin/dashboard' : '/'
      },
    },
  ],
})

// Protection des pages selon la connexion et les rôles
routeur.beforeEach((cible) => {
  const auth = useAuthStore()

  // Page privée client mais utilisateur non connecté → vers le login client
  if (cible.meta.necessiteConnexion && !cible.meta.necessiteAdmin && !auth.estConnecte) {
    return '/client/login'
  }

  // Page privée admin mais utilisateur non connecté → vers le login admin
  if (cible.meta.necessiteAdmin && !auth.estConnecte) {
    return '/admin/login'
  }

  // Page admin demandée par un voyageur standard → vers l'accueil
  if (cible.meta.necessiteAdmin && !auth.estAdmin) {
    return '/'
  }

  // Page login/inscription CLIENT alors qu'un voyageur est déjà connecté → vers accueil
  if (cible.meta.invite && cible.path.startsWith('/client/') && auth.estConnecte && !auth.estAdmin) {
    return '/'
  }

  // Page login/inscription ADMIN alors qu'un admin est déjà connecté → vers dashboard admin
  if (cible.meta.invite && cible.path.startsWith('/admin/') && auth.estConnecte && auth.estAdmin) {
    return '/admin/dashboard'
  }
})

// Maintien strict du thème sombre/clair et de la langue à chaque changement de page
routeur.afterEach(() => {
  if (typeof document !== 'undefined') {
    const estSombre = localStorage.getItem('dia_theme') === 'dark'
    if (estSombre) {
      document.documentElement.classList.add('mode-sombre')
      document.body.classList.add('mode-sombre')
    } else {
      document.documentElement.classList.remove('mode-sombre')
      document.body.classList.remove('mode-sombre')
    }

    const langue = localStorage.getItem('dia_langue') || 'fr'
    document.documentElement.lang = langue
  }
})

export default routeur

