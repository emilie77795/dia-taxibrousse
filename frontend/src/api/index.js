import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8000',
  headers: { 'Content-Type': 'application/json' },
})

// Attache automatiquement le token JWT à chaque requête
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Si le token est expiré ou non valide
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')

      const url = error.config?.url || ''
      const chemin = window.location.pathname
      const estRequeteLogin = url.includes('/api/login')
      const estPageAuth = chemin.includes('/login') || chemin.includes('/register')
      const estPagePublique = chemin === '/' 
        || chemin === '/client/dashboard'
        || chemin.startsWith('/client/trajets')
        || chemin.startsWith('/client/tarifs')
        || chemin.startsWith('/client/places')

      // Ne rediriger que si ce n'est PAS une tentative de login ou une page d'authentification
      if (!estRequeteLogin && !estPageAuth && !estPagePublique) {
        if (chemin.startsWith('/admin')) {
          window.location.href = '/admin/login'
        } else {
          window.location.href = '/client/login'
        }
      }
    }
    return Promise.reject(error)
  },
)

export default api
