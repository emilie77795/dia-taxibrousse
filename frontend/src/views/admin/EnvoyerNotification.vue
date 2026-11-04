<script setup>
import { ref, onMounted, computed } from "vue"
import { useRouter, useRoute } from "vue-router"
import Layout from "@/components/admin/Layout.vue"
import api from "@/api"

const router = useRouter()
const route = useRoute()

// ─── État du formulaire ──────────────────────────────────────────────────────
const utilisateurs = ref([])
const chargementUsers = ref(true)
const rechercheUser = ref("")
const destinatairesSelectionnes = ref([])
const envoyerATous = ref(false)
const typeNotification = ref("message_admin")
const message = ref("")
const enEnvoi = ref(false)
const erreur = ref("")
const succes = ref("")

async function chargerUtilisateurs() {
  chargementUsers.value = true
  try {
    const { data } = await api.get("/api/utilisateurs")
    utilisateurs.value = (data || []).filter(
      u => !["administrateur", "super_admin", "gerant"].includes(u.role)
    )
  } catch (err) {
    console.error("Erreur chargement utilisateurs", err)
  } finally {
    chargementUsers.value = false
  }
}

function toggleUser(id) {
  const idx = destinatairesSelectionnes.value.indexOf(id)
  if (idx === -1) {
    destinatairesSelectionnes.value.push(id)
  } else {
    destinatairesSelectionnes.value.splice(idx, 1)
  }
}

const utilisateursFiltres = computed(() => {
  const q = rechercheUser.value.toLowerCase().trim()
  if (!q) return utilisateurs.value
  return utilisateurs.value.filter(u =>
    `${u.prenom} ${u.nom} ${u.email} ${u.tel || ''}`.toLowerCase().includes(q)
  )
})

async function envoyer() {
  erreur.value = ""
  succes.value = ""

  if (!message.value.trim()) {
    erreur.value = "Veuillez saisir le texte de la notification."
    return
  }

  if (!envoyerATous.value && destinatairesSelectionnes.value.length === 0) {
    erreur.value = "Sélectionnez au moins un voyageur destinataire ou cochez 'Tous les voyageurs'."
    return
  }

  enEnvoi.value = true
  try {
    const payload = {
      message: message.value.trim(),
      type: typeNotification.value || "message_admin",
      destinataires: envoyerATous.value ? "tous" : destinatairesSelectionnes.value,
    }

    const { data } = await api.post("/api/notifications/envoyer", payload)
    succes.value = data.message || "Notification transmise avec succès."

    setTimeout(() => {
      router.push("/admin/notifications")
    }, 1200)
  } catch (err) {
    erreur.value = err.response?.data?.error || "Erreur lors de l'envoi de la notification."
  } finally {
    enEnvoi.value = false
  }
}

onMounted(async () => {
  await chargerUtilisateurs()

  // Préremplissage lors d'un transfert via query params
  if (route.query.message) {
    message.value = String(route.query.message)
  }
  if (route.query.destinataire_id) {
    const destId = parseInt(route.query.destinataire_id)
    if (!isNaN(destId)) {
      destinatairesSelectionnes.value = [destId]
      envoyerATous.value = false
    }
  }
})
</script>

