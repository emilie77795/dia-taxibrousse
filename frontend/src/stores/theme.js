import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  // Récupération initiale depuis le stockage local (par défaut 'light')
  const themeInitial = (typeof window !== 'undefined' ? localStorage.getItem('dia_theme') : 'light') || 'light'
  const modeSombre = ref(themeInitial === 'dark')

  function appliquerDOM(sombre) {
    if (typeof document !== 'undefined') {
      if (sombre) {
        document.documentElement.classList.add('mode-sombre')
        document.body.classList.add('mode-sombre')
      } else {
        document.documentElement.classList.remove('mode-sombre')
        document.body.classList.remove('mode-sombre')
      }
    }
  }

  // Appliquer immédiatement
  appliquerDOM(modeSombre.value)

  function basculerTheme() {
    modeSombre.value = !modeSombre.value
    const val = modeSombre.value ? 'dark' : 'light'
    try {
      localStorage.setItem('dia_theme', val)
    } catch (e) {}
    appliquerDOM(modeSombre.value)
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('dia:theme-change', { detail: val }))
    }
  }

  function setModeSombre(valeur) {
    modeSombre.value = Boolean(valeur)
    const val = modeSombre.value ? 'dark' : 'light'
    try {
      localStorage.setItem('dia_theme', val)
    } catch (e) {}
    appliquerDOM(modeSombre.value)
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('dia:theme-change', { detail: val }))
    }
  }

  function synchroniser() {
    try {
      const stocke = localStorage.getItem('dia_theme') === 'dark'
      if (modeSombre.value !== stocke) {
        modeSombre.value = stocke
      }
      appliquerDOM(modeSombre.value)
    } catch (e) {}
  }

  return {
    modeSombre,
    basculerTheme,
    setModeSombre,
    synchroniser,
    appliquerDOM
  }
})
