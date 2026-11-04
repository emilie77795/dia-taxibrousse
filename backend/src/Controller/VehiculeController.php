<?php

namespace App\Controller;

use App\Entity\Chauffeur;
use App\Entity\TypeVehicule;
use App\Entity\Vehicule;
use App\Repository\ChauffeurRepository;
use App\Repository\TypeVehiculeRepository;
use App\Repository\VehiculeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class VehiculeController extends AbstractController
{
    private function handleImageUpload(Request $request, ?string $currentImage = null): ?string
    {
        // 1. Fichier uploadé via multipart/form-data
        $uploadedFile = $request->files->get('image');
        if ($uploadedFile) {
            $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/types';
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0777, true);
            }
            $extension = $uploadedFile->guessExtension() ?: 'jpg';
            $filename = uniqid('type_') . '.' . $extension;
            try {
                $uploadedFile->move($uploadsDir, $filename);
                return '/uploads/types/' . $filename;
            } catch (\Throwable $e) {
                // Si l'upload échoue, on continue avec les autres vérifications
            }
        }

        // 2. Image en base64 ou URL/chemin direct dans les paramètres POST / JSON
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = $request->request->all();
        }

        if (!empty($data['image']) && is_string($data['image'])) {
            $rawImage = $data['image'];
            if (str_starts_with($rawImage, 'data:image/')) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/types';
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0777, true);
                }
                $parts = explode(',', $rawImage);
                if (count($parts) === 2) {
                    $typeInfo = $parts[0];
                    $base64Data = $parts[1];
                    $extension = str_contains($typeInfo, 'png') ? 'png' : (str_contains($typeInfo, 'webp') ? 'webp' : 'jpg');
                    $filename = uniqid('type_') . '.' . $extension;
                    @file_put_contents($uploadsDir . '/' . $filename, base64_decode($base64Data));
                    return '/uploads/types/' . $filename;
                }
            }
            return $rawImage;
        }

        return $currentImage;
    }

    #[Route('/api/types-vehicules', name: 'api_types_vehicules_list', methods: ['GET'])]
    public function listTypes(TypeVehiculeRepository $repo): JsonResponse
    {
        $types = $repo->findAll();
        $data = array_map(fn(TypeVehicule $t) => [
            'id'    => $t->getId(),
            'nom'   => $t->getNom(),
            'image' => $t->getImage(),
        ], $types);

        return $this->json($data);
    }

    #[Route('/api/types-vehicules', name: 'api_types_vehicules_create', methods: ['POST'])]
    public function createType(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!is_array($data)) {
                $data = $request->request->all();
            }

            $nom = $data['nom'] ?? $request->request->get('nom');

            if (empty($nom)) {
                return $this->json(['error' => 'Le nom du type de véhicule est obligatoire.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $type = new TypeVehicule();
            $type->setNom(trim((string)$nom));

            $imagePath = $this->handleImageUpload($request);
            if ($imagePath) {
                $type->setImage($imagePath);
            }

            $em->persist($type);
            $em->flush();

            return $this->json([
                'id'    => $type->getId(),
                'nom'   => $type->getNom(),
                'image' => $type->getImage(),
            ], JsonResponse::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/types-vehicules/{id}', name: 'api_types_vehicules_update', methods: ['POST', 'PUT', 'PATCH'])]
    public function updateType(int $id, Request $request, TypeVehiculeRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        try {
            $type = $repo->find($id);
            if (!$type) {
                return $this->json(['error' => 'Type de véhicule introuvable.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            if (!is_array($data)) {
                $data = $request->request->all();
            }

            $nom = $data['nom'] ?? $request->request->get('nom');
            if (!empty($nom)) {
                $type->setNom(trim((string)$nom));
            }

            $imagePath = $this->handleImageUpload($request, $type->getImage());
            if ($imagePath !== null) {
                $type->setImage($imagePath);
            }

            $em->flush();

            return $this->json([
                'id'    => $type->getId(),
                'nom'   => $type->getNom(),
                'image' => $type->getImage(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/types-vehicules/{id}', name: 'api_types_vehicules_delete', methods: ['DELETE'])]
    public function deleteType(int $id, TypeVehiculeRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $type = $repo->find($id);
        if (!$type) {
            return $this->json(['error' => 'Type de véhicule introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Vérifie si des véhicules utilisent encore ce type
        if (!$type->getVehicules()->isEmpty()) {
            return $this->json(
                ['error' => 'Impossible de supprimer : des véhicules utilisent encore ce type.'],
                JsonResponse::HTTP_CONFLICT
            );
        }

        $em->remove($type);
        $em->flush();

        return $this->json(['message' => 'Type de véhicule supprimé avec succès.']);
    }

    #[Route('/api/vehicules', name: 'api_vehicules_list', methods: ['GET'])]
    public function list(VehiculeRepository $repo): JsonResponse
    {
        $vehicules = $repo->findAll();
        $data = array_map(fn(Vehicule $v) => [
            'id'               => $v->getId(),
            'matricule'        => $v->getMatricule(),
            'nombre_places'    => $v->getNombrePlaces(),
            'type'             => $v->getTypeVehicule()?->getNom(),
            'image'            => $v->getTypeVehicule()?->getImage(),
            'id_type_vehicule' => $v->getTypeVehicule()?->getId(),
            'chauffeur'        => $v->getChauffeur()?->getNom(),
            'id_chauffeur'     => $v->getChauffeur()?->getId(),
        ], $vehicules);

        return $this->json($data);
    }

    #[Route('/api/vehicules', name: 'api_vehicules_create', methods: ['POST'])]
    public function create(
        Request $request,
        TypeVehiculeRepository $typeRepo,
        ChauffeurRepository $chauffeurRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['matricule']) || empty($data['nombre_places']) || empty($data['id_type_vehicule'])) {
            return $this->json(['error' => 'Matricule, nombre de places et type de véhicule requis.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $type = $typeRepo->find($data['id_type_vehicule']);
        if (!$type) {
            return $this->json(['error' => 'Type de véhicule introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $v = new Vehicule();
        $v->setMatricule($data['matricule']);
        $v->setNombrePlaces((int)$data['nombre_places']);
        $v->setTypeVehicule($type);

        if (!empty($data['id_chauffeur'])) {
            $chauffeur = $chauffeurRepo->find($data['id_chauffeur']);
            if ($chauffeur) {
                $v->setChauffeur($chauffeur);
            }
        }

        $em->persist($v);
        $em->flush();

        return $this->json([
            'id'               => $v->getId(),
            'matricule'        => $v->getMatricule(),
            'nombre_places'    => $v->getNombrePlaces(),
            'type'             => $v->getTypeVehicule()?->getNom(),
            'id_type_vehicule' => $v->getTypeVehicule()?->getId(),
            'chauffeur'        => $v->getChauffeur()?->getNom(),
            'id_chauffeur'     => $v->getChauffeur()?->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/vehicules/{id}', name: 'api_vehicules_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        VehiculeRepository $repo,
        TypeVehiculeRepository $typeRepo,
        ChauffeurRepository $chauffeurRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $v = $repo->find($id);
        if (!$v) {
            return $this->json(['error' => 'Véhicule introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['matricule'])) {
            $v->setMatricule($data['matricule']);
        }
        if (isset($data['nombre_places'])) {
            $v->setNombrePlaces((int)$data['nombre_places']);
        }
        if (isset($data['id_type_vehicule'])) {
            $type = $typeRepo->find($data['id_type_vehicule']);
            if ($type) $v->setTypeVehicule($type);
        }
        if (array_key_exists('id_chauffeur', $data)) {
            if ($data['id_chauffeur']) {
                $c = $chauffeurRepo->find($data['id_chauffeur']);
                $v->setChauffeur($c);
            } else {
                $v->setChauffeur(null);
            }
        }

        $em->flush();

        return $this->json([
            'id'               => $v->getId(),
            'matricule'        => $v->getMatricule(),
            'nombre_places'    => $v->getNombrePlaces(),
            'type'             => $v->getTypeVehicule()?->getNom(),
            'id_type_vehicule' => $v->getTypeVehicule()?->getId(),
            'chauffeur'        => $v->getChauffeur()?->getNom(),
            'id_chauffeur'     => $v->getChauffeur()?->getId(),
        ]);
    }

    #[Route('/api/vehicules/{id}', name: 'api_vehicules_delete', methods: ['DELETE'])]
    public function delete(int $id, VehiculeRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $v = $repo->find($id);
        if (!$v) {
            return $this->json(['error' => 'Véhicule introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $em->remove($v);
        $em->flush();

        return $this->json(['message' => 'Véhicule supprimé avec succès.']);
    }
}
