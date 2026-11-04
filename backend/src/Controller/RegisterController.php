<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends AbstractController
{
    #[Route('/api/utilisateurs', name: 'api_utilisateurs_list', methods: ['GET'])]
    public function list(UtilisateurRepository $repo): JsonResponse
    {
        $utilisateurs = $repo->findAll();

        $data = array_map(fn(Utilisateur $u) => [
            'id'     => $u->getId(),
            'nom'    => $u->getNom(),
            'prenom' => $u->getPrenom(),
            'email'  => $u->getEmail(),
            'tel'    => $u->getTelephone(),
            'role'   => $u->getRole(),
        ], $utilisateurs);

        return $this->json($data);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Si 'prenom' n'est pas fourni séparément, déduire depuis 'nom'
        if (empty($data['prenom'])) {
            $parties = preg_split('/\s+/', trim($data['nom'] ?? ''));
            if ($parties && count($parties) > 1) {
                $data['nom'] = array_shift($parties);
                $data['prenom'] = implode(' ', $parties);
            } else {
                $data['prenom'] = $data['nom'] ?? '-';
            }
        }

        // Validation des champs obligatoires
        $required = ['nom', 'prenom', 'telephone', 'email', 'mot_de_passe'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(
                    ['error' => "Le champ '$field' est obligatoire."],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }
        }

        // Vérification unicité de l'email
        $existing = $em->getRepository(Utilisateur::class)
                        ->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return $this->json(
                ['error' => 'Cet email est déjà utilisé.'],
                JsonResponse::HTTP_CONFLICT
            );
        }

        $utilisateur = new Utilisateur();
        $utilisateur->setNom($data['nom']);
        $utilisateur->setPrenom($data['prenom']);
        $utilisateur->setTelephone($data['telephone']);
        $utilisateur->setEmail($data['email']);
        $utilisateur->setLieu($data['lieu'] ?? null);
        $utilisateur->setRole($data['role'] ?? 'voyageur');

        $motDePasseHashe = $hasher->hashPassword($utilisateur, $data['mot_de_passe']);
        $utilisateur->setMotDePasse($motDePasseHashe);

        $em->persist($utilisateur);
        $em->flush();

        return $this->json([
            'message' => 'Compte créé avec succès.',
            'id'      => $utilisateur->getId(),
            'email'   => $utilisateur->getEmail(),
            'role'    => $utilisateur->getRole(),
        ], JsonResponse::HTTP_CREATED);
    }
}
