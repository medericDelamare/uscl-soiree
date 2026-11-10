<?php

namespace App\Service;

use App\Entity\Booking;
use App\Event\EventInfo;
use App\Repository\BookingRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class BookingFactory
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookingRepository $bookings,
        private readonly EventInfo $event,
    ) {
    }

    /** Places encore disponibles (celles des paiements en cours sont retenues quelques minutes). */
    public function seatsLeft(): int
    {
        return max(0, $this->event->capacity - $this->bookings->countTakenSeats($this->event->holdMinutes));
    }

    /**
     * Fige les prix et enregistre la réservation si les places sont disponibles.
     * Le verrou évite que deux réservations simultanées dépassent la jauge.
     *
     * @return bool false si la jauge serait dépassée
     */
    public function place(Booking $booking): bool
    {
        foreach ($booking->getGuests() as $guest) {
            $guest->setPrice($this->event->priceFor($guest->getAge()));
        }
        /** @var Connection $db */
        $db = $this->em->getConnection();
        $db->fetchOne("SELECT GET_LOCK('soiree_booking', 10)");
        try {
            if ($booking->getGuests()->count() > $this->seatsLeft()) {
                return false;
            }
            $this->em->persist($booking);
            $this->em->flush();
        } finally {
            $db->fetchOne("SELECT RELEASE_LOCK('soiree_booking')");
        }

        return true;
    }
}
