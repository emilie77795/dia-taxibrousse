<?php

namespace App\Entity;

use App\Repository\ReservationPlaceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationPlaceRepository::class)]
#[ORM\Table(name: 'reservation_place')]
class ReservationPlace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'reservationPlaces')]
    #[ORM\JoinColumn(name: 'id_reservation', nullable: false)]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne(targetEntity: Place::class, inversedBy: 'reservationPlaces')]
    #[ORM\JoinColumn(name: 'id_place', nullable: false)]
    private ?Place $place = null;

    public function getId(): ?int { return $this->id; }
    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $reservation): static { $this->reservation = $reservation; return $this; }
    public function getPlace(): ?Place { return $this->place; }
    public function setPlace(?Place $place): static { $this->place = $place; return $this; }
}