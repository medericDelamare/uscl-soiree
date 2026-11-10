<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Guest;
use App\Enum\BookingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Booking> */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /** Places prises : réservations payées + réservations en attente dont les places sont encore retenues. */
    public function countTakenSeats(int $holdMinutes, ?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();

        return (int) $this->getEntityManager()->createQuery(
            'SELECT COUNT(g.id) FROM App\Entity\Guest g JOIN g.booking b
             WHERE b.status = :paid OR (b.status = :pending AND b.createdAt > :limit)'
        )
            ->setParameter('paid', BookingStatus::Paid)
            ->setParameter('pending', BookingStatus::Pending)
            ->setParameter('limit', $now->modify("-$holdMinutes minutes"))
            ->getSingleScalarResult();
    }

    /** @return list<Booking> */
    public function findExpiredPending(int $holdMinutes, ?\DateTimeImmutable $now = null): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status = :pending')->andWhere('b.createdAt <= :limit')
            ->setParameter('pending', BookingStatus::Pending)
            ->setParameter('limit', ($now ?? new \DateTimeImmutable())->modify("-$holdMinutes minutes"))
            ->getQuery()->getResult();
    }

    /** @return list<Booking> */
    public function findPaid(): array
    {
        return $this->createQueryBuilder('b')->addSelect('g')->join('b.guests', 'g')
            ->where('b.status = :paid')->setParameter('paid', BookingStatus::Paid)
            ->orderBy('b.lastName')->addOrderBy('b.id')->addOrderBy('g.id')
            ->getQuery()->getResult();
    }

    /** Réservations payées ou en attente de paiement (pas les annulées), pour le listing complet. @return list<Booking> */
    public function findActive(): array
    {
        return $this->createQueryBuilder('b')->addSelect('g')->join('b.guests', 'g')
            ->where('b.status IN (:ok)')->setParameter('ok', [BookingStatus::Paid, BookingStatus::Pending])
            ->orderBy('b.lastName')->addOrderBy('b.id')->addOrderBy('g.id')
            ->getQuery()->getResult();
    }

    /**
     * Totaux des places payées, par plat et par catégorie : [meal => [adults, children]].
     *
     * @return array<string, array{adult: int, child: int}>
     */
    public function mealTotals(int $childMaxAge): array
    {
        $rows = $this->getEntityManager()->createQuery(
            'SELECT g.meal AS meal, SUM(CASE WHEN g.age <= :max THEN 1 ELSE 0 END) AS child, SUM(CASE WHEN g.age <= :max THEN 0 ELSE 1 END) AS adult
             FROM App\Entity\Guest g JOIN g.booking b WHERE b.status = :paid GROUP BY g.meal'
        )->setParameter('max', $childMaxAge)->setParameter('paid', BookingStatus::Paid)->getArrayResult();
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row['meal']->value] = ['adult' => (int) $row['adult'], 'child' => (int) $row['child']];
        }

        return $totals;
    }

    /**
     * Convives des réservations payées ayant une remarque (régime, allergie) : à transmettre au traiteur.
     *
     * @return list<\App\Entity\Guest>
     */
    public function findGuestsWithComment(): array
    {
        return $this->getEntityManager()->createQuery(
            'SELECT g, b FROM App\Entity\Guest g JOIN g.booking b
             WHERE b.status = :paid AND g.comment IS NOT NULL ORDER BY g.lastName, g.firstName'
        )->setParameter('paid', BookingStatus::Paid)->getResult();
    }

    /**
     * Convives des réservations payées, triés par nom. Sans terme : tous ; sinon filtrés
     * (nom ou prénom du convive, nom ou prénom du payeur, référence).
     *
     * @return list<Guest>
     */
    public function searchGuests(string $term = ''): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('g', 'b')->from(Guest::class, 'g')->join('g.booking', 'b')
            ->where('b.status = :paid')->setParameter('paid', BookingStatus::Paid)
            ->orderBy('g.lastName')->addOrderBy('g.firstName')->addOrderBy('g.id');

        if ('' !== $term) {
            $qb->andWhere('g.lastName LIKE :t OR g.firstName LIKE :t OR b.lastName LIKE :t OR b.firstName LIKE :t OR b.reference LIKE :t')
                ->setParameter('t', '%'.addcslashes($term, '%_').'%');
        }

        return $qb->getQuery()->getResult();
    }
}
