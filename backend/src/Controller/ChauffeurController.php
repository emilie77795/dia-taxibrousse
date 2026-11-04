<?php

namespace App\Controller;

use App\Entity\Chauffeur;
use App\Repository\ChauffeurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ChauffeurController extends AbstractController
{
    #[Route('/api/chauffeurs', name: 'api_chauffeurs_list', methods: ['GET'])]
    public function list(ChauffeurRepository $repo): JsonResponse
    {
        $chauffeurs = $repo->findAll();
        $data = array_map(fn(Chauffeur $c) => [
            'id'      => $c->getId(),
            'nom'     => $c->getNom(),
            'contact' => $c->getContact(),
        ], $chauffeurs);

        return $this->json($data);
    }

    #[Route('/api/chauffeurs', name: 'api_chauffeurs_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (empty($data['nom']) || empty($data['contact'])) {
            return $this->json(['error' => 'Nom et contact requis.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $c = new Chauffeur();
        $c->setNom($data['nom']);
        $c->setContact($data['contact']);

        $em->persist($c);
        $em->flush();

        return $this->json([
            'id'      => $c->getId(),
            'nom'     => $c->getNom(),
            'contact' => $c->getContact(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/api/chauffeurs/{id}', name: 'api_chauffeurs_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, ChauffeurRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $c = $repo->find($id);
        if (!$c) {
            return $this->json(['error' => 'Chauffeur introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['nom'])) $c->setNom($data['nom']);
        if (isset($data['contact'])) $c->setContact($data['contact']);

        $em->flush();

        return $this->json([
            'id'      => $c->getId(),
            'nom'     => $c->getNom(),
            'contact' => $c->getContact(),
        ]);
    }

    #[Route('/api/chauffeurs/{id}', name: 'api_chauffeurs_delete', methods: ['DELETE'])]
    public function delete(int $id, ChauffeurRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $c = $repo->find($id);
        if (!$c) {
            return $this->json(['error' => 'Chauffeur introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $em->remove($c);
        $em->flush();

        return $this->json(['message' => 'Chauffeur supprimé avec succès.']);
    }
}
