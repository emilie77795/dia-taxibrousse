<?php

namespace App\Entity;

use App\Repository\TarifRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TarifRepository::class)]
#[ORM\Table(name: 'tarif')]
class Tarif
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Trajet::class, inversedBy: 'tarifs')]
    #[ORM\JoinColumn(name: 'id_trajet', nullable: false)]
    private ?Trajet $trajet = null;

    #[ORM\ManyToOne(targetEntity: TypeVehicule::class, inversedBy: 'tarifs')]
    #[ORM\JoinColumn(name: 'id_type_vehicule', nullable: false)]
    private ?TypeVehicule $typeVehicule = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $prix = null;

    public function getId(): ?int { return $this->id; }
    public function getTrajet(): ?Trajet { return $this->trajet; }
    public function setTrajet(?Trajet $trajet): static { $this->trajet = $trajet; return $this; }
    public function getTypeVehicule(): ?TypeVehicule { return $this->typeVehicule; }
    public function setTypeVehicule(?TypeVehicule $typeVehicule): static { $this->typeVehicule = $typeVehicule; return $this; }
    public function getPrix(): ?string { return $this->prix; }
    public function setPrix(string $prix): static { $this->prix = $prix; return $this; }
}