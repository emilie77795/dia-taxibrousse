<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { Chart, registerables } from 'chart.js'
import Layout from '@/components/admin/Layout.vue'
import api from '@/api'

Chart.register(...registerables)

const routeur = useRouter()
const stats = ref(null)
const chargement = ref(true)
const canvasGraphique = ref(null)
let instanceGraphique = null

// Modale Raccourci Ajout Trajet
const modalAjoutTrajetOuvert = ref(false)
const nouveauTrajet = ref({ ville_depart: '', ville_arrivee: '', date: '', horaire: '', matricule: '' })
const listeVehicules = ref([])
const messageNotif = ref('')
const notifications = ref([])

// N'afficher dans le tableau de bord QUE les notifications non consultées (pour ne pas encombrer)
const notificationsNonLues = computed(() => {
  return notifications.value.filter(n => n.statut === 'non_lu')
})

async function marquerNotificationLue(n) {
  try {
    await api.post(`/api/notifications/${n.id}/lire`)
    n.statut = 'lu'
    window.dispatchEvent(new CustomEvent('dia:notifications-lues'))
  } catch (err) {
    console.error('Erreur marquage notification lue', err)
    n.statut = 'lu'
  }
}

async function marquerToutesNotificationsLues() {
  try {
    await api.post('/api/notifications/lire')
    notifications.value.forEach(n => { n.statut = 'lu' })
    window.dispatchEvent(new CustomEvent('dia:notifications-lues'))
  } catch (err) {
    console.error('Erreur marquage global des notifications', err)
    notifications.value.forEach(n => { n.statut = 'lu' })
  }
}

async function traiterNotification(n) {
  await marquerNotificationLue(n)
  routeur.push('/admin/reservations')
}

function onNotifsLues() {
  notifications.value.forEach(n => { n.statut = 'lu' })
}

async function chargerDonnees() {
  chargement.value = true
  try {
    const [repStats, repVehicules, repNotifs] = await Promise.all([
      api.get('/api/stats'),
      api.get('/api/vehicules'),
      api.get('/api/notifications?scope=all')
    ])
    stats.value = repStats.data
    listeVehicules.value = repVehicules.data
    notifications.value = repNotifs.data || []

    initialiserGraphique()
  } catch (err) {
    console.error('Erreur chargement des stats', err)
  } finally {
    chargement.value = false
  }
}

function initialiserGraphique() {
  if (!canvasGraphique.value) return
  if (instanceGraphique) instanceGraphique.destroy()

  const ctx = canvasGraphique.value.getContext('2d')
  instanceGraphique = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['MVola', 'Orange Money', 'Airtel Money', 'Espèces Checkpoint'],
      datasets: [
        {
          label: 'Chiffre d\'affaires par mode de règlement (Ar)',
          data: [
            stats.value?.bilan_operateurs?.['MVola'] || 0,
            stats.value?.bilan_operateurs?.['Orange Money'] || 0,
            stats.value?.bilan_operateurs?.['Airtel Money'] || 0,
            stats.value?.bilan_operateurs?.['Espèces'] || 0,
          ],
          backgroundColor: ['#13385C', '#DB5323', '#FFE7A8', '#C0C0BF'],
          borderColor: ['#13385C', '#DB5323', '#DB5323', '#13385C'],
          borderWidth: 1,
          borderRadius: 8,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: (val) => val.toLocaleString() + ' Ar'
          }
        }
      }
    }
  })
}

// Raccourci rapide : Ajouter Trajet
async function enregistrerNouveauTrajet() {
  if (!nouveauTrajet.value.ville_depart || !nouveauTrajet.value.ville_arrivee || !nouveauTrajet.value.date || !nouveauTrajet.value.horaire) {
    alert('Veuillez renseigner tous les champs obligatoires.')
    return
  }
  try {
    await api.post('/api/trajets', nouveauTrajet.value)
    modalAjoutTrajetOuvert.value = false
    nouveauTrajet.value = { ville_depart: '', ville_arrivee: '', date: '', horaire: '', matricule: '' }
    await chargerDonnees()
    alert('Trajet ajouté avec succès !')
  } catch (err) {
    alert(err.response?.data?.error || 'Erreur création trajet.')
  }
}

function envoyerNotificationGenerale() {
  const texte = prompt('Saisir le message de notification pour les voyageurs :')
  if (texte) {
    messageNotif.value = `Notification envoyée à tous les voyageurs : "${texte}"`
    setTimeout(() => messageNotif.value = '', 4000)
  }
}

onMounted(() => {
  chargerDonnees()
  window.addEventListener('dia:notifications-lues', onNotifsLues)
})

onUnmounted(() => {
  window.removeEventListener('dia:notifications-lues', onNotifsLues)
})
</script>

