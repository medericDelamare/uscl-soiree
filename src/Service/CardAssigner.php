<?php

namespace App\Service;

use App\Entity\Booking;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Relie les cartons numérotés remis aux convives d'une réservation saisie à la main. */
final class CardAssigner
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return list<string> messages d'erreur (vide si tout est bon) */
    public function check(Booking $booking): array
    {
        $errors = [];
        $seen = [];
        foreach ($booking->getGuests() as $guest) {
            $number = $guest->getCardNumber();
            if (null === $number) {
                continue;
            }
            if (isset($seen[$number])) {
                $errors[] = sprintf('Le carton n° %d est saisi deux fois.', $number);
                continue;
            }
            $seen[$number] = true;
            $ticket = $this->tickets->findOneByNumber($number);
            if (null === $ticket) {
                $errors[] = sprintf('Le carton n° %d n\'existe pas.', $number);
            } elseif ($ticket->isActive()) {
                $errors[] = sprintf('Le carton n° %d est déjà attribué à une autre personne.', $number);
            }
        }

        return $errors;
    }

    /** À appeler une fois la réservation enregistrée (les convives ont leur identifiant). */
    public function assign(Booking $booking): int
    {
        $count = 0;
        foreach ($booking->getGuests() as $guest) {
            $number = $guest->getCardNumber();
            if (null === $number) {
                continue;
            }
            $ticket = $this->tickets->findOneByNumber($number);
            if (null !== $ticket && !$ticket->isActive()) {
                $ticket->attachTo($guest);
                ++$count;
            }
        }
        $this->em->flush();

        return $count;
    }

    public function release(Booking $booking): void
    {
        $this->tickets->releaseForBooking($booking);
    }
}
