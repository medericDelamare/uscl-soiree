<?php

namespace App\Command;

use App\Event\EventInfo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:booking:purge-old', description: 'Supprime les réservations (et leurs convives) conservées au-delà de la durée annoncée dans la politique de confidentialité')]
class BookingPurgeOldCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventInfo $event,
        #[Autowire('%app.retention_months%')] private readonly int $months,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $limit = $this->event->date->modify("+{$this->months} months");
        if (new \DateTimeImmutable() < $limit) {
            $io->info('Rien à supprimer avant le '.$limit->format('d/m/Y').'.');

            return 0;
        }
        $deleted = $this->em->createQuery('DELETE FROM App\Entity\Booking b')->execute();
        $io->success("$deleted réservation(s) supprimée(s).");

        return 0;
    }
}
