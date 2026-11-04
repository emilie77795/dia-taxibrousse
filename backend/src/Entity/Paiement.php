<?php

namespace App\Entity;

use App\Repository\PaiementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
#[ORM\Table(name: 'paiement')]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'paiement', targetEntity: Reservation::class)]
    #[ORM\JoinColumn(name: 'id_reservation', nullable: false, unique: true)]
    private ?Reservation $reservation = null;

    #[ORM\Column(name: 'numero_telephone', length: 50, nullable: true)]
    private ?string $numeroTelephone = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $operateur = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = 'impaye';

    public function getId(): ?int { return $this->id; }
    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $reservation): static { $this->reservation = $reservation; return $this; }
    public function getNumeroTelephone(): ?string { return $this->numeroTelephone; }
    public function setNumeroTelephone(?string $numeroTelephone): static { $this->numeroTelephone = $numeroTelephone; return $this; }
    public function getOperateur(): ?string { return $this->operateur; }
    public function setOperateur(?string $operateur): static { $this->operateur = $operateur; return $this; }
    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $montant): static { $this->montant = $montant; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
}