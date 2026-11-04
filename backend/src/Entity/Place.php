<?php

namespace App\Entity;

use App\Repository\PlaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaceRepository::class)]
#[ORM\Table(name: 'place')]
class Place
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'numero_place')]
    private ?int $numeroPlace = null;

    #[ORM\ManyToOne(targetEntity: Vehicule::class, inversedBy: 'places')]
    #[ORM\JoinColumn(name: 'id_vehicule', nullable: false)]
    private ?Vehicule $vehicule = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = 'disponible';

    #[ORM\OneToMany(mappedBy: 'place', targetEntity: ReservationPlace::class)]
    private Collection $reservationPlaces;

    public function __construct()
    {
        $this->reservationPlaces = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNumeroPlace(): ?int { return $this->numeroPlace; }
    public function setNumeroPlace(int $numeroPlace): static { $this->numeroPlace = $numeroPlace; return $this; }
    public function getVehicule(): ?Vehicule { return $this->vehicule; }
    public function setVehicule(?Vehicule $vehicule): static { $this->vehicule = $vehicule; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
}