<?php

namespace App\Entity;

use App\Enum\BookingStatus;
use App\Payment\Payable;
use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Une réservation : un payeur, un ou plusieurs convives, un seul paiement HelloAsso. */
#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking implements Payable
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** Numéro lisible, donné au payeur et affiché sur le billet. */
    #[ORM\Column(length: 20, unique: true)]
    private string $reference;

    /** Secret de la page de réservation, du QR code et des URL de retour de paiement. */
    #[ORM\Column(length: 32, unique: true)]
    private string $accessToken;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Indiquez votre prénom.'), Assert\Length(max: 80)]
    private string $firstName = '';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Indiquez votre nom.'), Assert\Length(max: 80)]
    private string $lastName = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Indiquez votre adresse e-mail.'), Assert\Email(message: 'Cette adresse e-mail n\'est pas valide.')]
    private string $email = '';

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Indiquez un numéro de téléphone.'), Assert\Length(max: 30)]
    #[Assert\Regex('/^[0-9 .+()-]{8,}$/', message: 'Ce numéro de téléphone n\'est pas valide.')]
    private string $phone = '';

    /** @var Collection<int, Guest> */
    #[ORM\OneToMany(targetEntity: Guest::class, mappedBy: 'booking', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    #[Assert\Count(min: 1, max: 20, minMessage: 'Ajoutez au moins un convive.', maxMessage: 'Vingt places au maximum par réservation.')]
    #[Assert\Valid]
    private Collection $guests;

    #[ORM\Column(length: 20, enumType: BookingStatus::class)]
    private BookingStatus $status = BookingStatus::Pending;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /** "helloasso", "fake" ou "manuel" (saisi par le club : espèces, chèque…). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $paymentProvider = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $paymentReference = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $paymentIntents = [];

    public function __construct()
    {
        $this->guests = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->accessToken = bin2hex(random_bytes(16));
        $this->reference = 'SOI-'.strtoupper(bin2hex(random_bytes(3)));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = mb_strtolower(trim((string) $email));

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = trim((string) $phone);

        return $this;
    }

    /** @return Collection<int, Guest> */
    public function getGuests(): Collection
    {
        return $this->guests;
    }

    public function addGuest(Guest $guest): static
    {
        if (!$this->guests->contains($guest)) {
            $this->guests->add($guest);
            $guest->setBooking($this);
        }

        return $this;
    }

    public function removeGuest(Guest $guest): static
    {
        $this->guests->removeElement($guest);

        return $this;
    }

    public function getStatus(): BookingStatus
    {
        return $this->status;
    }

    public function setStatus(BookingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    /** Total en centimes. */
    public function getTotal(): int
    {
        return array_sum($this->guests->map(fn (Guest $g) => $g->getPrice())->toArray());
    }

    public function getAmountDue(): int
    {
        return $this->isPaid() ? 0 : $this->getTotal();
    }

    public function getPaymentLabel(): string
    {
        return sprintf('Soirée USCL – %d place%s (%s)', $this->guests->count(), $this->guests->count() > 1 ? 's' : '', $this->reference);
    }

    public function getPaymentBackRoute(): string
    {
        return 'home';
    }

    public function isPaid(): bool
    {
        return BookingStatus::Paid === $this->status;
    }

    public function markPaid(string $provider, ?string $reference, ?\DateTimeImmutable $at = null): static
    {
        $this->status = BookingStatus::Paid;
        $this->paymentProvider = $provider;
        $this->paymentReference = $reference ?? $this->paymentReference;
        $this->paidAt = $at ?? new \DateTimeImmutable();

        return $this;
    }

    public function getPaymentProvider(): ?string
    {
        return $this->paymentProvider;
    }

    public function setPaymentProvider(?string $paymentProvider): static
    {
        $this->paymentProvider = $paymentProvider;

        return $this;
    }

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    public function setPaymentReference(?string $paymentReference): static
    {
        $this->paymentReference = $paymentReference;

        return $this;
    }

    public function getPaymentIntents(): array
    {
        return $this->paymentIntents;
    }

    public function addPaymentIntent(string $intent): static
    {
        $this->paymentIntents[] = $intent;

        return $this;
    }

    /** Nombre de convives déjà entrés. */
    public function getCheckedInCount(): int
    {
        return $this->guests->filter(fn (Guest $g) => $g->isCheckedIn())->count();
    }

    public function __toString(): string
    {
        return $this->reference;
    }
}
