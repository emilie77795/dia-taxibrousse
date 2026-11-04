<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(length: 50)]
    private ?string $role = null;

    /**
     * Mot de passe hashé par Symfony (ne jamais stocker en clair).
     */
    #[ORM\Column(length: 255)]
    private ?string $motDePasse = null;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Reservation::class)]
    private Collection $reservations;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Notification::class)]
    private Collection $notifications;

    public function __construct()
    {
        $this->reservations  = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    // ---------------------------------------------------------------
    // Getters / Setters métier
    // ---------------------------------------------------------------

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(?string $lieu): static { $this->lieu = $lieu; return $this; }

    public function getRole(): ?string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }

    public function getMotDePasse(): ?string { return $this->motDePasse; }
    public function setMotDePasse(string $motDePasse): static { $this->motDePasse = $motDePasse; return $this; }

    // ---------------------------------------------------------------
    // UserInterface — requis par Symfony Security
    // ---------------------------------------------------------------

    /**
     * L'identifiant unique utilisé pour l'authentification (= email).
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Rôles Symfony : doit retourner un tableau (ex. ['ROLE_USER']).
     * On mappe notre colonne `role` métier vers une constante Symfony.
     */
    public function getRoles(): array
    {
        return match ($this->role) {
            'super_admin', 'administrateur', 'gerant' => ['ROLE_ADMIN', 'ROLE_USER'],
            default                                   => ['ROLE_USER'],
        };
    }

    /**
     * Effacement des données sensibles temporaires (non utilisé ici
     * car on ne stocke jamais le mot de passe en clair).
     */
    public function eraseCredentials(): void {}

    // ---------------------------------------------------------------
    // PasswordAuthenticatedUserInterface — requis par le hasher
    // ---------------------------------------------------------------

    /**
     * Retourne le mot de passe hashé stocké en base.
     */
    public function getPassword(): ?string
    {
        return $this->motDePasse;
    }
}