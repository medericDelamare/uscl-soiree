<?php

namespace App\Command;

use App\Entity\Ticket;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:ticket:generate', description: 'Crée les cartons numérotés de 1 à N (ceux qui existent déjà ne sont pas touchés)')]
class TicketGenerateCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TicketRepository $tickets,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Nombre de cartons')] int $count = 250): int
    {
        if ($count < 1 || $count > 2000) {
            $io->error('Indiquez un nombre entre 1 et 2000.');

            return 1;
        }
        $existing = array_flip($this->tickets->existingNumbers());
        $created = 0;
        for ($n = 1; $n <= $count; ++$n) {
            if (!isset($existing[$n])) {
                $this->em->persist(new Ticket($n));
                ++$created;
            }
        }
        $this->em->flush();
        $io->success(sprintf('%d carton(s) créé(s), %d existaient déjà.', $created, $count - $created));

        return 0;
    }
}
