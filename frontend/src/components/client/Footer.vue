<script setup>
import { t } from '@/i18n'
const anneeActuelle = new Date().getFullYear()
</script>

<template>
  <footer class="pied-page-client">
    <div class="container-xl d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <router-link to="/">
          <img src="/logo-dia.png" alt="DIA Logo" class="logo-footer logo-footer-clair" />
          <img src="/logo-dia-dark.png" alt="DIA Logo" class="logo-footer logo-footer-sombre" />
        </router-link>
      </div>
      <div class="small texte-copyright text-center text-md-end">
        &copy; {{ anneeActuelle }} <strong class="text-white">DIA TaxiBrousse</strong> — {{ t('footer_copyright') }}
      </div>
    </div>
  </footer>
</template>

<style>
@import "@/assets/css/client/base.css";
@import "@/assets/css/client/footer.css";
</style>
