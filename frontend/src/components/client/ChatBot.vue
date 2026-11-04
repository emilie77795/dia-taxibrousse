<script setup>
import { ref, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { t, langueCourante } from '@/i18n'

const routeur = useRouter()
const ouvert = ref(false)
const texteSaisi = ref('')
const zoneDefilement = ref(null)

// Messages par défaut à l'ouverture
const messages = ref([
  {
    expediteur: 'bot',
    texte: t('bot_bienvenue'),
    liens: [
      { libelle: t('bot_lien_trajets'), url: '/client/trajets' },
      { libelle: t('bot_lien_tarifs'), url: '/client/tarifs' },
      { libelle: t('bot_lien_billets'), url: '/client/mes-reservations' }
    ]
  }
])

function basculerChatbot() {
  ouvert.value = !ouvert.value
  if (ouvert.value) {
    if (messages.value.length === 1 && messages.value[0].expediteur === 'bot') {
      messages.value[0].texte = t('bot_bienvenue')
      messages.value[0].liens = [
        { libelle: t('bot_lien_trajets'), url: '/client/trajets' },
        { libelle: t('bot_lien_tarifs'), url: '/client/tarifs' },
        { libelle: t('bot_lien_billets'), url: '/client/mes-reservations' }
      ]
    }
    faireDefilerBas()
  }
}

function faireDefilerBas() {
  nextTick(() => {
    if (zoneDefilement.value) {
      zoneDefilement.value.scrollTop = zoneDefilement.value.scrollHeight
    }
  })
}

function repondreIntelligemment(texte) {
  const messageMin = texte.toLowerCase()
  const estAnglais = langueCourante.value === 'en'

  // 1. Demande de réservation / trajets / date / heure
  if (messageMin.includes('reserver') || messageMin.includes('réserv') || messageMin.includes('billet') || messageMin.includes('place') || messageMin.includes('partir') || messageMin.includes('voyage') || messageMin.includes('book') || messageMin.includes('ticket') || messageMin.includes('seat') || messageMin.includes('trip')) {
    return {
      texte: estAnglais
        ? 'Looking to book a trip? Browse our departures by date, time and vehicle type. Select your seat directly on the live bus map!'
        : 'Vous souhaitez réserver ? Vous pouvez consulter tous nos départs par date, horaire et type de véhicule. La sélection des places se fait en direct sur le plan du véhicule !',
      liens: [
        { libelle: estAnglais ? 'View trips & book' : 'Voir les trajets et réserver', url: '/client/trajets' }
      ]
    }
  }

  // 2. Demande de prix / tarif / fourchette
  if (messageMin.includes('prix') || messageMin.includes('tarif') || messageMin.includes('combien') || messageMin.includes('cout') || messageMin.includes('coût') || messageMin.includes('price') || messageMin.includes('cost') || messageMin.includes('fare')) {
    let detailPrix = estAnglais
      ? 'Our official price list:\n• Tana ➔ Tamatave: Bowing (35,000 Ar), Sprinter (45,000 Ar), Sylver Class (50,000 Ar)\n• Tana ➔ Majunga: Bowing (45,000 Ar), Sprinter (75,000 Ar).'
      : 'Nos fourchettes de prix officielles : \n' +
        '• Tana ➔ Tamatave : Bowing (35 000 Ar), Sprinter (45 000 Ar), Sylver Class (50 000 Ar)\n' +
        '• Tana ➔ Majunga : Bowing (45 000 Ar), Sprinter (75 000 Ar).'
    return {
      texte: detailPrix,
      liens: [
        { libelle: estAnglais ? 'View price table' : 'Voir la grille des tarifs (A ➔ B)', url: '/client/tarifs' }
      ]
    }
  }

  // 3. Remboursement
  if (messageMin.includes('rembours') || messageMin.includes('annul') || messageMin.includes('refund') || messageMin.includes('cancel')) {
    return {
      texte: estAnglais
        ? 'Refund rule: Requests are accepted at least 2 days before departure. You can request a full or partial refund (e.g. 2 seats out of 5). No refunds on the day of departure or the day before.'
        : 'Règle de remboursement : Votre demande est acceptée au plus tard 2 jours avant la date du départ. Le remboursement peut être total ou partiel (ex: 2 places sur 5). Aucun remboursement possible le jour même ou la veille.',
      liens: [
        { libelle: estAnglais ? 'Manage my refunds' : 'Gérer mes remboursements', url: '/client/mes-reservations' }
      ]
    }
  }

  // 4. Report de date
  if (messageMin.includes('report') || messageMin.includes('decal') || messageMin.includes('décal') || messageMin.includes('changer date') || messageMin.includes('reschedule') || messageMin.includes('change date')) {
    return {
      texte: estAnglais
        ? 'Reschedule rule: You can change your date at least 2 days before departure for the SAME vehicle type (Bowing, Sprinter or Sylver Class) at no extra charge.'
        : 'Règle de report : Le report est possible au moins 2 jours avant le départ. Il doit porter sur la totalité de la réservation, pour un trajet ayant le MÊME type de véhicule (Bowing, Sprinter ou Sylver Class) sans supplément.',
      liens: [
        { libelle: estAnglais ? 'Reschedule a booking' : 'Reporter une réservation', url: '/client/mes-reservations' }
      ]
    }
  }

  // 5. Paiement / Mobile Money / Avance
  if (messageMin.includes('paiement') || messageMin.includes('payer') || messageMin.includes('mvola') || messageMin.includes('orange') || messageMin.includes('airtel') || messageMin.includes('avance') || messageMin.includes('espece') || messageMin.includes('pay') || messageMin.includes('deposit') || messageMin.includes('cash')) {
    return {
      texte: estAnglais
        ? 'Payment methods: MVola, Orange Money and Airtel Money. You can pay 100% online or pay a 50% deposit online and the remainder in cash at the bus station checkpoint.'
        : 'Modes de paiement acceptés : MVola, Orange Money et Airtel Money. Vous pouvez régler la totalité ou payer une avance de 50% en ligne. Le solde restant se règle en espèces au checkpoint avant d\'embarquer.',
      liens: [
        { libelle: estAnglais ? 'Book with 50% deposit' : 'Réserver avec paiement 50%', url: '/client/trajets' }
      ]
    }
  }

  // 6. Checkpoint / Embarquement
  if (messageMin.includes('checkpoint') || messageMin.includes('embarqu') || messageMin.includes('valise') || messageMin.includes('bagage') || messageMin.includes('luggage') || messageMin.includes('bag') || messageMin.includes('station')) {
    return {
      texte: estAnglais
        ? 'Check-in starts 1 hour before departure at the DIA bus station. Present your digital ticket, pay any cash balance, and board with peace of mind.'
        : 'Le checkpoint commence 1 heure avant le départ à la station DIA. Présentez votre ticket numérique, réglez votre solde éventuel en espèces et installez vos bagages en toute sérénité.',
      liens: [
        { libelle: estAnglais ? 'My digital tickets' : 'Mes tickets numériques', url: '/client/mes-reservations' }
      ]
    }
  }

  // 7. Contact / Numéro d'agence / Aide
  if (messageMin.includes('numero') || messageMin.includes('numéro') || messageMin.includes('contact') || messageMin.includes('agence') || messageMin.includes('telephone') || messageMin.includes('téléphone') || messageMin.includes('aide') || messageMin.includes('help') || messageMin.includes('phone')) {
    return {
      texte: estAnglais
        ? 'DIA TaxiBrousse Agency Contact:\n• Phone: +261 34 00 123 45 / +261 32 00 123 45\n• Email: contact@dia-taxibrousse.mg\n• Bus Station: Antananarivo & Toamasina\n• Hours: Open 24/7.'
        : 'Coordonnées de l\'agence DIA TaxiBrousse :\n• Téléphone : +261 34 00 123 45 / +261 32 00 123 45\n• Email : contact@dia-taxibrousse.mg\n• Guichet : Gare routière d\'Antananarivo & Toamasina\n• Horaires : Ouvert 24h/24 et 7j/7.',
      liens: [
        { libelle: estAnglais ? 'Go to Home' : 'Aller à l\'accueil', url: '/client/dashboard' }
      ]
    }
  }

  // Par défaut
  return {
    texte: estAnglais
      ? 'I did not quite understand your question. Here are some quick actions you can take:'
      : 'Je n\'ai pas bien compris votre question. Voici les actions rapides que vous pouvez effectuer :',
    liens: [
      { libelle: t('bot_lien_trajets'), url: '/client/trajets' },
      { libelle: t('bot_lien_tarifs'), url: '/client/tarifs' },
      { libelle: t('bot_lien_billets'), url: '/client/mes-reservations' }
    ]
  }
}

function envoyerMessage() {
  const texte = texteSaisi.value.trim()
  if (!texte) return

  // Message de l'utilisateur
  messages.value.push({
    expediteur: 'utilisateur',
    texte: texte
  })
  texteSaisi.value = ''
  faireDefilerBas()

  // Réponse automatique après un petit délai réaliste
  setTimeout(() => {
    const reponse = repondreIntelligemment(texte)
    messages.value.push({
      expediteur: 'bot',
      texte: reponse.texte,
      liens: reponse.liens
    })
    faireDefilerBas()
  }, 400)
}

function naviguerVers(url) {
  ouvert.value = false
  routeur.push(url)
}
</script>

<template>
  <!-- Bouton rond flottant -->
  <button 
    class="chatbot-bouton-flottant" 
    @click="basculerChatbot" 
    :title="t('bot_titre')"
  >
    <img src="/icons/autres/chatbot.png" alt="Chat" style="width: 26px; height: 26px; filter: brightness(0) invert(1);" />
  </button>

  <!-- Boîte de dialogue du ChatBot -->
  <div v-if="ouvert" class="chatbot-boite">
    <!-- En-tête -->
    <div class="chatbot-entete">
      <div class="chatbot-titre">
        <span class="chatbot-statut-en-ligne"></span>
        <span>{{ t('bot_titre') }}</span>
      </div>
      <button @click="basculerChatbot" class="chatbot-fermer" :title="t('bot_fermer')">&times;</button>
    </div>

    <!-- Messages défilants -->
    <div ref="zoneDefilement" class="chatbot-messages">
      <div 
        v-for="(msg, index) in messages" 
        :key="index"
        class="message-bulle"
        :class="msg.expediteur === 'bot' ? 'message-bot' : 'message-utilisateur'"
      >
        <div style="white-space: pre-line;">{{ msg.texte }}</div>

        <!-- Liens d'action rapide -->
        <div v-if="msg.liens && msg.liens.length > 0" class="message-liens">
          <a 
            v-for="(lien, lIndex) in msg.liens" 
            :key="lIndex"
            href="#"
            @click.prevent="naviguerVers(lien.url)"
            class="lien-chatbot"
          >
            <span>{{ lien.libelle }}</span>
            <img src="/icons/autres/caret-down.png" alt="" class="icone-caret icone-caret-droite ms-1" />
          </a>
        </div>
      </div>
    </div>

    <!-- Zone de saisie -->
    <form @submit.prevent="envoyerMessage" class="chatbot-saisie-zone">
      <input 
        id="chatbot-question"
        v-model="texteSaisi" 
        type="text" 
        :placeholder="t('bot_placeholder')" 
        :aria-label="t('bot_placeholder')"
        class="chatbot-saisie-input" 
      />
      <button type="submit" class="chatbot-saisie-bouton">{{ t('bot_envoyer') }}</button>
    </form>
  </div>
</template>

<style scoped>
@import "@/assets/css/client/chatbot.css";
</style>
