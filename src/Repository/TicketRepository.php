<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Ticket> */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function findOneByToken(string $token): ?Ticket
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findOneByNumber(int $number): ?Ticket
    {
        return $this->findOneBy(['number' => $number]);
    }

    /** Numéros de cartons déjà créés. @return list<int> */
    public function existingNumbers(): array
    {
        return array_map('intval', array_column($this->createQueryBuilder('t')->select('t.number')->getQuery()->getScalarResult(), 'number'));
    }

    /** Libère les cartons d'une réservation (annulée) : ils peuvent être remis à quelqu'un d'autre. */
    public function releaseForBooking(Booking $booking): int
    {
        return $this->getEntityManager()->createQuery(
            'UPDATE App\Entity\Ticket t SET t.guest = NULL, t.activatedAt = NULL WHERE t.guest IN (SELECT g.id FROM App\Entity\Guest g WHERE g.booking = :b)'
        )->setParameter('b', $booking)->execute();
    }
}