<template>
  <Layout title="Diffuser une Notification">
    <!-- Barre d'action supérieure -->
    <template #actions>
      <router-link to="/admin/notifications" class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-inline-flex align-items-center gap-2">
        <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-gauche" />
        <span>Retour aux notifications</span>
      </router-link>
    </template>

    <div class="row justify-content-center">
      <div class="col-lg-8 col-xl-7">
        <div class="bloc-blanc p-4">
          <div class="d-flex align-items-center gap-2 mb-4 pb-2 border-bottom">
            <img src="/icons/message/megaphone.png" alt="" style="width: 22px; height: 22px;" />
            <div>
              <h5 class="fw-bold mb-0 text-dark">
                {{ route.query.message ? 'Transférer la Notification' : 'Nouvelle Notification Voyageurs' }}
              </h5>
              <span class="text-muted small">
                Transmettez une information ou une alerte en temps réel aux clients de DIA TaxiBrousse
              </span>
            </div>
          </div>

          <form @submit.prevent="envoyer">
            <!-- 1. Type de notification -->
            <div class="mb-4">
              <label class="form-label small fw-bold text-dark">Type d'avis :</label>
              <select v-model="typeNotification" class="form-select">
                <option value="message_admin">Avis général de l'Administration</option>
                <option value="report_voyage">Alerte de Report ou Modification de Trajet</option>
                <option value="remboursement_valide">Confirmation de Remboursement</option>
              </select>
            </div>

            <!-- 2. Choix de l'audience / Destinataires -->
            <div class="mb-4">
              <label class="form-label small fw-bold text-dark d-block">Destinataires :</label>
              
              <div class="d-flex gap-2 mb-3">
                <button
                  type="button"
                  @click="envoyerATous = false"
                  class="btn btn-sm rounded-pill px-3 flex-fill"
                  :class="!envoyerATous ? 'btn-primary' : 'btn-outline-secondary'"
                >
                  Voyageur(s) spécifique(s)
                </button>
                <button
                  type="button"
                  @click="envoyerATous = true"
                  class="btn btn-sm rounded-pill px-3 flex-fill"
                  :class="envoyerATous ? 'btn-primary' : 'btn-outline-secondary'"
                >
                  Tous les voyageurs ({{ utilisateurs.length }})
                </button>
              </div>

              <!-- Panneau de sélection des voyageurs -->
              <div v-if="!envoyerATous" class="cadre-selection-users">
                <div class="mb-2">
                  <input
                    v-model="rechercheUser"
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Filtrer un voyageur (nom, prénom, email, téléphone)..."
                  />
                </div>

                <div v-if="chargementUsers" class="text-center py-3 text-muted small">
                  Chargement des voyageurs...
                </div>

                <div v-else class="liste-voyageurs-scroll">
                  <div v-if="utilisateursFiltres.length === 0" class="text-center text-muted py-3 small">
                    Aucun voyageur ne correspond à la recherche.
                  </div>
                  <div
                    v-for="u in utilisateursFiltres"
                    :key="u.id"
                    class="item-voyageur-choix"
                    :class="{ 'actif': destinatairesSelectionnes.includes(u.id) }"
                    @click="toggleUser(u.id)"
                  >
                    <input
                      type="checkbox"
                      class="form-check-input mt-0 me-2"
                      :checked="destinatairesSelectionnes.includes(u.id)"
                      @click.stop="toggleUser(u.id)"
                    />
                    <div class="flex-grow-1 min-w-0">
                      <strong class="d-block text-dark small text-truncate">{{ u.prenom }} {{ u.nom }}</strong>
                      <span class="small text-muted text-truncate d-block">{{ u.email }} <span v-if="u.tel">• {{ u.tel }}</span></span>
                    </div>
                  </div>
                </div>

                <div class="mt-2 d-flex justify-content-between align-items-center small text-muted">
                  <span><strong>{{ destinatairesSelectionnes.length }}</strong> voyageur(s) sélectionné(s)</span>
                  <button
                    v-if="destinatairesSelectionnes.length > 0"
                    type="button"
                    @click="destinatairesSelectionnes = []"
                    class="btn btn-link p-0 text-decoration-none small text-danger"
                  >
                    Effacer la sélection
                  </button>
                </div>
              </div>
            </div>

            <!-- 3. Message -->
            <div class="mb-4">
              <label class="form-label small fw-bold text-dark">Message à diffuser :</label>
              <textarea
                v-model="message"
                class="form-control"
                rows="5"
                placeholder="Rédigez ici le texte clair et précis destiné au voyageur..."
                required
              ></textarea>
              <div class="form-text small text-muted mt-1">
                Ce message sera immédiatement affiché dans l'espace « Mes Notifications » du voyageur et incrémentera sa cloche d'alertes.
              </div>
            </div>

            <!-- Alertes de retour -->
            <div v-if="erreur" class="alert alert-danger py-2 small mb-3">{{ erreur }}</div>
            <div v-if="succes" class="alert alert-success py-2 small mb-3">{{ succes }}</div>

            <!-- Boutons de soumission -->
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
              <router-link to="/admin/notifications" class="btn btn-outline-secondary px-4 rounded-pill">
                Annuler
              </router-link>
              <button
                type="submit"
                class="btn btn-primary px-4 rounded-pill fw-bold"
                :disabled="enEnvoi"
              >
                <span v-if="enEnvoi" class="spinner-border spinner-border-sm me-1"></span>
                Diffuser la notification
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
@import "@/assets/css/admin/layout.css";
@import "@/assets/css/admin/tables.css";
@import "@/assets/css/admin/envoyer-notification.css";
</style>
