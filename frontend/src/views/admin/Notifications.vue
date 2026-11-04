<script setup>
import { ref, onMounted, computed } from "vue"
import { useRouter } from "vue-router"
import Layout from "@/components/admin/Layout.vue"
import api from "@/api"

const router = useRouter()

// ─── Données notifications ──────────────────────────────────────────────────
const notifications = ref([])
const chargement = ref(true)
const filtre = ref("tous")
const recherche = ref("")

async function chargerNotifications() {
  chargement.value = true
  try {
    const { data } = await api.get("/api/notifications?scope=all")
    notifications.value = data || []
  } catch (err) {
    console.error("Erreur chargement notifications", err)
  } finally {
    chargement.value = false
  }
}

async function marquerNotificationsLues() {
  try {
    await api.post("/api/notifications/lire")
    notifications.value = notifications.value.map(n => ({ ...n, statut: "lu" }))
    window.dispatchEvent(new CustomEvent('dia:notifications-lues'))
  } catch (err) {
    console.error("Erreur marquage notifications lues", err)
  }
}

function transfererNotification(n) {
  router.push({
    path: "/admin/notifications/nouveau",
    query: {
      message: n.message || "",
      destinataire_id: n.destinataire?.id || undefined,
    },
  })
}

// ─── Filtres & Table ────────────────────────────────────────────────────────
const notificationsFiltrees = computed(() => {
  let liste = notifications.value

  if (filtre.value !== "tous") {
    liste = liste.filter(n => n.type === filtre.value)
  }

  if (recherche.value.trim()) {
    const q = recherche.value.toLowerCase().trim()
    liste = liste.filter(n => {
      const msg = (n.message || "").toLowerCase()
      const dest = n.destinataire ? `${n.destinataire.prenom} ${n.destinataire.nom} ${n.destinataire.email}`.toLowerCase() : ""
      return msg.includes(q) || dest.includes(q)
    })
  }

  return liste
})

function compteParType(typeCle) {
  if (typeCle === "tous") return notifications.value.length
  return notifications.value.filter(n => n.type === typeCle).length
}

function formaterDateHeure(dateTexte) {
  if (!dateTexte) return "—"
  return new Date(dateTexte).toLocaleString("fr-FR", {
    day: "2-digit", month: "short", year: "numeric",
    hour: "2-digit", minute: "2-digit"
  })
}

function iconeType(type) {
  const icons = {
    demande_remboursement: "/icons/autres/loading.png",
    report_voyage: "/icons/autres/undo.png",
    nouveau_voyageur: "/icons/utilisateur/user.png",
    nouvelle_reservation: "/icons/autres/receipt.png",
    message_admin: "/icons/message/megaphone.png",
    remboursement_valide: "/icons/autres/receipt.png",
    remboursement_refuse: "/icons/autres/loading.png",
  }
  return icons[type] || "/icons/message/megaphone.png"
}

function badgeClass(type) {
  const classes = {
    demande_remboursement: "badge-alerte-demande",
    report_voyage: "badge-alerte-report",
    nouveau_voyageur: "badge-neutre",
    nouvelle_reservation: "badge-primaire",
    message_admin: "badge-admin-message",
    remboursement_valide: "badge-succes",
    remboursement_refuse: "badge-danger",
  }
  return classes[type] || "badge-neutre"
}

function labelType(type) {
  const labels = {
    demande_remboursement: "Demande Remboursement",
    report_voyage: "Report de Voyage",
    nouveau_voyageur: "Nouveau Voyageur",
    nouvelle_reservation: "Nouvelle Réservation",
    message_admin: "Message DIA",
    remboursement_valide: "Remboursement Validé",
    remboursement_refuse: "Remboursement Refusé",
  }
  return labels[type] || type
}

function remboursementDejaTraite(notification) {
  return notification.type === "demande_remboursement"
    && notification.reservation_statut
    && notification.reservation_statut !== "demande_remboursement"
}

onMounted(async () => {
  await chargerNotifications()
  await marquerNotificationsLues()
})
</script>

