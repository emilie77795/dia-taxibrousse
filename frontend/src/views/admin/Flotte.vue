<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api'
import Layout from '@/components/admin/Layout.vue'

const listeChauffeurs = ref([])
const listeVehicules = ref([])
const listeTypes = ref([])

// Formulaires & Modales Chauffeurs
const modalChauffeurOuvert = ref(false)
const chauffeurEnCours = ref(null)
const formulaireChauffeur = ref({ nom: '', contact: '' })

// Formulaires & Modales Véhicules
const modalVehiculeOuvert = ref(false)
const vehiculeEnCours = ref(null)
const formulaireVehicule = ref({ matricule: '', nombre_places: 8, id_type_vehicule: '', id_chauffeur: '' })

// Formulaires & Modales Types de véhicules
const modalTypeOuvert = ref(false)
const typeEnCours = ref(null)
const formulaireType = ref({ nom: '', image: '' })
const apercuImage = ref('')
const fichierImage = ref(null)
const erreurType = ref('')
const enEnregistrementType = ref(false)

function gererChangementImage(e) {
  const fichier = e.target.files[0]
  if (fichier) {
    fichierImage.value = fichier
    const reader = new FileReader()
    reader.onload = (event) => {
      apercuImage.value = event.target.result
    }
    reader.readAsDataURL(fichier)
  }
}

// ---------------- GESTION CHAUFFEURS ----------------
function ouvrirModalChauffeur(chauffeur = null) {
  chauffeurEnCours.value = chauffeur
  formulaireChauffeur.value = chauffeur ? { nom: chauffeur.nom, contact: chauffeur.contact } : { nom: '', contact: '' }
  modalChauffeurOuvert.value = true
}

async function enregistrerChauffeur() {
  if (!formulaireChauffeur.value.nom || !formulaireChauffeur.value.contact) {
    alert('Nom et contact du chauffeur requis.')
    return
  }
  try {
    if (chauffeurEnCours.value) {
      await api.put(`/api/chauffeurs/${chauffeurEnCours.value.id}`, formulaireChauffeur.value)
    } else {
      await api.post('/api/chauffeurs', formulaireChauffeur.value)
    }
    modalChauffeurOuvert.value = false
    await chargerFlotte()
  } catch (erreur) {
    alert(erreur.response?.data?.error || 'Erreur enregistrement chauffeur.')
  }
}

async function supprimerChauffeur(id) {
  if (!confirm('Voulez-vous vraiment supprimer ce chauffeur ?')) return
  try {
    await api.delete(`/api/chauffeurs/${id}`)
    await chargerFlotte()
  } catch (erreur) {
    alert('Erreur lors de la suppression.')
  }
}

// ---------------- GESTION VÉHICULES ----------------
function getNombrePlacesParDefaut(idType) {
  const type = listeTypes.value.find(t => t.id === idType)
  if (!type) return 14
  const nom = (type.nom || '').toLowerCase()
  if (nom.includes('sprinter')) return 18
  if (nom.includes('sylver') || nom.includes('silver')) return 14
  if (nom.includes('bowing') || nom.includes('boeing') || nom.includes('bus')) return 24
  return 14
}

function surChangementTypeVehicule() {
  const idType = formulaireVehicule.value.id_type_vehicule
  if (idType) {
    formulaireVehicule.value.nombre_places = getNombrePlacesParDefaut(idType)
  }
}

function ouvrirModalVehicule(vehicule = null) {
  vehiculeEnCours.value = vehicule
  if (vehicule) {
    formulaireVehicule.value = {
      matricule: vehicule.matricule,
      nombre_places: vehicule.nombre_places,
      id_type_vehicule: vehicule.id_type_vehicule || (listeTypes.value[0]?.id || ''),
      id_chauffeur: vehicule.id_chauffeur || ''
    }
  } else {
    formulaireVehicule.value = {
      matricule: '',
      nombre_places: 8,
      id_type_vehicule: listeTypes.value[0]?.id || '',
      id_chauffeur: ''
    }
  }
  modalVehiculeOuvert.value = true
}

