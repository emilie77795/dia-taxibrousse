<script setup>
import { onMounted, onUnmounted } from 'vue'
import { RouterView } from 'vue-router'

function appliquerThemeGlobal() {
  const theme = localStorage.getItem('dia_theme') || 'light'
  const estSombre = theme === 'dark'
  if (estSombre) {
    document.documentElement.classList.add('mode-sombre')
    document.body.classList.add('mode-sombre')
  } else {
    document.documentElement.classList.remove('mode-sombre')
    document.body.classList.remove('mode-sombre')
  }
}

// Appliquer immédiatement pour éviter tout flash blanc
if (typeof window !== 'undefined') {
  appliquerThemeGlobal()
}

onMounted(() => {
  appliquerThemeGlobal()
  window.addEventListener('dia:theme-change', appliquerThemeGlobal)
  window.addEventListener('storage', appliquerThemeGlobal)
})

onUnmounted(() => {
  window.removeEventListener('dia:theme-change', appliquerThemeGlobal)
  window.removeEventListener('storage', appliquerThemeGlobal)
})
</script>

<template>
  <RouterView />
</template>

<style>
/* Reset & typographie moderne (style Figma / Stripe / Linear) */
:root {
  --color-dia-blue: #13385C;
  --color-dia-orange: #DB5323;
  --color-dia-cream: #FFE7A8;
  --color-dia-silver: #C0C0BF;
  --color-dia-slate: #0f172a;
  
  --font-main: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  --font-title: 'Figtree', -apple-system, BlinkMacSystemFont, sans-serif;
  --font-body: 'Figtree', -apple-system, BlinkMacSystemFont, sans-serif;
}

*, *::before, *::after {
  box-sizing: border-box;
}

body {
  margin: 0;
  padding: 0;
  font-family: var(--font-main);
  font-weight: 400;
  background-color: #f8fafc;
  color: #0f172a;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  line-height: 1.5;
  transition: background-color 0.25s ease, color 0.25s ease;
}

body.mode-sombre {
  background-color: #0b131e;
  color: #f8fafc;
}

h1, h2, h3, h4, h5, h6 {
  font-family: var(--font-title);
  font-weight: 700;
  letter-spacing: -0.025em;
  color: #0f172a;
  transition: color 0.25s ease;
}

body.mode-sombre h1,
body.mode-sombre h2,
body.mode-sombre h3,
body.mode-sombre h4,
body.mode-sombre h5,
body.mode-sombre h6 {
  color: #ffffff;
}

strong, b, .fw-bold {
  font-weight: 700;
}

/* Focus clavier accessible et coh�rent sur toute l'application */
a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible,
.btn:focus-visible {
  outline: 2px solid var(--color-dia-orange);
  outline-offset: 2px;
}

/* Scrollbar discr�te et moderne (Webkit) */
::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background-color: rgba(19, 56, 92, 0.25);
  border-radius: 9999px;
  border: 2px solid transparent;
  background-clip: content-box;
}

::-webkit-scrollbar-thumb:hover {
  background-color: rgba(219, 83, 35, 0.45);
  background-clip: content-box;
}

/* Accessibilit� : respect des pr�f�rences de r�duction des animations */
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
</style>