<template>
  <Layout title="Tableau de Bord & Vue d'Ensemble">
    <!-- Notification flash raccourci -->
    <div v-if="messageNotif" class="alert alert-success py-2 mb-3 shadow-sm">
      <img src="/icons/autres/receipt.png" alt="" class="icone-action" /> {{ messageNotif }}
    </div>

    <!-- Rappel Pointage Checkpoint (Bandeau compact et élégant quand aucune notification en attente) -->
    <div v-if="stats && stats.nb_departs_jour > 0 && notificationsNonLues.length === 0" class="mb-4">
      <div class="carte-alerte carte-alerte--checkpoint">
        <div class="d-flex align-items-center gap-3">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 36px; height: 36px; background: rgba(219, 83, 35, 0.1);">
            <img src="/icons/autres/receipt.png" alt="" class="icone-auto" style="width: 17px; height: 17px;" />
          </div>
          <div class="carte-alerte__contenu">
            <span class="carte-alerte__titre">Pointage Checkpoint</span>
            <p class="carte-alerte__texte mb-0">
              <strong>{{ stats.nb_departs_jour }}</strong> départ{{ stats.nb_departs_jour > 1 ? 's' : '' }} programmé{{ stats.nb_departs_jour > 1 ? 's' : '' }} aujourd'hui.
            </p>
          </div>
        </div>
        <router-link to="/admin/checkpoint" class="btn btn-sm btn-primary text-white fw-bold rounded-pill d-inline-flex align-items-center gap-1 flex-shrink-0">
          <span>Gérer Checkpoint</span>
          <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite icone-sur-fond-sombre" />
        </router-link>
      </div>
    </div>

    <!-- Si des notifications non lues existent (avec ou sans départ du jour) -->
    <div v-else-if="notificationsNonLues.length > 0" class="row g-3 mb-4 align-items-start">
      <!-- Pointage Checkpoint (si départs du jour) -->
      <div class="col-lg-4 col-md-5" v-if="stats && stats.nb_departs_jour > 0">
        <div class="carte-alerte carte-alerte--checkpoint d-flex flex-column align-items-stretch gap-2">
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
              <img src="/icons/autres/receipt.png" alt="" class="icone-auto" style="width: 16px; height: 16px;" />
              <span class="carte-alerte__titre mb-0">Pointage Checkpoint</span>
            </div>
            <span class="badge rounded-pill bg-light text-dark border small">{{ stats.nb_departs_jour }} départ(s)</span>
          </div>
          <p class="carte-alerte__texte mb-1 small">
            <strong>{{ stats.nb_departs_jour }}</strong> départ programmé aujourd'hui.
          </p>
          <router-link to="/admin/checkpoint" class="btn btn-sm btn-primary text-white fw-bold rounded-pill d-inline-flex align-items-center justify-content-center gap-1 mt-1">
            <span>Gérer Checkpoint</span>
            <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite icone-sur-fond-sombre" />
          </router-link>
        </div>
      </div>

      <!-- Flux des Notifications Récentes -->
      <div class="col" :class="stats && stats.nb_departs_jour > 0 ? 'col-lg-8 col-md-7' : 'col-12'">
        <div class="carte-notifications">
          <div class="carte-notifications__entete">
            <div class="d-flex align-items-center gap-2">
              <h5 class="carte-notifications__titre mb-0">
                <img src="/icons/message/megaphone.png" alt="" />
                <span>Demandes &amp; Alertes Voyageurs</span>
              </h5>
              <span class="badge rounded-pill" style="background-color: #DB5323; color: #fff; font-size: 0.72rem;">
                {{ notificationsNonLues.length }} en attente
              </span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <button
                @click="marquerToutesNotificationsLues"
                class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1"
                style="font-size: 0.78rem;"
                title="Marquer toutes ces notifications comme lues pour dégager le tableau de bord"
              >
                Tout masquer ✓
              </button>
              <router-link to="/admin/reservations" class="btn btn-sm btn-outline-primary rounded-pill px-3 d-inline-flex align-items-center gap-1">
                <span>Gérer les réservations</span>
                <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite" />
              </router-link>
            </div>
          </div>

          <div class="liste-notifs">
            <div 
              v-for="n in notificationsNonLues.slice(0, 5)" 
              :key="n.id" 
              class="notif-item"
            >
              <div class="notif-corps">
                <div class="notif-entete-ligne">
                  <span v-if="n.type === 'demande_remboursement'" class="badge-type-notif badge-type-notif--remboursement">
                    Demande Remboursement
                  </span>
                  <span v-else-if="n.type === 'report_voyage'" class="badge-type-notif badge-type-notif--report">
                    Report Voyage
                  </span>
                  <span v-else class="badge-type-notif badge-type-notif--autre">
                    {{ n.type }}
                  </span>
                  <span class="notif-date">{{ n.date_creation }}</span>
                </div>
                <p class="notif-message">{{ n.message }}</p>
              </div>

              <div class="d-flex align-items-center gap-1 ms-2 flex-shrink-0">
                <button
                  v-if="n.type === 'demande_remboursement' || n.type === 'report_voyage'"
                  @click="traiterNotification(n)"
                  class="btn btn-sm btn-primary rounded-pill px-2.5 py-1"
                  style="font-size: 0.78rem;"
                  title="Traiter cette demande et la retirer du tableau de bord"
                >
                  Traiter
                </button>
                <button
                  @click="marquerNotificationLue(n)"
                  class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1"
                  style="font-size: 0.78rem;"
                  title="Marquer comme vu (retirer du tableau de bord)"
                >
                  Vu ✓
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Cartes statistiques globales (Palette 4 couleurs) -->
    <div class="row g-3 mb-4">
      <!-- CA Total (Orange #DB5323) -->
      <div class="col-sm-6 col-lg-3">
        <div class="carte-stat border-start border-4 h-100 bg-white p-3 rounded-3 shadow-sm" style="border-left-color: #DB5323 !important;">
          <div class="text-muted small fw-bold">Chiffre d'Affaires Total</div>
          <div class="fs-4 fw-bold mt-1" style="color: #DB5323;">{{ stats?.chiffre_affaires_texte || '0 Ar' }}</div>
          <span class="small text-muted">Encaissements validés</span>
        </div>
      </div>

      <!-- Taux de Remplissage (Bleu #13385C) -->
      <div class="col-sm-6 col-lg-3">
        <div class="carte-stat border-start border-4 h-100 bg-white p-3 rounded-3 shadow-sm" style="border-left-color: #13385C !important;">
          <div class="text-muted small fw-bold">Taux de Remplissage Moyen</div>
          <div class="fs-4 fw-bold mt-1" style="color: #13385C;">{{ stats?.taux_remplissage_moyen || 0 }}%</div>
          <span class="small text-muted">Sur l'ensemble de la flotte</span>
        </div>
      </div>

      <!-- Voyageurs Créés (Crème #FFE7A8 / Bleu) -->
      <div class="col-sm-6 col-lg-3">
        <div class="carte-stat border-start border-4 h-100 bg-white p-3 rounded-3 shadow-sm" style="border-left-color: #FFE7A8 !important;">
          <div class="text-muted small fw-bold">Voyageurs Inscrits Total</div>
          <div class="fs-4 fw-bold mt-1" style="color: #13385C;">{{ stats?.total_voyageurs || 0 }}</div>
          <span class="small text-muted">Comptes actifs</span>
        </div>
      </div>

      <!-- Réservations et Remboursements (Gris #C0C0BF) -->
      <div class="col-sm-6 col-lg-3">
        <div class="carte-stat border-start border-4 h-100 bg-white p-3 rounded-3 shadow-sm" style="border-left-color: #C0C0BF !important;">
          <div class="text-muted small fw-bold">Réservations Totales</div>
          <div class="fs-4 fw-bold mt-1" style="color: #13385C;">{{ stats?.total_reservations || 0 }}</div>
          <span class="small text-muted">{{ stats?.nb_reportes || 0 }} reporté(s) • {{ stats?.nb_remboursements || 0 }} remboursé(s)</span>
        </div>
      </div>
    </div>

    <!-- Graphe et Bilan par Opérateur -->
    <div class="row g-4 mb-4">
      <!-- Graphe des encaissements -->
      <div class="col-lg-8">
        <div class="bloc-blanc p-4 rounded-3 shadow-sm h-100">
          <h5 class="fw-bold mb-1">Encaissements par Opérateur & Espèces</h5>
          <p class="text-muted small mb-3">Répartition des paiements Mobile Money et des règlements de solde en espèces au checkpoint.</p>
          <div style="height: 260px;">
            <canvas ref="canvasGraphique"></canvas>
          </div>
        </div>
      </div>

      <!-- Bilan détaillé opérateurs -->
      <div class="col-lg-4">
        <div class="bloc-blanc p-4 rounded-3 shadow-sm h-100 d-flex flex-column justify-content-between">
          <div>
            <h5 class="fw-bold mb-3">Bilan par Opérateur</h5>
            <div class="list-group list-group-flush">
              <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                <span>MVola (Telma)</span>
                <strong style="color: #13385C;">{{ (stats?.bilan_operateurs?.['MVola'] || 0).toLocaleString() }} Ar</strong>
              </div>
              <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                <span>Orange Money</span>
                <strong style="color: #DB5323;">{{ (stats?.bilan_operateurs?.['Orange Money'] || 0).toLocaleString() }} Ar</strong>
              </div>
              <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                <span>Airtel Money</span>
                <strong style="color: #DB5323;">{{ (stats?.bilan_operateurs?.['Airtel Money'] || 0).toLocaleString() }} Ar</strong>
              </div>
              <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                <span>Espèces au Checkpoint</span>
                <strong style="color: #13385C;">{{ (stats?.bilan_operateurs?.['Espèces'] || 0).toLocaleString() }} Ar</strong>
              </div>
            </div>
          </div>
          <router-link to="/admin/reservations" class="btn btn-outline-primary btn-sm rounded-pill w-100 mt-3 d-inline-flex align-items-center justify-content-center gap-1">
            <span>Consulter l'historique complet</span>
            <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite" />
          </router-link>
        </div>
      </div>
    </div>

    <!-- Planning des départs du jour & Trajets par chauffeur -->
    <div class="row g-4">
      <!-- Planning départs du jour -->
      <div class="col-lg-7">
        <div class="bloc-blanc p-4 rounded-3 shadow-sm">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Planning des Départs du Jour</h5>
            <router-link to="/admin/checkpoint" class="btn btn-sm btn-outline-primary rounded-pill d-inline-flex align-items-center gap-1">
              <span>Ouvrir Checkpoint</span>
              <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite" />
            </router-link>
          </div>

          <div v-if="!stats?.departs_du_jour || stats.departs_du_jour.length === 0" class="text-center py-4 text-muted small">
            Aucun départ n'est programmé à la date d'aujourd'hui.
          </div>

          <table v-else class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Horaire</th>
                <th>Trajet</th>
                <th>Véhicule</th>
                <th>Type</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in stats.departs_du_jour" :key="d.id">
                <td><span class="badge bg-primary fs-6">{{ d.horaire }}</span></td>
                <td class="fw-bold">{{ d.depart }} ➔ {{ d.arrivee }}</td>
                <td><code>{{ d.vehicule }}</code></td>
                <td><span class="badge bg-light text-dark border">{{ d.type }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Nombre de trajets effectués par chauffeurs -->
      <div class="col-lg-5">
        <div class="bloc-blanc p-4 rounded-3 shadow-sm">
          <h5 class="fw-bold mb-3">Trajets par Chauffeur</h5>
          
          <div v-if="!stats?.trajets_par_chauffeur || Object.keys(stats.trajets_par_chauffeur).length === 0" class="text-center py-4 text-muted small">
            Aucun trajet attribué aux chauffeurs.
          </div>

          <ul v-else class="list-group list-group-flush">
            <li 
              v-for="(nb, chauffeur) in stats.trajets_par_chauffeur" 
              :key="chauffeur"
              class="list-group-item px-0 d-flex justify-content-between align-items-center"
            >
              <div class="d-flex align-items-center gap-2">
                <img src="/icons/utilisateur/user.png" alt="Chauffeur" class="icone-auto" style="width: 16px; height: 16px;" />
                <span class="fw-bold">{{ chauffeur }}</span>
              </div>
              <span class="badge bg-secondary rounded-pill">{{ nb }} trajet(s)</span>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- MODALE RACCOURCI : AJOUT RAPIDE DE TRAJET -->
    <div v-if="modalAjoutTrajetOuvert" class="fond-modale">
      <div class="boite-modale" style="max-width: 480px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Ajout Rapide de Trajet</h5>
          <button @click="modalAjoutTrajetOuvert = false" class="btn-close"></button>
        </div>

        <form @submit.prevent="enregistrerNouveauTrajet">
          <div class="mb-3">
            <label class="form-label small fw-bold">Ville de départ</label>
            <input v-model="nouveauTrajet.ville_depart" type="text" class="form-control" placeholder="Antananarivo" required />
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Ville d'arrivée</label>
            <input v-model="nouveauTrajet.ville_arrivee" type="text" class="form-control" placeholder="Toamasina" required />
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Date</label>
              <input v-model="nouveauTrajet.date" type="date" class="form-control" required />
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Horaire</label>
              <input v-model="nouveauTrajet.horaire" type="time" class="form-control" required />
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Véhicule assigné</label>
            <select v-model="nouveauTrajet.matricule" class="form-select" required>
              <option value="">-- Sélectionner un véhicule --</option>
              <option v-for="v in listeVehicules" :key="v.id" :value="v.matricule">
                {{ v.matricule }} ({{ v.type }} - {{ v.chauffeur || 'Sans chauffeur' }})
              </option>
            </select>
          </div>

          <div class="d-flex gap-2 mt-4">
            <button type="button" @click="modalAjoutTrajetOuvert = false" class="btn btn-light w-50">Annuler</button>
            <button type="submit" class="btn btn-primary w-50">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
@import "@/assets/css/admin/layout.css";
@import "@/assets/css/admin/dashboard.css";
@import "@/assets/css/admin/modales.css";
</style>
