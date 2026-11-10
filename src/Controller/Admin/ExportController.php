<?php

namespace App\Controller\Admin;

use App\Entity\Guest;
use App\Event\EventInfo;
use App\Repository\BookingRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/export', name: 'admin_export')]
#[IsGranted('ROLE_ADMIN')]
final class ExportController extends AbstractController
{
    public function __invoke(BookingRepository $bookings, EventInfo $event): Response
    {
        $book = new Spreadsheet();

        // Un listing complet (payés et en attente), trié par nom de convive
        $rows = [];
        foreach ($bookings->findActive() as $booking) {
            foreach ($booking->getGuests() as $g) {
                $rows[] = [$booking, $g];
            }
        }
        usort($rows, fn (array $x, array $y) => [mb_strtolower($x[1]->getLastName()), mb_strtolower($x[1]->getFirstName())] <=> [mb_strtolower($y[1]->getLastName()), mb_strtolower($y[1]->getFirstName())]);

        $sheet = $book->getActiveSheet()->setTitle('Convives');
        $sheet->fromArray(['Nom', 'Prénom', 'Âge', 'Catégorie', 'Plat', 'Remarque (régime, allergie)', 'Prix (€)', 'Payé', 'Réservation', 'Payeur', 'E-mail', 'Téléphone', 'Entré'], null, 'A1');
        $row = 2;
        foreach ($rows as [$booking, $g]) {
            /** @var Guest $g */
            $sheet->fromArray([
                mb_strtoupper($g->getLastName()), $g->getFirstName(), $g->getAge(),
                $event->isChild($g->getAge()) ? 'Enfant' : 'Adulte', $g->getMeal()?->label(), $g->getComment(), $g->getPrice() / 100,
                $booking->isPaid() ? 'oui' : 'non',
                $booking->getReference(), $booking->getFullName(), $booking->getEmail(), $booking->getPhone(),
                $g->isCheckedIn() ? 'oui' : '',
            ], null, 'A'.$row++);
        }
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->setAutoFilter('A1:M'.max(2, $row - 1));

        $totals = $book->createSheet()->setTitle('Traiteur');
        $totals->fromArray(['Plat', 'Adultes', 'Enfants', 'Total'], null, 'A1');
        $counts = $bookings->mealTotals($event->childMaxAge);
        $r = 2;
        foreach (\App\Enum\Meal::cases() as $meal) {
            $a = $counts[$meal->value]['adult'] ?? 0;
            $c = $counts[$meal->value]['child'] ?? 0;
            $totals->fromArray([$meal->label(), $a, $c, $a + $c], null, 'A'.$r++);
        }
        $totals->fromArray(['Total', '=SUM(B2:B'.($r - 1).')', '=SUM(C2:C'.($r - 1).')', '=SUM(D2:D'.($r - 1).')'], null, 'A'.$r);
        $totals->getStyle('A1:D1')->getFont()->setBold(true);
        $totals->getStyle('A'.$r.':D'.$r)->getFont()->setBold(true);
        foreach (range('A', 'D') as $col) {
            $totals->getColumnDimension($col)->setAutoSize(true);
        }
        $book->setActiveSheetIndex(0);

        $filename = 'soiree-'.$event->date->format('Y-m-d').'-convives.xlsx';

        return new StreamedResponse(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
