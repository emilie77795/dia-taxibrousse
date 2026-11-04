<?php

namespace App\Entity;

use App\Repository\VehiculeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehiculeRepository::class)]
#[ORM\Table(name: 'vehicule')]
class Vehicule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $matricule = null;

    #[ORM\ManyToOne(targetEntity: TypeVehicule::class, inversedBy: 'vehicules')]
    #[ORM\JoinColumn(name: 'id_type_vehicule', nullable: false)]
    private ?TypeVehicule $typeVehicule = null;

    #[ORM\Column(name: 'nombre_places')]
    private ?int $nombrePlaces = null;

    #[ORM\ManyToOne(targetEntity: Chauffeur::class, inversedBy: 'vehicules')]
    #[ORM\JoinColumn(name: 'id_chauffeur', nullable: true)]
    private ?Chauffeur $chauffeur = null;

    #[ORM\OneToMany(mappedBy: 'vehicule', targetEntity: Place::class)]
    private Collection $places;

    #[ORM\OneToMany(mappedBy: 'vehicule', targetEntity: Trajet::class)]
    private Collection $trajets;

    public function __construct()
    {
        $this->places = new ArrayCollection();
        $this->trajets = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getMatricule(): ?string { return $this->matricule; }
    public function setMatricule(string $matricule): static { $this->matricule = $matricule; return $this; }
    public function getTypeVehicule(): ?TypeVehicule { return $this->typeVehicule; }
    public function setTypeVehicule(?TypeVehicule $typeVehicule): static { $this->typeVehicule = $typeVehicule; return $this; }
    public function getNombrePlaces(): ?int { return $this->nombrePlaces; }
    public function setNombrePlaces(int $nombrePlaces): static { $this->nombrePlaces = $nombrePlaces; return $this; }
    public function getChauffeur(): ?Chauffeur { return $this->chauffeur; }
    public function setChauffeur(?Chauffeur $chauffeur): static { $this->chauffeur = $chauffeur; return $this; }
    public function getTrajets(): Collection { return $this->trajets; }
    public function getPlaces(): Collection { return $this->places; }
}