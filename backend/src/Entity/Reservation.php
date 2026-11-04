<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'numero_ticket', length: 50, unique: true)]
    private ?string $numeroTicket = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'id_utilisateur', nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: Trajet::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'id_trajet', nullable: false)]
    private ?Trajet $trajet = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(name: 'montant_total', type: 'decimal', precision: 10, scale: 2)]
    private ?string $montantTotal = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = 'confirme';

    #[ORM\OneToMany(mappedBy: 'reservation', targetEntity: ReservationPlace::class)]
    private Collection $reservationPlaces;

    #[ORM\OneToOne(mappedBy: 'reservation', targetEntity: Paiement::class)]
    private ?Paiement $paiement = null;

    public function __construct()
    {
        $this->reservationPlaces = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNumeroTicket(): ?string { return $this->numeroTicket; }
    public function setNumeroTicket(string $numeroTicket): static { $this->numeroTicket = $numeroTicket; return $this; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }
    public function getTrajet(): ?Trajet { return $this->trajet; }
    public function setTrajet(?Trajet $trajet): static { $this->trajet = $trajet; return $this; }
    public function getDate(): ?\DateTimeImmutable { return $this->date; }
    public function setDate(\DateTimeImmutable $date): static { $this->date = $date; return $this; }
    public function getMontantTotal(): ?string { return $this->montantTotal; }
    public function setMontantTotal(string $montantTotal): static { $this->montantTotal = $montantTotal; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getPaiement(): ?Paiement { return $this->paiement; }
    public function setPaiement(?Paiement $paiement): static { $this->paiement = $paiement; return $this; }
    public function getReservationPlaces(): Collection { return $this->reservationPlaces; }
}