<template>
  <Layout title="Centre des Notifications">
    <!-- Barre d'actions en haut -->
    <template #actions>
      <router-link to="/admin/notifications/nouveau" class="btn btn-sm btn-primary rounded-pill px-3 d-inline-flex align-items-center gap-2">
        <img src="/icons/message/megaphone.png" alt="" class="icone-sur-fond-sombre" style="width: 15px; height: 15px;" />
        <span>Nouveau Message</span>
      </router-link>

      <button @click="chargerNotifications" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
        <img src="/icons/autres/loading.png" alt="" class="icone-action" /> Actualiser
      </button>
    </template>

    <!-- Bloc principal du journal des notifications -->
    <div class="bloc-blanc">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h5 class="fw-bold mb-0">Historique des Notifications &amp; Messages</h5>
          <span class="text-muted small">Suivi des demandes voyageurs, alertes de réservation et diffusions administratives</span>
        </div>

        <!-- Recherche par mot-clé -->
        <div style="min-width: 260px; max-width: 360px;" class="w-100 w-sm-auto">
          <input
            v-model="recherche"
            type="text"
            class="form-control form-control-sm"
            placeholder="Rechercher par message ou voyageur..."
          />
        </div>
      </div>

      <!-- Onglets Filtres de statut -->
      <div class="d-flex flex-wrap gap-2 mb-4">
        <button
          v-for="opt in [
            { val: 'tous', label: 'Toutes' },
            { val: 'demande_remboursement', label: 'Remboursements' },
            { val: 'report_voyage', label: 'Reports' },
            { val: 'message_admin', label: 'Messages DIA' },
            { val: 'nouvelle_reservation', label: 'Réservations' },
            { val: 'nouveau_voyageur', label: 'Voyageurs' },
          ]"
          :key="opt.val"
          @click="filtre = opt.val"
          class="btn btn-sm rounded-pill px-3 filtre-pill"
          :class="filtre === opt.val ? 'filtre-pill-actif' : 'filtre-pill-inactif'"
        >
          <span>{{ opt.label }}</span>
          <span class="badge ms-1" :class="filtre === opt.val ? 'bg-white text-dark' : 'bg-light text-secondary border'">
            {{ compteParType(opt.val) }}
          </span>
        </button>
      </div>

      <!-- État de chargement -->
      <div v-if="chargement" class="text-center py-5 text-muted">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div>Chargement des notifications...</div>
      </div>

      <!-- Aucune notification -->
      <div v-else-if="notificationsFiltrees.length === 0" class="text-center py-5 text-muted">
        <img src="/icons/message/megaphone.png" alt="Vide" style="width: 44px; height: 44px; opacity: 0.35;" class="mb-3" />
        <div class="fw-bold text-dark">Aucune notification trouvée</div>
        <div class="small">Aucun élément ne correspond au filtre ou à la recherche sélectionnée.</div>
      </div>

      <!-- Tableau des notifications -->
      <div v-else class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th style="width: 170px;">Type</th>
              <th>Message</th>
              <th style="width: 200px;">Destinataire / Concerne</th>
              <th style="width: 160px;">Date &amp; Heure</th>
              <th style="width: 150px; text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="n in notificationsFiltrees"
              :key="n.id"
              :class="{ 'ligne-demande-en-attente': n.type === 'demande_remboursement' && !remboursementDejaTraite(n) }"
            >
              <!-- Type -->
              <td>
                <span class="badge d-inline-flex align-items-center gap-1 px-2.5 py-1" :class="badgeClass(n.type)">
                  <img :src="iconeType(n.type)" alt="" class="icone-badge" />
                  <span>{{ labelType(n.type) }}</span>
                </span>
              </td>

              <!-- Message (Texte en noir / gris foncé, jamais en bleu) -->
              <td>
                <div class="texte-message">{{ n.message }}</div>
              </td>

              <!-- Destinataire -->
              <td>
                <div v-if="n.destinataire && !n.est_pour_moi">
                  <span class="destinataire-chip">
                    <img src="/icons/utilisateur/user.png" alt="" class="icone-dest" />
                    <strong>{{ n.destinataire.prenom }} {{ n.destinataire.nom }}</strong>
                  </span>
                  <div class="small text-muted ps-1">{{ n.destinataire.email }}</div>
                </div>
                <div v-else-if="n.est_pour_moi">
                  <span class="badge bg-secondary text-white fw-normal">Reçue (Administration)</span>
                </div>
                <div v-else class="text-muted small">—</div>
              </td>

              <!-- Date & Heure -->
              <td>
                <span class="text-muted small text-nowrap">{{ formaterDateHeure(n.date_creation) }}</span>
              </td>

              <!-- Actions -->
              <td style="text-align: right;">
                <div class="d-inline-flex align-items-center gap-1 justify-content-end">
                  <!-- Bouton Transférer -->
                  <button
                    @click="transfererNotification(n)"
                    class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 d-inline-flex align-items-center gap-1"
                    title="Transférer ce message dans une nouvelle notification"
                  >
                    <span>Transférer</span>
                    <span style="font-size: 0.9em;">↗</span>
                  </button>

                  <!-- Boutons d'action métier -->
                  <span v-if="remboursementDejaTraite(n)" class="badge bg-success ms-1">Traité</span>
                  <router-link
                    v-else-if="n.type === 'demande_remboursement' || n.type === 'report_voyage'"
                    to="/admin/reservations"
                    class="btn btn-sm btn-primary rounded-pill px-2.5 ms-1 d-inline-flex align-items-center gap-1"
                  >
                    <span>Traiter</span>
                    <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite" />
                  </router-link>
                  <router-link
                    v-else-if="n.type === 'nouveau_voyageur'"
                    to="/admin/utilisateurs"
                    class="btn btn-sm btn-outline-primary rounded-pill px-2 ms-1"
                  >Voir</router-link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-3 pt-2 border-top text-muted small d-flex justify-content-between align-items-center">
        <span>{{ notificationsFiltrees.length }} notification(s) affichée(s)</span>
        <span class="fst-italic">Les messages envoyés sont instantanément visibles sur l'espace client des voyageurs</span>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
@import "@/assets/css/admin/layout.css";
@import "@/assets/css/admin/tables.css";
@import "@/assets/css/admin/notifications.css";
</style>
