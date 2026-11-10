<?php

namespace App\Event;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Réglages de la soirée (config/services.yaml, paramètre app.event) : à adapter chaque année. */
final readonly class EventInfo
{
    public \DateTimeImmutable $date;
    public ?\DateTimeImmutable $closesAt;

    /** @param list<string> $menu */
    public function __construct(
        #[Autowire('%app.event.name%')] public string $name,
        #[Autowire('%app.event.date%')] string $date,
        #[Autowire('%app.event.closes_at%')] ?string $closesAt,
        #[Autowire('%app.event.place%')] public string $place,
        #[Autowire('%app.event.capacity%')] public int $capacity,
        #[Autowire('%app.event.adult_price%')] public int $adultPrice,
        #[Autowire('%app.event.child_price%')] public int $childPrice,
        #[Autowire('%app.event.child_max_age%')] public int $childMaxAge,
        #[Autowire('%app.event.hold_minutes%')] public int $holdMinutes,
        #[Autowire('%app.event.menu%')] public array $menu,
    ) {
        $tz = new \DateTimeZone('Europe/Paris');
        $this->date = new \DateTimeImmutable($date, $tz);
        $this->closesAt = $closesAt ? new \DateTimeImmutable($closesAt, $tz) : null;
    }

    public function isChild(?int $age): bool
    {
        return null !== $age && $age <= $this->childMaxAge;
    }

    /** Prix d'un convive, en centimes. */
    public function priceFor(?int $age): int
    {
        return $this->isChild($age) ? $this->childPrice : $this->adultPrice;
    }

    /** Les inscriptions sont ouvertes jusqu'à la date limite (ou jusqu'au jour de la soirée). */
    public function isOpen(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $now < ($this->closesAt ?? $this->date);
    }
}
