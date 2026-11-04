<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Paiement;
use App\Entity\Place;
use App\Entity\Reservation;
use App\Entity\ReservationPlace;
use App\Entity\Tarif;
use App\Entity\Trajet;
use App\Entity\Utilisateur;
use App\Repository\ChauffeurRepository;
use App\Repository\ReservationRepository;
use App\Repository\TrajetRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\VehiculeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ReservationController extends AbstractController
{
    #[Route('/api/reservations', name: 'api_reservations_list', methods: ['GET'])]
    public function list(Request $request, ReservationRepository $repo): JsonResponse
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $isAdminRole = in_array($user->getRole(), ['administrateur', 'super_admin', 'gerant']);
        $seulementMoi = $request->query->getBoolean('mine', false) 
            || $request->query->get('scope') === 'mine' 
            || $request->query->get('contexte') === 'client';

        $reservations = ($isAdminRole && !$seulementMoi)
            ? $repo->findBy([], ['id' => 'DESC'])
            : $repo->findBy(['utilisateur' => $user], ['id' => 'DESC']);

        return $this->json($this->formaterReservations($reservations));
    }

    #[Route('/api/mes-reservations', name: 'api_mes_reservations_list', methods: ['GET'])]
    public function mesReservations(ReservationRepository $repo): JsonResponse
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Renvoie UNIQUEMENT les réservations du compte actuellement connecté, même pour un admin
        $reservations = $repo->findBy(['utilisateur' => $user], ['id' => 'DESC']);

        return $this->json($this->formaterReservations($reservations));
    }

    #[Route('/api/reservations/{id}', name: 'api_reservations_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, ReservationRepository $repo): JsonResponse
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $reservation = $repo->find($id);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $isAdminRole = in_array($user->getRole(), ['administrateur', 'super_admin', 'gerant']);
        if ($reservation->getUtilisateur()?->getId() !== $user->getId() && !$isAdminRole) {
            return $this->json(['error' => 'Accès refusé à cette réservation.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $formatted = $this->formaterReservations([$reservation]);
        return $this->json($formatted[0] ?? null);
    }

    private function formaterReservations(array $reservations): array
    {
        $maintenant = new \DateTimeImmutable('today');
        $deuxJoursPlusTard = $maintenant->modify('+2 days');

        return array_map(function (Reservation $r) use ($deuxJoursPlusTard) {
            $trajet = $r->getTrajet();
            $paiement = $r->getPaiement();
            $vehicule = $trajet?->getVehicule();

            $placesNumeros = [];
            foreach ($r->getReservationPlaces() as $rp) {
                if ($rp->getPlace() && $rp->getPlace()->getNumeroPlace()) {
                    $placesNumeros[] = $rp->getPlace()->getNumeroPlace();
                }
            }
            sort($placesNumeros);

            $montantTotal = (float)$r->getMontantTotal();
            $montantPaye = $paiement ? (float)$paiement->getMontant() : $montantTotal;
            $montantReste = max(0, $montantTotal - $montantPaye);

            $dateDepart = $trajet?->getDate();
            $statutRes = strtolower($r->getStatut() ?? 'confirme');
            $peutModifier = false;
            // Ne peut demander remboursement ou reporter que si >= 2 jours ET non déjà remboursé/reporté/embarqué/en attente
            if ($dateDepart && !in_array($statutRes, ['rembourse', 'reporte', 'annule', 'embarque', 'demande_remboursement'])) {
                $peutModifier = ($dateDepart >= $deuxJoursPlusTard);
            }

            $statutTexte = match($statutRes) {
                'demande_remboursement' => 'En attente remboursement',
                'confirme' => 'Confirmé (100%)',
                'avance' => 'Avance 50%',
                'reporte' => 'Reporté',
                'rembourse' => 'Remboursé',
                'embarque' => 'Embarqué',
                default => ucfirst($statutRes)
            };

            return [
                'id'              => $r->getId(),
                'ticket'          => $r->getNumeroTicket(),
                'id_trajet'       => $trajet?->getId(),
                'trajet'          => $trajet ? $trajet->getVilleDepart() . ' ➔ ' . $trajet->getVilleArrivee() : '—',
                'ville_depart'    => $trajet?->getVilleDepart(),
                'ville_arrivee'   => $trajet?->getVilleArrivee(),
                'date'            => $trajet?->getDate()?->format('Y-m-d'),
                'horaire'         => $trajet?->getHoraire()?->format('H:i'),
                'montant_total'   => $montantTotal,
                'montant_total_texte' => number_format($montantTotal, 0, '', ' ') . ' Ar',
                'montant_paye'    => $montantPaye,
                'montant_paye_texte' => number_format($montantPaye, 0, '', ' ') . ' Ar',
                'montant_reste'   => $montantReste,
                'montant_reste_texte' => number_format($montantReste, 0, '', ' ') . ' Ar',
                'places'          => $placesNumeros,
                'nombre_places'   => count($placesNumeros) > 0 ? count($placesNumeros) : 1,
                'statut'          => $statutRes,
                'statut_texte'    => $statutTexte,
                'user'            => $r->getUtilisateur()?->getEmail(),
                'nom_client'      => trim(($r->getUtilisateur()?->getPrenom() ?? '') . ' ' . ($r->getUtilisateur()?->getNom() ?? '')) ?: ($r->getUtilisateur()?->getEmail() ?? '—'),
                'telephone_client'=> $r->getUtilisateur()?->getTelephone() ?? $paiement?->getNumeroTelephone(),
                'source'          => ($paiement?->getOperateur() === 'Espèces') ? 'guichet' : 'en_ligne',
                'dateReservation' => $r->getDate()?->format('Y-m-d'),
                'operateur'       => $paiement?->getOperateur(),
                'telephone'       => $paiement?->getNumeroTelephone(),
                'statutPaiement'  => $paiement?->getStatut() ?? 'paye',
                'type_vehicule'   => $vehicule?->getTypeVehicule()?->getNom() ?? 'Standard',
                'id_type_vehicule'=> $vehicule?->getTypeVehicule()?->getId(),
                'matricule'       => $vehicule?->getMatricule() ?? 'TBA',
                'chauffeur'       => $vehicule?->getChauffeur()?->getNom() ?? 'Assigné',
                'peut_rembourser' => $peutModifier && $statutRes !== 'demande_remboursement',
                'peut_reporter'   => $peutModifier && $statutRes !== 'demande_remboursement',
                'en_attente_remboursement' => ($statutRes === 'demande_remboursement'),
                'est_embarque'    => ($statutRes === 'embarque'),
            ];
        }, $reservations);
    }

    #[Route('/api/reservations', name: 'api_reservations_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['id_trajet']) || (empty($data['nombre_places']) && empty($data['places']))) {
            return $this->json(['error' => 'Données incomplètes.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $trajet = $em->getRepository(Trajet::class)->find($data['id_trajet']);
        if (!$trajet) {
            return $this->json(['error' => 'Trajet introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $vehicule = $trajet->getVehicule();
        if (!$vehicule) {
            return $this->json(['error' => 'Aucun véhicule associé à ce trajet.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $tarif = $em->getRepository(Tarif::class)->findOneBy([
            'trajet' => $trajet,
            'typeVehicule' => $vehicule->getTypeVehicule(),
        ]);
        if (!$tarif) {
            $tarif = $em->getRepository(Tarif::class)->findOneBy(['trajet' => $trajet]);
        }
        if (!$tarif) {
            // Recherche par itinéraire global (VilleDépart -> VilleArrivée)
            $tousTarifs = $em->getRepository(Tarif::class)->findAll();
            foreach ($tousTarifs as $tf) {
                $trj = $tf->getTrajet();
                if ($trj && 
                    strcasecmp($trj->getVilleDepart(), $trajet->getVilleDepart()) === 0 && 
                    strcasecmp($trj->getVilleArrivee(), $trajet->getVilleArrivee()) === 0) {
                    if ($tf->getTypeVehicule()?->getId() === $vehicule->getTypeVehicule()?->getId()) {
                        $tarif = $tf;
                        break;
                    }
                }
            }
            if (!$tarif) {
                foreach ($tousTarifs as $tf) {
                    $trj = $tf->getTrajet();
                    if ($trj && 
                        strcasecmp($trj->getVilleDepart(), $trajet->getVilleDepart()) === 0 && 
                        strcasecmp($trj->getVilleArrivee(), $trajet->getVilleArrivee()) === 0) {
                        $tarif = $tf;
                        break;
                    }
                }
            }
        }

        $prixUnitaire = $tarif ? (float)$tarif->getPrix() : 20000.0;
        $placesChoisies = !empty($data['places']) && is_array($data['places']) ? $data['places'] : [1];
        $nbPlaces = count($placesChoisies);
        $montantTotal = $nbPlaces * $prixUnitaire;

        $typePaiement = strtolower($data['type_paiement'] ?? 'total');
        $minimumAvance = round($montantTotal / 2, 2); // Règle métier : minimum 50% du montant total
        $montantSoumis = isset($data['montant_paye']) ? (float)$data['montant_paye'] : (isset($data['montant']) ? (float)$data['montant'] : null);

        // Validation stricte du type de paiement et du montant d'avance
        if ($typePaiement === 'avance') {
            if ($montantSoumis !== null) {
                // Si l'utilisateur envoie un montant inférieur à 50% du total, on rejette
                if ($montantSoumis < $minimumAvance) {
                    return $this->json([
                        'error' => sprintf(
                            'Acompte insuffisant. L\'avance minimale autorisée est de 50%% du total, soit %s Ar (montant total : %s Ar).',
                            number_format($minimumAvance, 0, '', ' '),
                            number_format($montantTotal, 0, '', ' ')
                        )
                    ], JsonResponse::HTTP_BAD_REQUEST);
                }

                if ($montantSoumis >= $montantTotal) {
                    // Si le montant soumis couvre ou dépasse la totalité, c'est un paiement total
                    $montantPaye = $montantTotal;
                    $statutReservation = 'confirme';
                    $typePaiement = 'total';
                } else {
                    $montantPaye = $montantSoumis;
                    $statutReservation = 'avance';
                }
            } else {
                // Pas de montant explicite fourni : calcul automatique des 50%
                $montantPaye = $minimumAvance;
                $statutReservation = 'avance';
            }
        } elseif ($typePaiement === 'total' || $typePaiement === 'paye') {
            if ($montantSoumis !== null && $montantSoumis < $montantTotal) {
                return $this->json([
                    'error' => sprintf(
                        'Pour un paiement intégral, le montant doit être de %s Ar. Pour une avance, veuillez choisir le mode avance (minimum 50%% : %s Ar).',
                        number_format($montantTotal, 0, '', ' '),
                        number_format($minimumAvance, 0, '', ' ')
                    )
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            $montantPaye = $montantTotal;
            $statutReservation = 'confirme';
            $typePaiement = 'total';
        } else {
            return $this->json(['error' => 'Type de paiement invalide (choisir "total" ou "avance").'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $ticketNum = 'T' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);

        $reservation = new Reservation();
        $reservation->setNumeroTicket($ticketNum);
        $reservation->setUtilisateur($user);
        $reservation->setTrajet($trajet);
        $reservation->setDate(new \DateTimeImmutable());
        $reservation->setMontantTotal((string)$montantTotal);
        $reservation->setStatut($statutReservation);

        $em->persist($reservation);

        foreach ($placesChoisies as $numPlace) {
            $place = $em->getRepository(Place::class)->findOneBy([
                'vehicule' => $vehicule,
                'numeroPlace' => (int)$numPlace,
            ]);

            if (!$place) {
                $place = new Place();
                $place->setVehicule($vehicule);
                $place->setNumeroPlace((int)$numPlace);
                $place->setStatut('occupe');
                $em->persist($place);
            } else {
                $place->setStatut('occupe');
            }

            $rp = new ReservationPlace();
            $rp->setReservation($reservation);
            $rp->setPlace($place);
            $em->persist($rp);
        }

        $operateur = $data['operateur'] ?? 'MVola';
        $numeroTel = $data['numero_telephone'] ?? ($user->getTelephone() ?? '');
        $paiement = new Paiement();
        $paiement->setReservation($reservation);
        $paiement->setOperateur($operateur);
        $paiement->setNumeroTelephone($numeroTel);
        $paiement->setMontant((string)$montantPaye);
        $paiement->setStatut($typePaiement === 'avance' ? 'avance' : 'paye');

        $em->persist($paiement);
        $em->flush();

        return $this->json([
            'message'      => 'Réservation enregistrée avec succès.',
            'ticket'       => $ticketNum,
            'id'           => $reservation->getId(),
            'prixUnitaire' => $prixUnitaire,
            'montantTotal' => $montantTotal,
            'montantPaye'  => $montantPaye,
            'montantReste' => max(0, $montantTotal - $montantPaye),
            'typePaiement' => $typePaiement,
            'places'       => $placesChoisies,
            'operateur'    => $operateur,
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/admin/guichet', name: 'api_admin_guichet_create', methods: ['POST'])]
    public function reservationGuichet(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        try {
            /** @var Utilisateur|null $user */
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'Non autorisé.'], JsonResponse::HTTP_UNAUTHORIZED);
            }

            $isAdminRole = in_array($user->getRole(), ['administrateur', 'super_admin', 'gerant']);
            if (!$isAdminRole) {
                return $this->json(['error' => 'Accès refusé. Réservé aux administrateurs.'], JsonResponse::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            if (empty($data['id_trajet']) || empty($data['places']) || !is_array($data['places'])) {
                return $this->json(['error' => 'Veuillez sélectionner un trajet et au moins une place.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $nomClient = trim($data['nom_client'] ?? '');
            $telephoneClient = trim($data['telephone_client'] ?? '');

            if ($nomClient === '' || $telephoneClient === '') {
                return $this->json(['error' => 'Veuillez renseigner le nom et le numéro de téléphone du client.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $trajet = $em->getRepository(Trajet::class)->find($data['id_trajet']);
            if (!$trajet) {
                return $this->json(['error' => 'Trajet introuvable.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $vehicule = $trajet->getVehicule();
            if (!$vehicule) {
                return $this->json(['error' => 'Aucun véhicule associé à ce trajet.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            // Synchronisation de sécurité des séquences pour éviter les collisions d'identifiants
            try {
                $conn = $em->getConnection();
                $conn->executeStatement("SELECT setval('reservation_id_seq', (SELECT COALESCE(MAX(id), 0) + 10 FROM reservation))");
                $conn->executeStatement("SELECT setval('paiement_id_seq', (SELECT COALESCE(MAX(id), 0) + 10 FROM paiement))");
                $conn->executeStatement("SELECT setval('reservation_place_id_seq', (SELECT COALESCE(MAX(id), 0) + 10 FROM reservation_place))");
                $conn->executeStatement("SELECT setval('utilisateur_id_seq', (SELECT COALESCE(MAX(id), 0) + 10 FROM utilisateur))");
            } catch (\Throwable $seqEx) {
                // Ignore
            }

            // 1. VÉRIFICATION ANTI-DOUBLES RÉSERVATIONS EN TEMPS RÉEL
            $placesOccupeesNumeros = [];
            foreach ($trajet->getReservations() as $res) {
                if (!in_array(strtolower($res->getStatut() ?? ''), ['rembourse', 'reporte', 'annule'])) {
                    foreach ($res->getReservationPlaces() as $rp) {
                        $p = $rp->getPlace();
                        if ($p && $p->getNumeroPlace()) {
                            $placesOccupeesNumeros[] = $p->getNumeroPlace();
                        }
                    }
                }
            }
            $placesOccupeesNumeros = array_unique($placesOccupeesNumeros);

            $placesChoisies = array_map('intval', $data['places']);
            sort($placesChoisies);

            foreach ($placesChoisies as $numPlace) {
                if (in_array($numPlace, $placesOccupeesNumeros, true)) {
                    return $this->json([
                        'error' => sprintf('La place N° %d est déjà réservée pour ce départ. Veuillez choisir une autre place disponible.', $numPlace)
                    ], JsonResponse::HTTP_CONFLICT);
                }
            }

            // 2. RECHERCHE DU TARIF DU TRAJET
            $tarif = $em->getRepository(Tarif::class)->findOneBy([
                'trajet' => $trajet,
                'typeVehicule' => $vehicule->getTypeVehicule(),
            ]);
            if (!$tarif) {
                $tarif = $em->getRepository(Tarif::class)->findOneBy(['trajet' => $trajet]);
            }
            if (!$tarif) {
                $tousTarifs = $em->getRepository(Tarif::class)->findAll();
                foreach ($tousTarifs as $tf) {
                    $trj = $tf->getTrajet();
                    if ($trj && 
                        strcasecmp($trj->getVilleDepart(), $trajet->getVilleDepart()) === 0 && 
                        strcasecmp($trj->getVilleArrivee(), $trajet->getVilleArrivee()) === 0) {
                        if ($tf->getTypeVehicule()?->getId() === $vehicule->getTypeVehicule()?->getId()) {
                            $tarif = $tf;
                            break;
                        }
                    }
                }
            }

            $prixUnitaire = $tarif ? (float)$tarif->getPrix() : 20000.0;
            $montantTotal = count($placesChoisies) * $prixUnitaire;

            // 3. GÉNÉRATION NUMÉRO DE TICKET UNIQUE
            do {
                $ticketNum = 'T' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
                $existant = $em->getRepository(Reservation::class)->findOneBy(['numeroTicket' => $ticketNum]);
            } while ($existant !== null);

            // 4. RÉCUPÉRATION OU ENREGISTREMENT DU VOYAGEUR DANS LA TABLE UTILISATEUR
            $userRepo = $em->getRepository(Utilisateur::class);
            $clientUser = $userRepo->findOneBy(['telephone' => $telephoneClient]);
            if (!$clientUser) {
                $partiesNom = explode(' ', $nomClient, 2);
                $prenom = $partiesNom[0] ?? 'Passager';
                $nom = $partiesNom[1] ?? ($partiesNom[0] ?? 'Guichet');

                $cleanTel = preg_replace('/[^0-9]/', '', $telephoneClient);
                $emailGenere = 'voyageur.' . ($cleanTel ?: time()) . '@passager.dia.mg';
                if ($userRepo->findOneBy(['email' => $emailGenere])) {
                    $emailGenere = 'voyageur.' . ($cleanTel ?: time()) . '.' . random_int(100, 999) . '@passager.dia.mg';
                }

                $clientUser = new Utilisateur();
                $clientUser->setNom($nom);
                $clientUser->setPrenom($prenom);
                $clientUser->setTelephone($telephoneClient);
                $clientUser->setEmail($emailGenere);
                $clientUser->setLieu('Guichet');
                $clientUser->setRole('voyageur');
                $clientUser->setMotDePasse(password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT));

                $em->persist($clientUser);
                $em->flush();
            }

            // CRÉATION DE LA RÉSERVATION (SCHÉMA STRICT SANS COLONNES SUPPLÉMENTAIRES)
            $reservation = new Reservation();
            $reservation->setNumeroTicket($ticketNum);
            $reservation->setUtilisateur($clientUser);
            $reservation->setTrajet($trajet);
            $reservation->setDate(new \DateTimeImmutable());
            $reservation->setMontantTotal((string)$montantTotal);
            $reservation->setStatut('confirme');

            $em->persist($reservation);

            // 5. OCCUPATION DES PLACES
            foreach ($placesChoisies as $numPlace) {
                $place = $em->getRepository(Place::class)->findOneBy([
                    'vehicule' => $vehicule,
                    'numeroPlace' => (int)$numPlace,
                ]);

                if (!$place) {
                    $place = new Place();
                    $place->setVehicule($vehicule);
                    $place->setNumeroPlace((int)$numPlace);
                    $place->setStatut('occupe');
                    $em->persist($place);
                } else {
                    $place->setStatut('occupe');
                }

                $rp = new ReservationPlace();
                $rp->setReservation($reservation);
                $rp->setPlace($place);
                $em->persist($rp);
            }

            // 6. ENREGISTREMENT DU PAIEMENT EN ESPÈCES
            $paiement = new Paiement();
            $paiement->setReservation($reservation);
            $paiement->setOperateur('Espèces');
            $paiement->setNumeroTelephone($telephoneClient);
            $paiement->setMontant((string)$montantTotal);
            $paiement->setStatut('paye');
            $em->persist($paiement);

            // Notification administrateur pour le tableau de bord (non-bloquant)
            try {
                $admins = $em->getRepository(Utilisateur::class)->findBy(['role' => ['administrateur', 'admin', 'super_admin']]);
                $msgGuichet = sprintf(
                    'Réservation Guichet #%s par l\'agent pour %s (Tél: %s) - %d place(s) payée(s) en espèces (%s Ar). Trajet : %s ➔ %s (%s à %s).',
                    $ticketNum,
                    $nomClient,
                    $telephoneClient,
                    count($placesChoisies),
                    number_format($montantTotal, 0, '', ' '),
                    $trajet->getVilleDepart(),
                    $trajet->getVilleArrivee(),
                    $trajet->getDate()?->format('d/m/Y') ?? '',
                    $trajet->getHoraire()?->format('H:i') ?? ''
                );
                foreach ($admins as $adm) {
                    $notif = new Notification();
                    $notif->setUtilisateur($adm);
                    $notif->setType('nouvelle_reservation');
                    $notif->setMessage($msgGuichet);
                    $notif->setStatut('non_lu');
                    $em->persist($notif);
                }
            } catch (\Throwable $notifEx) {
                // Non-bloquant
            }

            $em->flush();

            // 7. RETOUR DU TICKET COMPLET
            return $this->json([
                'message'             => 'Réservation au guichet enregistrée avec succès.',
                'id'                  => $reservation->getId(),
                'ticket'              => $ticketNum,
                'nom_client'          => $nomClient,
                'telephone_client'    => $telephoneClient,
                'source'              => 'guichet',
                'places'              => $placesChoisies,
                'nombre_places'       => count($placesChoisies),
                'prix_unitaire'       => $prixUnitaire,
                'montant_total'       => $montantTotal,
                'montant_total_texte' => number_format($montantTotal, 0, '', ' ') . ' Ar',
                'montant_paye'        => $montantTotal,
                'montant_reste'       => 0,
                'operateur'           => 'Espèces',
                'date_reservation'    => $reservation->getDate()?->format('Y-m-d H:i:s'),
                'trajet'              => $trajet->getVilleDepart() . ' ➔ ' . $trajet->getVilleArrivee(),
                'ville_depart'        => $trajet->getVilleDepart(),
                'ville_arrivee'       => $trajet->getVilleArrivee(),
                'date'                => $trajet->getDate()?->format('Y-m-d'),
                'horaire'             => $trajet->getHoraire()?->format('H:i'),
                'type_vehicule'       => $vehicule->getTypeVehicule()?->getNom() ?? 'Standard',
                'matricule'           => $vehicule->getMatricule() ?? 'TBA',
                'chauffeur'           => $vehicule->getChauffeur()?->getNom() ?? 'Assigné',
            ], JsonResponse::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Erreur lors de l\'enregistrement : ' . $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/reservations/{id}/demander-remboursement', name: 'api_reservations_demander_remboursement', methods: ['POST'])]
    public function demanderRemboursement(
        int $id,
        Request $request,
        ReservationRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $reservation = $repo->find($id);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Vérifier que la réservation appartient au client connecté
        $estAdmin = in_array($user->getRole(), ['administrateur', 'super_admin', 'gerant']);
        if ($reservation->getUtilisateur()?->getId() !== $user->getId() && !$estAdmin) {
            return $this->json(['error' => 'Accès refusé à cette réservation.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $trajet = $reservation->getTrajet();
        $dateDepart = $trajet?->getDate();
        $deuxJoursPlusTard = (new \DateTimeImmutable('today'))->modify('+2 days');

        // RÈGLE MÉTIER STRICTE : Au moins 2 jours (48h) avant la date de départ
        if ($dateDepart && $dateDepart < $deuxJoursPlusTard) {
            return $this->json([
                'error' => 'Le délai de 2 jours avant le départ est dépassé. Aucun remboursement n\'est autorisé.'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $statutActuel = strtolower($reservation->getStatut() ?? '');
        if (in_array($statutActuel, ['rembourse', 'demande_remboursement', 'reporte', 'embarque'])) {
            return $this->json([
                'error' => 'Ce billet n\'est plus éligible ou une demande de remboursement est déjà en cours d\'examen.'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Passage au statut 'demande_remboursement' (en attente de l'administrateur)
        $reservation->setStatut('demande_remboursement');

        // Notification envoyée aux administrateurs
        $admins = $em->getRepository(Utilisateur::class)->findBy(['role' => ['administrateur', 'admin', 'super_admin']]);
        $client = $reservation->getUtilisateur();
        $nomClient = trim(($client?->getPrenom() ?? '') . ' ' . ($client?->getNom() ?? '')) ?: ($client?->getEmail() ?? 'Client');
        $telClient = $client?->getTelephone() ?? '—';
        $ticket = $reservation->getNumeroTicket();
        $montant = number_format((float)$reservation->getMontantTotal(), 0, '', ' ') . ' Ar';
        $trajetTexte = $trajet ? $trajet->getVilleDepart() . ' ➔ ' . $trajet->getVilleArrivee() : 'Trajet';
        $dateTexte = $trajet?->getDate()?->format('d/m/Y') ?? '';
        $heureTexte = $trajet?->getHoraire()?->format('H:i') ?? '';

        $messageNotif = "Demande de remboursement pour le ticket #{$ticket} ({$nomClient}, Tél: {$telClient}) d'un montant de {$montant}. Trajet : {$trajetTexte} du {$dateTexte} à {$heureTexte}.";

        foreach ($admins as $admin) {
            $notif = new Notification();
            $notif->setUtilisateur($admin);
            $notif->setType('demande_remboursement');
            $notif->setMessage($messageNotif);
            $notif->setStatut('non_lu');
            $em->persist($notif);
        }

        $em->flush();

        return $this->json([
            'message' => 'Votre demande de remboursement a bien été transmise à l\'administration pour validation.',
            'statut'  => 'demande_remboursement',
            'ticket'  => $ticket,
        ]);
    }

    #[Route('/api/reservations/{id}/rembourser', name: 'api_reservations_rembourser', methods: ['PATCH', 'POST'])]
    public function rembourser(
        int $id,
        Request $request,
        ReservationRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var Utilisateur|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser || !in_array($currentUser->getRole(), ['administrateur', 'super_admin', 'gerant'])) {
            return $this->json([
                'error' => 'Action non autorisée. Le remboursement doit obligatoirement être validé par un administrateur.'
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $reservation = $repo->find($id);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $trajet = $reservation->getTrajet();
        $dateDepart = $trajet?->getDate();
        $deuxJoursPlusTard = (new \DateTimeImmutable('today'))->modify('+2 days');

        // RÈGLE MÉTIER : Vérification du délai de 2 jours avant le départ
        if ($dateDepart && $dateDepart < $deuxJoursPlusTard) {
            return $this->json([
                'error' => 'Le délai de 2 jours avant la date de départ est dépassé pour ce remboursement.'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $nbPlacesTotal = $reservation->getReservationPlaces()->count();
        $nbARembourser = isset($data['places_a_rembourser']) ? (int)$data['places_a_rembourser'] : $nbPlacesTotal;

        if ($nbARembourser <= 0 || $nbARembourser > $nbPlacesTotal) {
            $nbARembourser = $nbPlacesTotal;
        }

        $prixUnitaire = $nbPlacesTotal > 0 ? (float)$reservation->getMontantTotal() / $nbPlacesTotal : 0;
        $montantRembourse = $nbARembourser * $prixUnitaire;

        if ($nbARembourser === $nbPlacesTotal) {
            $reservation->setStatut('rembourse');
            foreach ($reservation->getReservationPlaces() as $rp) {
                if ($rp->getPlace()) {
                    $rp->getPlace()->setStatut('disponible');
                }
                $em->remove($rp);
            }
            if ($reservation->getPaiement()) {
                $reservation->getPaiement()->setStatut('rembourse');
            }
        } else {
            $restantPlaces = $nbPlacesTotal - $nbARembourser;
            $nouveauTotal = $restantPlaces * $prixUnitaire;
            $reservation->setMontantTotal((string)$nouveauTotal);
            $reservation->setStatut('confirme');

            $compteur = 0;
            foreach ($reservation->getReservationPlaces() as $rp) {
                if ($compteur < $nbARembourser) {
                    if ($rp->getPlace()) {
                        $rp->getPlace()->setStatut('disponible');
                    }
                    $em->remove($rp);
                    $compteur++;
                }
            }

            if ($reservation->getPaiement()) {
                $nouveauPaye = max(0, (float)$reservation->getPaiement()->getMontant() - $montantRembourse);
                $reservation->getPaiement()->setMontant((string)$nouveauPaye);
            }
        }

        // Notification envoyée au client pour confirmer la validation
        $client = $reservation->getUtilisateur();
        if ($client) {
            $notifClient = new Notification();
            $notifClient->setUtilisateur($client);
            $notifClient->setType('remboursement_valide');
            $montantTxt = number_format($montantRembourse, 0, '', ' ') . ' Ar';
            $notifClient->setMessage("Votre remboursement pour le ticket #{$reservation->getNumeroTicket()} ({$montantTxt}) a été validé avec succès par l'administration DIA.");
            $notifClient->setStatut('non_lu');
            $em->persist($notifClient);
        }

        $em->flush();

        return $this->json([
            'message' => "Remboursement de {$nbARembourser} place(s) validé avec succès ({$montantRembourse} Ar).",
            'statut'  => $reservation->getStatut(),
            'montant_rembourse' => $montantRembourse,
        ]);
    }

    #[Route('/api/reservations/{id}/refuser-remboursement', name: 'api_reservations_refuser_remboursement', methods: ['POST'])]
    public function refuserRemboursement(
        int $id,
        ReservationRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var Utilisateur|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser || !in_array($currentUser->getRole(), ['administrateur', 'super_admin', 'gerant'])) {
            return $this->json(['error' => 'Action réservée à l\'administrateur.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $reservation = $repo->find($id);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $reservation->setStatut('confirme');

        $client = $reservation->getUtilisateur();
        if ($client) {
            $notifClient = new Notification();
            $notifClient->setUtilisateur($client);
            $notifClient->setType('remboursement_refuse');
            $notifClient->setMessage("Votre demande de remboursement pour le ticket #{$reservation->getNumeroTicket()} a été refusée par l'administration.");
            $notifClient->setStatut('non_lu');
            $em->persist($notifClient);
        }

        $em->flush();

        return $this->json(['message' => 'Demande de remboursement refusée. Le billet reste actif.']);
    }

    #[Route('/api/reservations/{id}/reporter', name: 'api_reservations_reporter', methods: ['PATCH', 'POST'])]
    public function reporter(
        int $id,
        Request $request,
        ReservationRepository $repo,
        TrajetRepository $trajetRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $ancienneReservation = $repo->find($id);
        if (!$ancienneReservation) {
            return $this->json(['error' => 'Réservation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $estAdmin = in_array($user->getRole(), ['administrateur', 'super_admin', 'gerant']);
        if ($ancienneReservation->getUtilisateur()?->getId() !== $user->getId() && !$estAdmin) {
            return $this->json(['error' => 'Accès refusé à cette réservation.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $ancienTrajet = $ancienneReservation->getTrajet();
        $dateDepart = $ancienTrajet?->getDate();
        $deuxJoursPlusTard = (new \DateTimeImmutable('today'))->modify('+2 days');

        // RÈGLE MÉTIER STRICTE : Le report est uniquement possible au moins 2 jours avant la date de départ
        if ($dateDepart && $dateDepart < $deuxJoursPlusTard) {
            return $this->json([
                'error' => 'Le délai de 2 jours avant le départ est dépassé. Aucun report n\'est autorisé, aucun compte n\'est rendu.'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $nouveauIdTrajet = $data['nouveau_id_trajet'] ?? null;
        $nouvellesPlaces = $data['nouvelles_places'] ?? [];

        if (!$nouveauIdTrajet) {
            return $this->json(['error' => 'Veuillez choisir un nouveau trajet.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $nouveauTrajet = $trajetRepo->find($nouveauIdTrajet);
        if (!$nouveauTrajet) {
            return $this->json(['error' => 'Nouveau trajet introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $ancienTypeVehicule = $ancienTrajet?->getVehicule()?->getTypeVehicule()?->getId();
        $nouveauTypeVehicule = $nouveauTrajet->getVehicule()?->getTypeVehicule()?->getId();

        if ($ancienTypeVehicule && $nouveauTypeVehicule && $ancienTypeVehicule !== $nouveauTypeVehicule) {
            return $this->json([
                'error' => 'Le report doit se faire impérativement sur un véhicule du même type.'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Libérer les anciennes places
        foreach ($ancienneReservation->getReservationPlaces() as $rp) {
            if ($rp->getPlace()) {
                $rp->getPlace()->setStatut('disponible');
            }
        }
        $ancienneReservation->setStatut('reporte');

        // Création de la nouvelle réservation
        $nouveauTicket = 'T' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $nouvelleReservation = new Reservation();
        $nouvelleReservation->setNumeroTicket($nouveauTicket);
        $nouvelleReservation->setUtilisateur($ancienneReservation->getUtilisateur());
        $nouvelleReservation->setTrajet($nouveauTrajet);
        $nouvelleReservation->setDate(new \DateTimeImmutable());
        $nouvelleReservation->setMontantTotal($ancienneReservation->getMontantTotal());
        $nouvelleReservation->setStatut('confirme');
        $em->persist($nouvelleReservation);

        $vehiculeNouveau = $nouveauTrajet->getVehicule();
        if (empty($nouvellesPlaces)) {
            $nb = $ancienneReservation->getReservationPlaces()->count() ?: 1;
            $nouvellesPlaces = range(1, $nb);
        }

        foreach ($nouvellesPlaces as $num) {
            $place = $em->getRepository(Place::class)->findOneBy([
                'vehicule' => $vehiculeNouveau,
                'numeroPlace' => (int)$num,
            ]);
            if (!$place) {
                $place = new Place();
                $place->setVehicule($vehiculeNouveau);
                $place->setNumeroPlace((int)$num);
                $place->setStatut('occupe');
                $em->persist($place);
            } else {
                $place->setStatut('occupe');
            }

            $rp = new ReservationPlace();
            $rp->setReservation($nouvelleReservation);
            $rp->setPlace($place);
            $em->persist($rp);
        }

        // Transfert direct du paiement (aucun nouveau paiement)
        $ancienPaiement = $ancienneReservation->getPaiement();
        $nouveauPaiement = new Paiement();
        $nouveauPaiement->setReservation($nouvelleReservation);
        $nouveauPaiement->setOperateur($ancienPaiement?->getOperateur() ?? 'Report');
        $nouveauPaiement->setNumeroTelephone($ancienPaiement?->getNumeroTelephone());
        $nouveauPaiement->setMontant($ancienPaiement?->getMontant() ?? $ancienneReservation->getMontantTotal());
        $nouveauPaiement->setStatut('paye');
        $em->persist($nouveauPaiement);

        // RÈGLE MÉTIER : Notification à l'administrateur
        $admins = $em->getRepository(Utilisateur::class)->findBy(['role' => ['administrateur', 'admin', 'super_admin']]);
        $client = $ancienneReservation->getUtilisateur();
        $nomClient = trim(($client?->getPrenom() ?? '') . ' ' . ($client?->getNom() ?? '')) ?: ($client?->getEmail() ?? 'Client');
        $telClient = $client?->getTelephone() ?? ($ancienPaiement?->getNumeroTelephone() ?? '—');
        $trajetDest = $nouveauTrajet->getVilleDepart() . ' ➔ ' . $nouveauTrajet->getVilleArrivee();
        $nouvelleDateTxt = $nouveauTrajet->getDate()?->format('d/m/Y') . ' à ' . $nouveauTrajet->getHoraire()?->format('H:i');

        $messageReport = "Le voyageur {$nomClient} (Tél: {$telClient}) a reporté sa réservation #{$ancienneReservation->getNumeroTicket()} vers le départ {$trajetDest} du {$nouvelleDateTxt}. Nouveau billet : #{$nouveauTicket}.";

        foreach ($admins as $admin) {
            $notif = new Notification();
            $notif->setUtilisateur($admin);
            $notif->setType('report_voyage');
            $notif->setMessage($messageReport);
            $notif->setStatut('non_lu');
            $em->persist($notif);
        }

        $em->flush();

        return $this->json([
            'message'        => 'Réservation reportée avec succès vers la nouvelle date !',
            'ancien_ticket'  => $ancienneReservation->getNumeroTicket(),
            'nouveau_ticket' => $nouveauTicket,
            'nouveau_id'     => $nouvelleReservation->getId(),
        ]);
    }

    #[Route('/api/notifications', name: 'api_notifications_list', methods: ['GET'])]
    public function listNotifications(Request $request, EntityManagerInterface $em, ReservationRepository $reservationRepository): JsonResponse
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $isAdmin = in_array($user->getRole(), ['administrateur', 'super_admin', 'gerant']);
        $scope = $request->query->get('scope');

        if ($isAdmin && $scope === 'all') {
            $notifs = $em->getRepository(Notification::class)->findBy(
                [],
                ['id' => 'DESC'],
                100
            );
        } else {
            $notifs = $em->getRepository(Notification::class)->findBy(
                ['utilisateur' => $user],
                ['id' => 'DESC'],
                50
            );
        }

        $data = array_map(function (Notification $n) use ($reservationRepository, $user) {
            $reservationStatut = null;

            if ($n->getType() === 'demande_remboursement' && preg_match('/#([^\s(]+)/', $n->getMessage() ?? '', $matches)) {
                $reservation = $reservationRepository->findOneBy(['numeroTicket' => $matches[1]]);
                $reservationStatut = $reservation?->getStatut();
            }

            $dest = $n->getUtilisateur();

            return [
                'id'                  => $n->getId(),
                'type'                => $n->getType(),
                'message'             => $n->getMessage(),
                'statut'              => $n->getStatut(),
                'reservation_statut'  => $reservationStatut,
                'date_creation'       => $n->getDateCreation()?->format('Y-m-d H:i:s'),
                'destinataire'        => $dest ? [
                    'id'     => $dest->getId(),
                    'nom'    => $dest->getNom(),
                    'prenom' => $dest->getPrenom(),
                    'email'  => $dest->getEmail(),
                    'role'   => $dest->getRole(),
                ] : null,
                'est_pour_moi'        => $dest?->getId() === $user->getId(),
            ];
        }, $notifs);

        return $this->json($data);
    }

    #[Route('/api/notifications/lire', name: 'api_notifications_lire', methods: ['POST'])]
    public function marquerNotificationsLues(EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $isAdmin = in_array($user->getRole(), ['administrateur', 'super_admin', 'gerant']);

        if ($isAdmin) {
            $notifications = $em->getRepository(Notification::class)->findBy([
                'utilisateur' => $user,
                'statut' => 'non_lu',
            ]);

            if (empty($notifications)) {
                $allNotifs = $em->getRepository(Notification::class)->findBy([
                    'statut' => 'non_lu',
                ]);
                foreach ($allNotifs as $notification) {
                    $u = $notification->getUtilisateur();
                    if ($u && in_array($u->getRole(), ['administrateur', 'super_admin', 'gerant'])) {
                        $notification->setStatut('lu');
                    }
                }
            } else {
                foreach ($notifications as $notification) {
                    $notification->setStatut('lu');
                }
            }
        } else {
            $notifications = $em->getRepository(Notification::class)->findBy([
                'utilisateur' => $user,
                'statut' => 'non_lu',
            ]);

            foreach ($notifications as $notification) {
                $notification->setStatut('lu');
            }
        }

        $em->flush();

        return $this->json(['message' => 'Notifications marquées comme lues.']);
    }

    #[Route('/api/notifications/{id}/lire', name: 'api_notification_lire_une', methods: ['POST'])]
    public function marquerUneNotificationLue(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non autorisé.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $isAdmin = in_array($user->getRole(), ['administrateur', 'super_admin', 'gerant']);
        $notif = $em->getRepository(Notification::class)->find($id);

        if (!$notif || (!$isAdmin && $notif->getUtilisateur() !== $user)) {
            return $this->json(['error' => 'Notification introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $notif->setStatut('lu');
        $em->flush();

        return $this->json(['message' => 'Notification marquée comme lue.']);
    }

    #[Route('/api/notifications/envoyer', name: 'api_notifications_envoyer', methods: ['POST'])]
    public function envoyerNotification(
        Request $request,
        EntityManagerInterface $em,
        UtilisateurRepository $userRepo
    ): JsonResponse {
        /** @var Utilisateur|null $admin */
        $admin = $this->getUser();
        if (!$admin || !in_array($admin->getRole(), ['administrateur', 'super_admin', 'gerant'])) {
            return $this->json(['error' => 'Action réservée à l\'administrateur.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $message = trim($data['message'] ?? '');
        $type = $data['type'] ?? 'message_admin';

        if (empty($message)) {
            return $this->json(['error' => 'Le message ne peut pas être vide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Déterminer les destinataires
        $destinataires = $data['destinataires'] ?? [];
        if ($destinataires === 'tous' || $destinataires === ['tous']) {
            // Tous les voyageurs (non-admins)
            $tous = $userRepo->findAll();
            $users = array_filter($tous, fn(Utilisateur $u) => !in_array($u->getRole(), ['administrateur', 'super_admin', 'gerant']));
        } elseif (is_array($destinataires) && count($destinataires) > 0) {
            $users = array_filter(
                array_map(fn($id) => $userRepo->find((int)$id), $destinataires),
                fn($u) => $u !== null
            );
        } else {
            return $this->json(['error' => 'Aucun destinataire spécifié.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $nbEnvoyes = 0;

        foreach ($users as $user) {
            $notif = new Notification();
            $notif->setUtilisateur($user);
            $notif->setType($type);
            $notif->setMessage($message);
            $notif->setStatut('non_lu');
            $em->persist($notif);
            $nbEnvoyes++;
        }

        $em->flush();

        return $this->json([
            'message' => "Notification envoyée à {$nbEnvoyes} destinataire(s).",
            'nb_envoyes' => $nbEnvoyes,
        ]);
    }

    #[Route('/api/reservations/{id}/checkpoint', name: 'api_reservations_checkpoint', methods: ['PATCH', 'POST'])]
    public function validerCheckpoint(
        int $id,
        Request $request,
        ReservationRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $reservation = $repo->find($id);
        if (!$reservation) {
            return $this->json(['error' => 'Réservation introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $reservation->setStatut('embarque');

        $paiement = $reservation->getPaiement();
        if ($paiement) {
            $paiement->setMontant($reservation->getMontantTotal());
            $paiement->setStatut('solde_especes');
        }

        $em->flush();

        return $this->json([
            'message' => 'Présence validée et embarquement confirmé.',
            'ticket'  => $reservation->getNumeroTicket(),
            'statut'  => 'embarque',
        ]);
    }

    #[Route('/api/checkpoint/voyages-du-jour', name: 'api_checkpoint_voyages_jour', methods: ['GET'])]
    #[Route('/api/checkpoint/voyages-du-jour', name: 'api_checkpoint_voyages_du_jour', methods: ['GET'])]
    public function voyagesDuJour(TrajetRepository $trajetRepo): JsonResponse
    {
        try {
            $aujourdhui = (new \DateTimeImmutable('today'))->format('Y-m-d');
            $trajets = $trajetRepo->findAll();

            $trajetsJour = array_filter($trajets, function (Trajet $t) use ($aujourdhui) {
                return $t->getDate() && $t->getDate()->format('Y-m-d') === $aujourdhui;
            });

            $data = array_values(array_map(function (Trajet $t) {
                $passagers = [];
                $resList = $t->getReservations();
                if ($resList) {
                    foreach ($resList as $r) {
                        if (!in_array(strtolower($r->getStatut() ?? ''), ['rembourse', 'reporte', 'annule'])) {
                            $places = [];
                            $rPlaces = $r->getReservationPlaces();
                            if ($rPlaces) {
                                foreach ($rPlaces as $rp) {
                                    if ($rp->getPlace() && $rp->getPlace()->getNumeroPlace()) {
                                        $places[] = $rp->getPlace()->getNumeroPlace();
                                    }
                                }
                            }
                            sort($places);

                            $paiement = $r->getPaiement();
                            $montantTotal = (float)$r->getMontantTotal();
                            $montantPaye = $paiement ? (float)$paiement->getMontant() : $montantTotal;

                            $passagers[] = [
                                'id_reservation' => $r->getId(),
                                'ticket'         => $r->getNumeroTicket(),
                                'client'         => trim(($r->getUtilisateur()?->getPrenom() ?? '') . ' ' . ($r->getUtilisateur()?->getNom() ?? '')) ?: ($r->getUtilisateur()?->getEmail() ?? 'Passager'),
                                'telephone'      => $r->getUtilisateur()?->getTelephone() ?? '—',
                                'places'         => $places,
                                'montant_total'  => $montantTotal,
                                'montant_paye'   => $montantPaye,
                                'solde_a_payer'  => max(0, $montantTotal - $montantPaye),
                                'statut'         => $r->getStatut(),
                                'est_embarque'   => strtolower($r->getStatut() ?? '') === 'embarque',
                            ];
                        }
                    }
                }

                return [
                    'id'            => $t->getId(),
                    'depart'        => $t->getVilleDepart(),
                    'arrivee'       => $t->getVilleArrivee(),
                    'horaire'       => $t->getHoraire()?->format('H:i'),
                    'date'          => $t->getDate()?->format('Y-m-d'),
                    'vehicule'      => $t->getVehicule()?->getMatricule() ?? 'TBA',
                    'type_vehicule' => $t->getVehicule()?->getTypeVehicule()?->getNom() ?? 'Standard',
                    'chauffeur'     => $t->getVehicule()?->getChauffeur()?->getNom() ?? 'Non assigné',
                    'passagers'     => $passagers,
                    'total_passagers' => count($passagers),
                ];
            }, $trajetsJour));

            return $this->json($data);
        } catch (\Throwable $e) {
            return $this->json([], JsonResponse::HTTP_OK);
        }
    }

    #[Route('/api/stats', name: 'api_admin_stats', methods: ['GET'])]
    public function stats(
        ReservationRepository $resRepo,
        TrajetRepository $trajetRepo,
        UtilisateurRepository $userRepo,
        ChauffeurRepository $chauffeurRepo,
        VehiculeRepository $vehiculeRepo
    ): JsonResponse {
        try {
            $reservations = $resRepo->findAll();
            $trajets = $trajetRepo->findAll();
            $utilisateurs = $userRepo->findAll();
            $chauffeurs = $chauffeurRepo->findAll();

            $chiffreAffairesTotal = 0;
            $bilanOperateurs = [
                'MVola'        => 0,
                'Orange Money' => 0,
                'Airtel Money' => 0,
                'Espèces'      => 0,
            ];
            $nbRembourses = 0;
            $nbReportes = 0;
            $nbConfirmes = 0;

            foreach ($reservations as $r) {
                $statut = strtolower($r->getStatut() ?? '');
                if ($statut === 'rembourse') {
                    $nbRembourses++;
                    continue; // Pas de chiffre d'affaires
                }
                if ($statut === 'reporte') {
                    $nbReportes++;
                    continue; // RÈGLE : Le report déplace l'argent vers la nouvelle réservation, on ne compte pas l'ancienne pour éviter le doublon
                }
                
                $nbConfirmes++;

                $p = $r->getPaiement();
                $montant = $p ? (float)$p->getMontant() : (float)$r->getMontantTotal();
                $chiffreAffairesTotal += $montant;

                $op = $p?->getOperateur() ?? 'MVola';
                if (stripos($op, 'mvola') !== false) {
                    $bilanOperateurs['MVola'] += $montant;
                } elseif (stripos($op, 'orange') !== false) {
                    $bilanOperateurs['Orange Money'] += $montant;
                } elseif (stripos($op, 'airtel') !== false) {
                    $bilanOperateurs['Airtel Money'] += $montant;
                } else {
                    $bilanOperateurs['Espèces'] += $montant;
                }
            }

            $totalPlacesVehicules = 0;
            $totalPlacesOccupees = 0;
            foreach ($trajets as $t) {
                $placesVehicule = $t->getVehicule()?->getNombrePlaces() ?? 8;
                $totalPlacesVehicules += $placesVehicule;

                $rList = $t->getReservations();
                if ($rList) {
                    foreach ($rList as $r) {
                        if (!in_array(strtolower($r->getStatut() ?? ''), ['rembourse', 'reporte', 'annule'])) {
                            $rPlaces = $r->getReservationPlaces();
                            $totalPlacesOccupees += $rPlaces ? $rPlaces->count() : 0;
                        }
                    }
                }
            }
            $tauxRemplissageMoyen = $totalPlacesVehicules > 0
                ? round(($totalPlacesOccupees / $totalPlacesVehicules) * 100, 1)
                : 0;

            $trajetsParChauffeur = [];
            foreach ($chauffeurs as $ch) {
                $nom = $ch->getNom() ?? 'Chauffeur';
                $count = 0;
                foreach ($ch->getVehicules() as $veh) {
                    $count += $veh->getTrajets()->count();
                }
                $trajetsParChauffeur[$nom] = $count;
            }

            $aujourdhui = (new \DateTimeImmutable('today'))->format('Y-m-d');
            $departsDuJour = [];
            foreach ($trajets as $t) {
                if ($t->getDate()?->format('Y-m-d') === $aujourdhui) {
                    $departsDuJour[] = [
                        'id'       => $t->getId(),
                        'depart'   => $t->getVilleDepart(),
                        'arrivee'  => $t->getVilleArrivee(),
                        'horaire'  => $t->getHoraire()?->format('H:i'),
                        'vehicule' => $t->getVehicule()?->getMatricule() ?? 'TBA',
                        'type'     => $t->getVehicule()?->getTypeVehicule()?->getNom() ?? 'Standard',
                    ];
                }
            }

            // Nombre réel de voyageurs (excluant administrateurs ou tous utilisateurs enregistrés)
            $nbVoyageurs = count(array_filter($utilisateurs, fn($u) => $u->getRole() !== 'administrateur'));
            if ($nbVoyageurs === 0) {
                $nbVoyageurs = count($utilisateurs);
            }

            return $this->json([
                'chiffre_affaires_total' => $chiffreAffairesTotal,
                'chiffre_affaires_texte' => number_format($chiffreAffairesTotal, 0, '', ' ') . ' Ar',
                'taux_remplissage_moyen' => $tauxRemplissageMoyen,
                'bilan_operateurs'       => $bilanOperateurs,
                'total_voyageurs'        => $nbVoyageurs,
                'total_reservations'     => count($reservations),
                'nb_remboursements'      => $nbRembourses,
                'nb_reportes'            => $nbReportes,
                'nb_confirmes'           => $nbConfirmes,
                'trajets_par_chauffeur'  => $trajetsParChauffeur,
                'departs_du_jour'        => $departsDuJour,
                'nb_departs_jour'        => count($departsDuJour),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error'                  => $e->getMessage(),
                'chiffre_affaires_total' => 0,
                'chiffre_affaires_texte' => '0 Ar',
                'taux_remplissage_moyen' => 0,
                'bilan_operateurs'       => ['MVola' => 0, 'Orange Money' => 0, 'Airtel Money' => 0, 'Espèces' => 0],
                'total_voyageurs'        => 0,
                'total_reservations'     => 0,
                'nb_remboursements'      => 0,
                'nb_reportes'            => 0,
                'nb_confirmes'           => 0,
                'trajets_par_chauffeur'  => [],
                'departs_du_jour'        => [],
                'nb_departs_jour'        => 0,
            ], JsonResponse::HTTP_OK);
        }
    }
}

