<?php

namespace App\Controller;

use App\Entity\Tarif;
use App\Entity\Trajet;
use App\Entity\TypeVehicule;
use App\Repository\TarifRepository;
use App\Repository\TrajetRepository;
use App\Repository\TypeVehiculeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TarifController extends AbstractController
{
    /**
     * Liste des tarifs / fourchettes de prix enregistrés dans la base de données
     */
    #[Route('/api/tarifs', name: 'api_tarifs_list', methods: ['GET'])]
    #[Route('/api/fourchettes', name: 'api_fourchettes_alias_list', methods: ['GET'])]
    public function list(TarifRepository $tarifRepo): JsonResponse
    {
        $tarifs = $tarifRepo->findAll();

        $data = array_map(function (Tarif $t) {
            $trajet = $t->getTrajet();
            $type = $t->getTypeVehicule();
            $villeDep = $trajet ? $trajet->getVilleDepart() : 'Antananarivo';
            $villeArr = $trajet ? $trajet->getVilleArrivee() : 'Destination';

            $imageVehicule = $type ? $type->getImage() : null;
            $ligneNat = $this->determinerLigneNationale($villeArr);

            return [
                'id'           => $t->getId(),
                'id_trajet'    => $trajet ? $trajet->getId() : null,
                'id_type_vehicule' => $type ? $type->getId() : null,
                'titre'        => $villeDep . ' ➔ ' . $villeArr,
                'depart'       => $villeDep,
                'arrivee'      => $villeArr,
                'region'       => $villeArr . ' · ' . $ligneNat,
                'duree'        => 'Environ ' . ($trajet ? $trajet->getHoraire()?->format('H\h') : '8h'),
                'prix'         => number_format((float)$t->getPrix(), 0, '', ' ') . ' Ar',
                'prix_valeur'  => (float)$t->getPrix(),
                'typeVehicule' => $type ? $type->getNom() : 'Standard',
                'note'         => '4.9',
                'image'        => $imageVehicule
            ];
        }, $tarifs);

        return $this->json($data);
    }

    /**
     * Ajouter un tarif dans la base de données
     */
    #[Route('/api/tarifs', name: 'api_tarifs_create', methods: ['POST'])]
    #[Route('/api/fourchettes', name: 'api_fourchettes_alias_create', methods: ['POST'])]
    public function create(
        Request $request,
        TrajetRepository $trajetRepo,
        TypeVehiculeRepository $typeRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['prix'])) {
            return $this->json(['error' => 'Le montant du prix est obligatoire.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Trouver ou créer le trajet
        $trajet = null;
        if (!empty($data['id_trajet'])) {
            $trajet = $trajetRepo->find($data['id_trajet']);
        } elseif (!empty($data['titre'])) {
            $parts = explode('➔', $data['titre']);
            $depart = trim($parts[0] ?? 'Antananarivo');
            $arrivee = trim($parts[1] ?? 'Toamasina');
            $trajet = $trajetRepo->findOneBy(['villeDepart' => $depart, 'villeArrivee' => $arrivee]);
        }

        if (!$trajet) {
            $trajet = $trajetRepo->findOneBy([]);
            if (!$trajet) {
                return $this->json(['error' => 'Veuillez enregistrer au moins un trajet en base avant de définir un tarif.'], JsonResponse::HTTP_BAD_REQUEST);
            }
        }

        // Trouver ou créer le type de véhicule
        $type = null;
        if (!empty($data['id_type_vehicule'])) {
            $type = $typeRepo->find($data['id_type_vehicule']);
        } elseif (!empty($data['typeVehicule'])) {
            $type = $typeRepo->findOneBy(['nom' => $data['typeVehicule']]);
        }

        if (!$type) {
            $type = $typeRepo->findOneBy([]);
            if (!$type) {
                $type = new TypeVehicule();
                $type->setNom($data['typeVehicule'] ?? 'Minibus');
                $em->persist($type);
                $em->flush();
            }
        }

        $prixNet = str_replace([' ', 'Ar', 'ar'], '', (string)$data['prix']);

        $tarif = new Tarif();
        $tarif->setTrajet($trajet);
        $tarif->setTypeVehicule($type);
        $tarif->setPrix($prixNet ?: '20000');

        $em->persist($tarif);
        $em->flush();

        return $this->json([
            'id'           => $tarif->getId(),
            'id_trajet'    => $trajet->getId(),
            'id_type_vehicule' => $type->getId(),
            'titre'        => $trajet->getVilleDepart() . ' ➔ ' . $trajet->getVilleArrivee(),
            'depart'       => $trajet->getVilleDepart(),
            'arrivee'      => $trajet->getVilleArrivee(),
            'region'       => $trajet->getVilleArrivee() . ' · ' . $this->determinerLigneNationale($trajet->getVilleArrivee()),
            'duree'        => 'Environ ' . $trajet->getHoraire()?->format('H\h'),
            'prix'         => number_format((float)$tarif->getPrix(), 0, '', ' ') . ' Ar',
            'prix_valeur'  => (float)$tarif->getPrix(),
            'typeVehicule' => $type->getNom(),
            'note'         => '4.9',
            'image'        => $type->getImage()
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * Modifier un tarif en base de données
     */
    #[Route('/api/tarifs/{id}', name: 'api_tarifs_update', methods: ['PUT', 'PATCH'])]
    #[Route('/api/fourchettes/{id}', name: 'api_fourchettes_alias_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        TarifRepository $tarifRepo,
        TrajetRepository $trajetRepo,
        TypeVehiculeRepository $typeRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $tarif = $tarifRepo->find($id);
        if (!$tarif) {
            return $this->json(['error' => 'Tarif introuvable en base.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!empty($data['prix'])) {
            $prixNet = str_replace([' ', 'Ar', 'ar'], '', (string)$data['prix']);
            $tarif->setPrix($prixNet);
        } elseif (!empty($data['prixMin'])) {
            $prixNet = str_replace([' ', 'Ar', 'ar'], '', (string)$data['prixMin']);
            $tarif->setPrix($prixNet);
        }

        if (!empty($data['id_type_vehicule'])) {
            $type = $typeRepo->find($data['id_type_vehicule']);
            if ($type) $tarif->setTypeVehicule($type);
        } elseif (!empty($data['typeVehicule'])) {
            $type = $typeRepo->findOneBy(['nom' => $data['typeVehicule']]);
            if ($type) $tarif->setTypeVehicule($type);
        }

        if (!empty($data['id_trajet'])) {
            $trajet = $trajetRepo->find($data['id_trajet']);
            if ($trajet) $tarif->setTrajet($trajet);
        }

        $em->flush();

        return $this->json(['message' => 'Tarif mis à jour avec succès en base de données.']);
    }

    /**
     * Supprimer un tarif de la base de données
     */
    #[Route('/api/tarifs/{id}', name: 'api_tarifs_delete', methods: ['DELETE'])]
    #[Route('/api/fourchettes/{id}', name: 'api_fourchettes_alias_delete', methods: ['DELETE'])]
    public function delete(int $id, TarifRepository $tarifRepo, EntityManagerInterface $em): JsonResponse
    {
        $tarif = $tarifRepo->find($id);
        if (!$tarif) {
            return $this->json(['error' => 'Tarif introuvable en base.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $em->remove($tarif);
        $em->flush();

        return $this->json(['message' => 'Tarif supprimé avec succès de la base de données.']);
    }

    private function determinerLigneNationale(string $ville): string
    {
        $v = strtolower(trim($ville));
        if (str_contains($v, 'mahajanga') || str_contains($v, 'majunga')) {
            return 'Ligne Nationale 4';
        }
        if (str_contains($v, 'toamasina') || str_contains($v, 'tamatave')) {
            return 'Ligne Nationale 2';
        }
        if (str_contains($v, 'fianarantsoa') || str_contains($v, 'toliara') || str_contains($v, 'tulear') || str_contains($v, 'antsirabe')) {
            return 'Ligne Nationale 7';
        }
        if (str_contains($v, 'antsiranana') || str_contains($v, 'diego')) {
            return 'Ligne Nationale 6';
        }
        if (str_contains($v, 'morondava')) {
            return 'Ligne Nationale 35';
        }
        if (str_contains($v, 'ambatondrazaka')) {
            return 'Ligne Nationale 44';
        }
        if (str_contains($v, 'manakara')) {
            return 'Ligne Nationale 25';
        }

        return 'Ligne Nationale';
    }
}