async function enregistrerVehicule() {
  if (!formulaireVehicule.value.matricule || !formulaireVehicule.value.nombre_places || !formulaireVehicule.value.id_type_vehicule) {
    alert('Matricule, nombre de places et type de véhicule obligatoires.')
    return
  }
  try {
    if (vehiculeEnCours.value) {
      await api.put(`/api/vehicules/${vehiculeEnCours.value.id}`, formulaireVehicule.value)
    } else {
      await api.post('/api/vehicules', formulaireVehicule.value)
    }
    modalVehiculeOuvert.value = false
    await chargerFlotte()
  } catch (erreur) {
    alert(erreur.response?.data?.error || 'Erreur enregistrement véhicule.')
  }
}

async function supprimerVehicule(id) {
  if (!confirm('Voulez-vous vraiment supprimer ce véhicule ?')) return
  try {
    await api.delete(`/api/vehicules/${id}`)
    await chargerFlotte()
  } catch (erreur) {
    alert('Erreur lors de la suppression.')
  }
}

// ---------------- GESTION TYPES DE VÉHICULES ----------------
function ouvrirModalType(typeVeh = null) {
  typeEnCours.value = typeVeh
  formulaireType.value = { 
    nom: typeVeh ? typeVeh.nom : '', 
    image: typeVeh ? (typeVeh.image || '') : '' 
  }
  apercuImage.value = typeVeh && typeVeh.image ? (typeVeh.image.startsWith('http') ? typeVeh.image : 'http://localhost:8000' + typeVeh.image) : ''
  fichierImage.value = null
  erreurType.value = ''
  modalTypeOuvert.value = true
}

async function enregistrerType() {
  if (!formulaireType.value.nom.trim()) {
    erreurType.value = 'Le nom du type est obligatoire.'
    return
  }
  erreurType.value = ''
  enEnregistrementType.value = true

  try {
    const payload = {
      nom: formulaireType.value.nom.trim(),
      image: apercuImage.value || formulaireType.value.image || null
    }

    if (typeEnCours.value) {
      await api.post(`/api/types-vehicules/${typeEnCours.value.id}`, payload)
    } else {
      await api.post('/api/types-vehicules', payload)
    }
    modalTypeOuvert.value = false
    await chargerFlotte()
  } catch (erreur) {
    console.error('Erreur enregistrement type:', erreur)
    erreurType.value = erreur.response?.data?.error || erreur.response?.data?.message || "Erreur lors de l'enregistrement."
  } finally {
    enEnregistrementType.value = false
  }
}

async function supprimerType(id) {
  if (!confirm('Voulez-vous vraiment supprimer ce type de véhicule ?')) return
  try {
    await api.delete(`/api/types-vehicules/${id}`)
    await chargerFlotte()
  } catch (erreur) {
    alert(erreur.response?.data?.error || 'Erreur lors de la suppression.')
  }
}

// ---------------- CHARGEMENT GLOBAL DE LA FLOTTE ----------------
async function chargerFlotte() {
  try {
    const [repChauff, repVeh, repTypes] = await Promise.all([
      api.get('/api/chauffeurs'),
      api.get('/api/vehicules'),
      api.get('/api/types-vehicules')
    ])
    listeChauffeurs.value = repChauff.data
    listeVehicules.value = repVeh.data
    listeTypes.value = repTypes.data
  } catch (erreur) {
    console.error('Erreur chargement flotte', erreur)
  }
}

onMounted(() => {
  chargerFlotte()
})
</script>

