<?php

namespace App\Command;

use App\Enum\BookingStatus;
use App\Event\EventInfo;
use App\Payment\PaymentManager;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:booking:purge-pending', description: 'Annule les réservations non payées dont les places ne sont plus retenues (à lancer toutes les 15 minutes par cron)')]
class BookingPurgePendingCommand
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly EntityManagerInterface $em,
        private readonly EventInfo $event,
        private readonly PaymentManager $payments,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $cancelled = 0;
        foreach ($this->bookings->findExpiredPending($this->event->holdMinutes) as $booking) {
            try {
                // Le payeur a peut-être payé sans que le retour ne nous parvienne : on vérifie avant d'annuler.
                if ($booking->getPaymentIntents() && $this->payments->confirm($booking)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }
            $booking->setStatus(BookingStatus::Cancelled);
            ++$cancelled;
        }
        $this->em->flush();
        $io->success("$cancelled réservation(s) expirée(s) annulée(s).");

        return 0;
    }
}
