<?php

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Carton d'invitation numéroté, imprimé à l'avance (un carton = une personne).
 * Il ne donne accès à rien tant qu'il n'est pas relié à un convive (carton « attribué »).
 */
#[ORM\Entity(repositoryClass: TicketRepository::class)]
class Ticket
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** Numéro imprimé sur le talon et le carton (aussi numéro de tombola). */
    #[ORM\Column(unique: true)]
    private int $number;

    /** Contenu du QR code : impossible à deviner. */
    #[ORM\Column(length: 32, unique: true)]
    private string $token;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Guest $guest = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $activatedAt = null;

    public function __construct(int $number)
    {
        $this->number = $number;
        $this->token = bin2hex(random_bytes(16));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getGuest(): ?Guest
    {
        return $this->guest;
    }

    public function attachTo(Guest $guest): static
    {
        $this->guest = $guest;
        $this->activatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function release(): static
    {
        $this->guest = null;
        $this->activatedAt = null;

        return $this;
    }

    public function isActive(): bool
    {
        return null !== $this->guest;
    }

    public function getActivatedAt(): ?\DateTimeImmutable
    {
        return $this->activatedAt;
    }
}
