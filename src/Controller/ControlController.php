<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Guest;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Contrôle à l'entrée : le QR code du billet ouvre directement la réservation. Réservé aux bénévoles connectés. */
#[Route('/controle')]
#[IsGranted('ROLE_CONTROLE')]
final class ControlController extends AbstractController
{
    #[Route('', name: 'control_home', methods: ['GET'])]
    public function home(Request $request, BookingRepository $bookings, EntityManagerInterface $em): Response
    {
        $term = trim($request->query->getString('q'));
        $present = (int) $em->createQuery('SELECT COUNT(g.id) FROM App\Entity\Guest g WHERE g.checkedInAt IS NOT NULL')->getSingleScalarResult();
        $expected = (int) $em->createQuery('SELECT COUNT(g.id) FROM App\Entity\Guest g JOIN g.booking b WHERE b.status = :paid')->setParameter('paid', \App\Enum\BookingStatus::Paid)->getSingleScalarResult();

        return $this->render('control/home.html.twig', [
            'term' => $term,
            'results' => $bookings->searchGuests(mb_strlen($term) >= 2 ? $term : ''),
            'present' => $present,
            'expected' => $expected,
        ]);
    }

    #[Route('/{token}', name: 'control_show', methods: ['GET'], requirements: ['token' => '[a-f0-9]{32}'])]
    public function show(#[MapEntity(mapping: ['token' => 'accessToken'])] Booking $booking): Response
    {
        return $this->render('control/show.html.twig', ['booking' => $booking]);
    }

    /** Valide l'entrée d'un convive, ou de tous ceux de la réservation avec guest=all. */
    #[Route('/{token}/entree', name: 'control_checkin', methods: ['POST'], requirements: ['token' => '[a-f0-9]{32}'])]
    public function checkIn(#[MapEntity(mapping: ['token' => 'accessToken'])] Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('checkin'.$booking->getId(), $request->request->getString('_token')) && $booking->isPaid()) {
            $which = $request->request->getString('guest');
            $undo = $request->request->getBoolean('undo');
            $now = new \DateTimeImmutable();
            foreach ($booking->getGuests() as $guest) {
                /** @var Guest $guest */
                if ('all' === $which && !$undo && !$guest->isCheckedIn()) {
                    $guest->setCheckedInAt($now);
                } elseif ((string) $guest->getId() === $which) {
                    $guest->setCheckedInAt($undo ? null : ($guest->getCheckedInAt() ?? $now));
                }
            }
            $em->flush();
        }

        // Depuis la liste des invités : on y revient, avec la même recherche, à la ligne validée.
        if ('list' === $request->request->getString('back')) {
            $query = trim($request->request->getString('q'));

            return $this->redirect($this->generateUrl('control_home', '' !== $query ? ['q' => $query] : []).'#invite-'.(int) $request->request->getString('guest'));
        }

        return $this->redirectToRoute('control_show', ['token' => $booking->getAccessToken()]);
    }
}
