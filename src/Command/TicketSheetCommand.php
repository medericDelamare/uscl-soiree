<?php

namespace App\Command;

use App\Repository\TicketRepository;
use App\Service\TicketQrCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

#[AsCommand(name: 'app:ticket:sheet', description: 'Génère la feuille imprimable des cartons (HTML, 3 cartons par page A4) avec leur vrai QR code')]
class TicketSheetCommand
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly TicketQrCode $qr,
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Fichier de sortie')] string $output = 'var/cartons/cartons.html',
        #[Option('Premier numéro')] int $from = 1,
        #[Option('Dernier numéro')] int $to = 250,
    ): int {
        $cards = [];
        foreach ($this->tickets->findBy([], ['number' => 'ASC']) as $ticket) {
            if ($ticket->getNumber() < $from || $ticket->getNumber() > $to) {
                continue;
            }
            $cards[] = [
                'number' => $ticket->getNumber(),
                'qr' => 'data:image/png;base64,'.base64_encode($this->qr->pngForUrl($this->qr->urlForToken($ticket->getToken()), 420)),
            ];
        }
        if ([] === $cards) {
            $io->error('Aucun carton : lancez d\'abord app:ticket:generate.');

            return 1;
        }

        $font = fn (string $file): string => base64_encode((string) file_get_contents($this->projectDir.'/assets/fonts/'.$file));
        $html = $this->twig->render('ticket/sheet.html.twig', [
            'cards' => $cards,
            'logo' => 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents($this->projectDir.'/assets/images/logo-uscl.svg')),
            'fonts' => ['playfair' => $font('playfair-normal-700.woff2'), 'playfair_italic' => $font('playfair-italic-700.woff2'), 'inter' => $font('inter-variable.woff2')],
        ]);

        $path = str_starts_with($output, '/') ? $output : $this->projectDir.'/'.$output;
        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0o775, true);
        }
        file_put_contents($path, $html);
        $io->success(sprintf('%d carton(s), %d page(s) A4 : %s', \count($cards), (int) ceil(\count($cards) / 3), $path));

        return 0;
    }
}
