<?php

namespace App\Entity;

use App\Repository\TrajetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrajetRepository::class)]
#[ORM\Table(name: 'trajet')]
class Trajet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'ville_depart', length: 255)]
    private ?string $villeDepart = null;

    #[ORM\Column(name: 'ville_arrivee', length: 255)]
    private ?string $villeArrivee = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: 'time_immutable')]
    private ?\DateTimeImmutable $horaire = null;

    #[ORM\ManyToOne(targetEntity: Vehicule::class, inversedBy: 'trajets')]
    #[ORM\JoinColumn(name: 'id_vehicule', nullable: false)]
    private ?Vehicule $vehicule = null;

    #[ORM\OneToMany(mappedBy: 'trajet', targetEntity: Tarif::class)]
    private Collection $tarifs;

    #[ORM\OneToMany(mappedBy: 'trajet', targetEntity: Reservation::class)]
    private Collection $reservations;

    public function __construct()
    {
        $this->tarifs = new ArrayCollection();
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getVilleDepart(): ?string { return $this->villeDepart; }
    public function setVilleDepart(string $villeDepart): static { $this->villeDepart = $villeDepart; return $this; }
    public function getVilleArrivee(): ?string { return $this->villeArrivee; }
    public function setVilleArrivee(string $villeArrivee): static { $this->villeArrivee = $villeArrivee; return $this; }
    public function getDate(): ?\DateTimeImmutable { return $this->date; }
    public function setDate(\DateTimeImmutable $date): static { $this->date = $date; return $this; }
    public function getHoraire(): ?\DateTimeImmutable { return $this->horaire; }
    public function setHoraire(\DateTimeImmutable $horaire): static { $this->horaire = $horaire; return $this; }
    public function getVehicule(): ?Vehicule { return $this->vehicule; }
    public function setVehicule(?Vehicule $vehicule): static { $this->vehicule = $vehicule; return $this; }
    public function getTarifs(): Collection { return $this->tarifs; }
    public function getReservations(): Collection { return $this->reservations; }
}