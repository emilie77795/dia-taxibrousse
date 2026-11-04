<?php

namespace App\Controller;

use App\Entity\Tarif;
use App\Entity\Trajet;
use App\Entity\Vehicule;
use App\Repository\TarifRepository;
use App\Repository\TrajetRepository;
use App\Repository\VehiculeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TrajetController extends AbstractController
{
    private function trouverPrixTrajet(Trajet $t, TarifRepository $tarifRepo): float
    {
        // 1. Tarif directement lié au trajet et au type de véhicule
        $typeVehicule = $t->getVehicule()?->getTypeVehicule();
        if ($typeVehicule) {
            $tarifDirect = $tarifRepo->findOneBy([
                'trajet' => $t,
                'typeVehicule' => $typeVehicule,
            ]);
            if ($tarifDirect) {
                return (float)$tarifDirect->getPrix();
            }
        }

        // 2. Si non trouvé directement, chercher un tarif avec le même itinéraire (VilleDépart -> VilleArrivée) et même type
        $tousTarifs = $tarifRepo->findAll();
        foreach ($tousTarifs as $tf) {
            $trj = $tf->getTrajet();
            if ($trj && 
                strcasecmp($trj->getVilleDepart(), $t->getVilleDepart()) === 0 && 
                strcasecmp($trj->getVilleArrivee(), $t->getVilleArrivee()) === 0) {
                if ($typeVehicule && $tf->getTypeVehicule()?->getId() === $typeVehicule->getId()) {
                    return (float)$tf->getPrix();
                }
            }
        }

        // 3. Premier tarif de même itinéraire
        foreach ($tousTarifs as $tf) {
            $trj = $tf->getTrajet();
            if ($trj && 
                strcasecmp($trj->getVilleDepart(), $t->getVilleDepart()) === 0 && 
                strcasecmp($trj->getVilleArrivee(), $t->getVilleArrivee()) === 0) {
                return (float)$tf->getPrix();
            }
        }

        // 4. Tarif lié directement
        $tarif = $t->getTarifs()->first();
        if ($tarif) {
            return (float)$tarif->getPrix();
        }

        return 20000.0;
    }

    #[Route('/api/trajets', name: 'api_trajets_list', methods: ['GET'])]
    public function list(TrajetRepository $repo, TarifRepository $tarifRepo): JsonResponse
    {
        $trajets = $repo->findAll();

        $data = array_map(function (Trajet $t) use ($tarifRepo) {
            $prix = $this->trouverPrixTrajet($t, $tarifRepo);

            $placesOccupeesNumeros = [];
            foreach ($t->getReservations() as $res) {
                // Seules les réservations actives occupent des places (pas remboursées ni reportées)
                if (!in_array(strtolower($res->getStatut() ?? ''), ['rembourse', 'reporte', 'annule'])) {
                    foreach ($res->getReservationPlaces() as $rp) {
                        $p = $rp->getPlace();
                        if ($p && $p->getNumeroPlace()) {
                            $placesOccupeesNumeros[] = $p->getNumeroPlace();
                        }
                    }
                }
            }
            $placesOccupeesNumeros = array_values(array_unique($placesOccupeesNumeros));
            $nbPlacesVehicule = $t->getVehicule()?->getNombrePlaces() ?? 8;
            $nbPlacesOccupees = count($placesOccupeesNumeros);
            $tauxRemplissage = $nbPlacesVehicule > 0 ? round(($nbPlacesOccupees / $nbPlacesVehicule) * 100, 1) : 0;

            return [
                'id'           => $t->getId(),
                'ville_depart' => $t->getVilleDepart(),
                'ville_arrivee'=> $t->getVilleArrivee(),
                'date'         => $t->getDate()?->format('Y-m-d'),
                'horaire'      => $t->getHoraire()?->format('H:i'),
                'prix'         => number_format($prix, 0, '', ' ') . ' Ar',
                'prix_valeur'  => $prix,
                'places_totales'        => $nbPlacesVehicule,
                'places_occupees'       => $nbPlacesOccupees,
                'places_disponibles'    => max(0, $nbPlacesVehicule - $nbPlacesOccupees),
                'numeros_places_occupees' => $placesOccupeesNumeros,
                'taux_remplissage'      => $tauxRemplissage,
                'vehicule'     => [
                    'id'               => $t->getVehicule()?->getId(),
                    'matricule'        => $t->getVehicule()?->getMatricule(),
                    'nombre_places'    => $nbPlacesVehicule,
                    'id_type_vehicule' => $t->getVehicule()?->getTypeVehicule()?->getId(),
                    'type'             => $t->getVehicule()?->getTypeVehicule()?->getNom(),
                    'image'            => $t->getVehicule()?->getTypeVehicule()?->getImage(),
                    'chauffeur'        => $t->getVehicule()?->getChauffeur()?->getNom(),
                ],
            ];
        }, $trajets);

        return $this->json($data);
    }

    #[Route('/api/trajets/par-type/{idType}', name: 'api_trajets_par_type', methods: ['GET'])]
    public function parType(int $idType, TrajetRepository $repo, TarifRepository $tarifRepo): JsonResponse
    {
        $trajets = $repo->findAll();
        $filtres = array_filter($trajets, function (Trajet $t) use ($idType) {
            return $t->getVehicule()?->getTypeVehicule()?->getId() === $idType;
        });

        $data = array_values(array_map(function (Trajet $t) use ($tarifRepo) {
            $prix = $this->trouverPrixTrajet($t, $tarifRepo);

            $placesOccupeesNumeros = [];
            foreach ($t->getReservations() as $res) {
                if (!in_array(strtolower($res->getStatut() ?? ''), ['rembourse', 'reporte', 'annule'])) {
                    foreach ($res->getReservationPlaces() as $rp) {
                        $p = $rp->getPlace();
                        if ($p && $p->getNumeroPlace()) {
                            $placesOccupeesNumeros[] = $p->getNumeroPlace();
                        }
                    }
                }
            }
            $nbPlacesVehicule = $t->getVehicule()?->getNombrePlaces() ?? 8;
            $nbPlacesOccupees = count(array_unique($placesOccupeesNumeros));

            return [
                'id'           => $t->getId(),
                'ville_depart' => $t->getVilleDepart(),
                'ville_arrivee'=> $t->getVilleArrivee(),
                'date'         => $t->getDate()?->format('Y-m-d'),
                'horaire'      => $t->getHoraire()?->format('H:i'),
                'prix'         => number_format($prix, 0, '', ' ') . ' Ar',
                'prix_valeur'  => $prix,
                'places_totales'        => $nbPlacesVehicule,
                'places_occupees'       => $nbPlacesOccupees,
                'places_disponibles'    => max(0, $nbPlacesVehicule - $nbPlacesOccupees),
                'numeros_places_occupees' => array_values(array_unique($placesOccupeesNumeros)),
                'vehicule'     => [
                    'id'               => $t->getVehicule()?->getId(),
                    'matricule'        => $t->getVehicule()?->getMatricule(),
                    'nombre_places'    => $nbPlacesVehicule,
                    'id_type_vehicule' => $t->getVehicule()?->getTypeVehicule()?->getId(),
                    'type'             => $t->getVehicule()?->getTypeVehicule()?->getNom(),
                    'image'            => $t->getVehicule()?->getTypeVehicule()?->getImage(),
                    'chauffeur'        => $t->getVehicule()?->getChauffeur()?->getNom(),
                ],
            ];
        }, $filtres));

        return $this->json($data);
    }

    #[Route('/api/trajets', name: 'api_trajets_create', methods: ['POST'])]
    public function create(
        Request $request,
        VehiculeRepository $vehiculeRepo,
        TarifRepository $tarifRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['ville_depart']) || empty($data['ville_arrivee']) || empty($data['date']) || empty($data['horaire'])) {
            return $this->json(['error' => 'Champs obligatoires manquants.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $t = new Trajet();
        $t->setVilleDepart(trim($data['ville_depart']));
        $t->setVilleArrivee(trim($data['ville_arrivee']));
        $t->setDate(new \DateTimeImmutable($data['date']));
        $t->setHoraire(new \DateTimeImmutable($data['horaire']));

        if (!empty($data['id_vehicule'])) {
            $v = $vehiculeRepo->find($data['id_vehicule']);
            if ($v) $t->setVehicule($v);
        } elseif (!empty($data['matricule'])) {
            $v = $vehiculeRepo->findOneBy(['matricule' => $data['matricule']]);
            if ($v) $t->setVehicule($v);
        }

        // Si aucun véhicule assigné n'est trouvé, attribuer le premier véhicule disponible si présent
        if (!$t->getVehicule()) {
            $firstV = $vehiculeRepo->findOneBy([]);
            if ($firstV) {
                $t->setVehicule($firstV);
            } else {
                return $this->json(['error' => 'Veuillez enregistrer au moins un véhicule avant de créer un trajet.'], JsonResponse::HTTP_BAD_REQUEST);
            }
        }

        $em->persist($t);
        $em->flush();

        // Si un prix a été saisi à la création du trajet, on crée ou met à jour le Tarif correspondant
        if (!empty($data['prix'])) {
            $prixNet = str_replace([' ', 'Ar', 'ar'], '', (string)$data['prix']);
            $typeVehicule = $t->getVehicule()?->getTypeVehicule();
            if ($typeVehicule) {
                $tarif = new Tarif();
                $tarif->setTrajet($t);
                $tarif->setTypeVehicule($typeVehicule);
                $tarif->setPrix($prixNet);
                $em->persist($tarif);
                $em->flush();
            }
        }

        return $this->json([
            'id'           => $t->getId(),
            'ville_depart' => $t->getVilleDepart(),
            'ville_arrivee'=> $t->getVilleArrivee(),
            'date'         => $t->getDate()?->format('Y-m-d'),
            'horaire'      => $t->getHoraire()?->format('H:i'),
            'vehicule'     => [
                'id'            => $t->getVehicule()?->getId(),
                'matricule'     => $t->getVehicule()?->getMatricule(),
                'nombre_places' => $t->getVehicule()?->getNombrePlaces(),
                'type'          => $t->getVehicule()?->getTypeVehicule()?->getNom(),
                'chauffeur'     => $t->getVehicule()?->getChauffeur()?->getNom(),
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/trajets/{id}', name: 'api_trajets_update', methods: ['PUT', 'PATCH', 'POST'])]
    public function update(
        int $id,
        Request $request,
        TrajetRepository $repo,
        VehiculeRepository $vehiculeRepo,
        TarifRepository $tarifRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        try {
            $t = $repo->find($id);
            if (!$t) {
                return $this->json(['error' => 'Trajet introuvable.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            if (!is_array($data)) {
                $data = $request->request->all();
            }

            if (empty($data['ville_depart']) || empty($data['ville_arrivee']) || empty($data['date']) || empty($data['horaire'])) {
                return $this->json(['error' => 'Champs obligatoires manquants.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $t->setVilleDepart(trim($data['ville_depart']));
            $t->setVilleArrivee(trim($data['ville_arrivee']));
            $t->setDate(new \DateTimeImmutable($data['date']));
            $t->setHoraire(new \DateTimeImmutable($data['horaire']));

            if (!empty($data['id_vehicule'])) {
                $v = $vehiculeRepo->find($data['id_vehicule']);
                if ($v) $t->setVehicule($v);
            } elseif (!empty($data['matricule'])) {
                $v = $vehiculeRepo->findOneBy(['matricule' => $data['matricule']]);
                if ($v) $t->setVehicule($v);
            }

            // Si aucun véhicule assigné n'est présent, attribuer le premier si nécessaire
            if (!$t->getVehicule()) {
                $firstV = $vehiculeRepo->findOneBy([]);
                if ($firstV) {
                    $t->setVehicule($firstV);
                }
            }

            // Si un prix a été saisi / modifié
            if (isset($data['prix']) && $data['prix'] !== '') {
                $prixNet = str_replace([' ', 'Ar', 'ar'], '', (string)$data['prix']);
                $typeVehicule = $t->getVehicule()?->getTypeVehicule();
                if ($typeVehicule) {
                    // Chercher le tarif lié à ce trajet
                    $tarif = $tarifRepo->findOneBy([
                        'trajet' => $t,
                        'typeVehicule' => $typeVehicule,
                    ]);

                    if (!$tarif) {
                        $tarif = $t->getTarifs()->first() ?: null;
                    }

                    if ($tarif) {
                        $tarif->setTypeVehicule($typeVehicule);
                        $tarif->setPrix($prixNet);
                    } else {
                        $tarif = new Tarif();
                        $tarif->setTrajet($t);
                        $tarif->setTypeVehicule($typeVehicule);
                        $tarif->setPrix($prixNet);
                        $em->persist($tarif);
                    }
                }
            }

            $em->flush();

            return $this->json([
                'id'           => $t->getId(),
                'ville_depart' => $t->getVilleDepart(),
                'ville_arrivee'=> $t->getVilleArrivee(),
                'date'         => $t->getDate()?->format('Y-m-d'),
                'horaire'      => $t->getHoraire()?->format('H:i'),
                'vehicule'     => [
                    'id'            => $t->getVehicule()?->getId(),
                    'matricule'     => $t->getVehicule()?->getMatricule(),
                    'nombre_places' => $t->getVehicule()?->getNombrePlaces(),
                    'type'          => $t->getVehicule()?->getTypeVehicule()?->getNom(),
                    'chauffeur'     => $t->getVehicule()?->getChauffeur()?->getNom(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour : ' . $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/trajets/{id}', name: 'api_trajets_delete', methods: ['DELETE'])]
    public function delete(int $id, TrajetRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        try {
            $t = $repo->find($id);
            if (!$t) {
                return $this->json(['error' => 'Trajet introuvable.'], JsonResponse::HTTP_NOT_FOUND);
            }

            // Vérifier les réservations
            if (!$t->getReservations()->isEmpty()) {
                return $this->json(
                    ['error' => 'Impossible de supprimer ce trajet car il possède des réservations enregistrées.'],
                    JsonResponse::HTTP_CONFLICT
                );
            }

            // Supprimer d'abord les tarifs associés pour respecter les contraintes de clé étrangère
            foreach ($t->getTarifs() as $tarif) {
                $em->remove($tarif);
            }

            $em->remove($t);
            $em->flush();

            return $this->json(['message' => 'Trajet supprimé avec succès.']);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Impossible de supprimer ce trajet : ' . $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