<template>
  <Layout title="Gestion de la Flotte (Véhicules, Types & Chauffeurs)">
    <div class="row g-4">
      <!-- Section Véhicules -->
      <div class="col-md-6">
        <div class="bloc-blanc">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Véhicules Enregistrés</h5>
            <button @click="ouvrirModalVehicule()" class="btn btn-primary btn-sm">+ Nouveau Véhicule</button>
          </div>
          <div v-if="listeVehicules.length === 0" class="text-muted text-center py-4">
            Aucun véhicule enregistré.
          </div>
          <table v-else class="table align-middle">
            <thead class="table-light">
              <tr>
                <th>Matricule</th>
                <th>Type</th>
                <th>Places</th>
                <th>Chauffeur</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="v in listeVehicules" :key="v.id">
                <td class="fw-bold text-dark">{{ v.matricule }}</td>
                <td><span class="badge bg-secondary">{{ v.type }}</span></td>
                <td>{{ v.nombre_places }} places</td>
                <td>{{ v.chauffeur || '—' }}</td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button @click="ouvrirModalVehicule(v)" class="btn btn-outline-primary btn-sm" title="Modifier"><img src="/icons/autres/document.png" alt="" class="icone-sur-fond-sombre" style="width: 14px; height: 14px;" /></button>
                    <button @click="supprimerVehicule(v.id)" class="btn btn-outline-danger btn-sm"><img src="/icons/autres/trash.png" alt="" class="icone-sur-fond-sombre" style="width: 14px; height: 14px;" /></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Section Chauffeurs -->
      <div class="col-md-6">
        <div class="bloc-blanc">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Chauffeurs Enregistrés</h5>
            <button @click="ouvrirModalChauffeur()" class="btn btn-primary btn-sm">+ Nouveau Chauffeur</button>
          </div>
          <div v-if="listeChauffeurs.length === 0" class="text-muted text-center py-4">
            Aucun chauffeur enregistré.
          </div>
          <table v-else class="table align-middle">
            <thead class="table-light">
              <tr>
                <th>Nom et Prénom</th>
                <th>Contact</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in listeChauffeurs" :key="c.id">
                <td class="fw-bold text-dark">{{ c.nom }}</td>
                <td>{{ c.contact }}</td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button @click="ouvrirModalChauffeur(c)" class="btn btn-outline-primary btn-sm" title="Modifier"><img src="/icons/autres/document.png" alt="" class="icone-sur-fond-sombre" style="width: 14px; height: 14px;" /></button>
                    <button @click="supprimerChauffeur(c.id)" class="btn btn-outline-danger btn-sm"><img src="/icons/autres/trash.png" alt="" class="icone-sur-fond-sombre" style="width: 14px; height: 14px;" /></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Section Types de Véhicules -->
    <div class="mt-4">
      <div class="bloc-blanc">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Types de Véhicules</h5>
          <button @click="ouvrirModalType()" class="btn btn-primary btn-sm">+ Nouveau Type</button>
        </div>
        <div v-if="listeTypes.length === 0" class="text-muted text-center py-4">
          Aucun type de véhicule enregistré.
        </div>
        <table v-else class="table align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Photo</th>
              <th>Nom du type</th>
              <th>Véhicules liés</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in listeTypes" :key="t.id">
              <td class="text-muted">#{{ t.id }}</td>
              <td>
                <img 
                  :src="t.image ? (t.image.startsWith('http') ? t.image : 'http://localhost:8000' + t.image) : '/images/rn2.jpg'" 
                  alt="Véhicule" 
                  class="rounded object-fit-cover shadow-sm border" 
                  style="width: 50px; height: 38px;" 
                />
              </td>
              <td class="fw-bold">
                <span class="badge bg-warning text-dark fs-6">{{ t.nom }}</span>
              </td>
              <td class="text-muted small">
                {{ listeVehicules.filter(v => v.id_type_vehicule === t.id).length }} véhicule(s)
              </td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button @click="ouvrirModalType(t)" class="btn btn-outline-primary btn-sm" title="Modifier"><img src="/icons/autres/document.png" alt="" class="icone-sur-fond-sombre" style="width: 14px; height: 14px;" /></button>
                  <button @click="supprimerType(t.id)" class="btn btn-outline-danger btn-sm"><img src="/icons/autres/trash.png" alt="" class="icone-sur-fond-sombre" style="width: 14px; height: 14px;" /></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modale Chauffeur -->
    <div v-if="modalChauffeurOuvert" class="fond-modale">
      <div class="boite-modale" style="max-width: 450px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">{{ chauffeurEnCours ? 'Modifier Chauffeur' : 'Nouveau Chauffeur' }}</h5>
          <button @click="modalChauffeurOuvert = false" class="btn-close"></button>
        </div>

        <form @submit.prevent="enregistrerChauffeur">
          <div class="mb-3">
            <label class="form-label">Nom et Prénom</label>
            <input v-model="formulaireChauffeur.nom" type="text" class="form-control" placeholder="Rakoto Jean" required />
          </div>
          <div class="mb-3">
            <label class="form-label">Contact / Téléphone</label>
            <input v-model="formulaireChauffeur.contact" type="text" class="form-control" placeholder="034 12 345 67" required />
          </div>

          <div class="d-flex gap-2 mt-4">
            <button type="button" @click="modalChauffeurOuvert = false" class="btn btn-light w-50">Annuler</button>
            <button type="submit" class="btn btn-primary w-50">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modale Véhicule -->
    <div v-if="modalVehiculeOuvert" class="fond-modale">
      <div class="boite-modale" style="max-width: 480px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">{{ vehiculeEnCours ? 'Modifier Véhicule' : 'Nouveau Véhicule' }}</h5>
          <button @click="modalVehiculeOuvert = false" class="btn-close"></button>
        </div>

        <form @submit.prevent="enregistrerVehicule">
          <div class="mb-3">
            <label class="form-label">Matricule</label>
            <input v-model="formulaireVehicule.matricule" type="text" class="form-control" placeholder="1234 TBA" required />
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">Type de Véhicule</label>
              <select 
                v-model="formulaireVehicule.id_type_vehicule" 
                class="form-select" 
                required
                @change="surChangementTypeVehicule"
              >
                <option v-for="t in listeTypes" :key="t.id" :value="t.id">{{ t.nom }}</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Nombre de places</label>
              <input v-model="formulaireVehicule.nombre_places" type="number" class="form-control" min="1" required />
              <div class="form-text small">
                <span v-if="formulaireVehicule.id_type_vehicule">
                  Défaut : {{ getNombrePlacesParDefaut(formulaireVehicule.id_type_vehicule) }} places
                </span>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Chauffeur assigné (optionnel)</label>
            <select v-model="formulaireVehicule.id_chauffeur" class="form-select">
              <option value="">-- Aucun chauffeur --</option>
              <option v-for="c in listeChauffeurs" :key="c.id" :value="c.id">{{ c.nom }} ({{ c.contact }})</option>
            </select>
          </div>

          <div class="d-flex gap-2 mt-4">
            <button type="button" @click="modalVehiculeOuvert = false" class="btn btn-light w-50">Annuler</button>
            <button type="submit" class="btn btn-primary w-50">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modale Type de Véhicule avec Upload d'Image -->
    <div v-if="modalTypeOuvert" class="fond-modale">
      <div class="boite-modale" style="max-width: 440px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">{{ typeEnCours ? 'Modifier le type' : 'Nouveau type de véhicule' }}</h5>
          <button @click="modalTypeOuvert = false" class="btn-close"></button>
        </div>

        <form @submit.prevent="enregistrerType">
          <div class="mb-3">
            <label class="form-label fw-bold">Nom du type <span class="text-danger">*</span></label>
            <input
              v-model="formulaireType.nom"
              type="text"
              class="form-control"
              placeholder="ex: Sprinter, Sylver Class, Taxi-Brousse..."
              required
            />
          </div>

          <!-- Upload d'image -->
          <div class="mb-3">
            <label class="form-label fw-bold">Photo / Image du véhicule</label>
            <input
              type="file"
              accept="image/*"
              @change="gererChangementImage"
              class="form-control mb-2"
            />
            <div v-if="apercuImage" class="text-center p-2 border rounded bg-light">
              <div class="small text-muted mb-1">Aperçu :</div>
              <img :src="apercuImage" alt="Aperçu" class="img-fluid rounded shadow-sm" style="max-height: 140px; object-fit: cover;" />
            </div>
          </div>

          <div v-if="erreurType" class="alert alert-danger py-2">{{ erreurType }}</div>

          <div class="d-flex gap-2 mt-4">
            <button type="button" @click="modalTypeOuvert = false; erreurType = ''" class="btn btn-light w-50">Annuler</button>
            <button type="submit" class="btn btn-warning w-50 fw-bold" :disabled="enEnregistrementType">
              <span v-if="enEnregistrementType" class="spinner-border spinner-border-sm me-1"></span>
              Enregistrer
            </button>
          </div>
        </form>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
@import "@/assets/css/admin/layout.css";
@import "@/assets/css/admin/tables.css";
@import "@/assets/css/admin/modales.css";
@import "@/assets/css/admin/flotte.css";
</style>

