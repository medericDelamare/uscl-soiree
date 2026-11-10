<?php

namespace App\Entity;

use App\Enum\Meal;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Un convive (une place) d'une réservation. */
#[ORM\Entity]
class Guest
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'guests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Indiquez le prénom.'), Assert\Length(max: 80)]
    private string $firstName = '';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Indiquez le nom.'), Assert\Length(max: 80)]
    private string $lastName = '';

    #[ORM\Column(type: 'smallint')]
    #[Assert\NotNull(message: 'Indiquez l\'âge.'), Assert\Range(min: 0, max: 110, notInRangeMessage: 'Indiquez un âge entre 0 et 110 ans.')]
    private ?int $age = null;

    #[ORM\Column(length: 20, enumType: Meal::class)]
    #[Assert\NotNull(message: 'Choisissez un plat.')]
    private ?Meal $meal = null;

    /** Régime ou allergie à signaler au traiteur (végétarien, sans gluten…). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: '255 caractères au maximum.')]
    private ?string $comment = null;

    /** Prix payé pour cette place, figé à la réservation (centimes). */
    #[ORM\Column]
    private int $price = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $checkedInAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = trim((string) $firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = trim((string) $lastName);

        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName.' '.mb_strtoupper($this->lastName);
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function getMeal(): ?Meal
    {
        return $this->meal;
    }

    public function setMeal(?Meal $meal): static
    {
        $this->meal = $meal;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $comment = trim((string) $comment);
        $this->comment = '' === $comment ? null : $comment;

        return $this;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCheckedInAt(): ?\DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function setCheckedInAt(?\DateTimeImmutable $checkedInAt): static
    {
        $this->checkedInAt = $checkedInAt;

        return $this;
    }

    /** La réservation de ce convive est payée. */
    public function isPaid(): bool
    {
        return $this->booking?->isPaid() ?? false;
    }

    public function isCheckedIn(): bool
    {
        return null !== $this->checkedInAt;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
