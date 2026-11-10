<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Guest;
use App\Repository\BookingRepository;
use App\Repository\TicketRepository;
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
    public function home(Request $request, BookingRepository $bookings, TicketRepository $tickets, EntityManagerInterface $em): Response
    {
        $term = trim($request->query->getString('q'));
        // Un numéro seul (1 à 3 chiffres) est le numéro d'un carton : on ouvre directement la personne.
        if (preg_match('/^\d{1,3}$/', $term) && null !== $ticket = $tickets->findOneByNumber((int) $term)) {
            return $this->redirectToRoute('control_show', ['token' => $ticket->getToken()]);
        }
        $present = (int) $em->createQuery('SELECT COUNT(g.id) FROM App\Entity\Guest g WHERE g.checkedInAt IS NOT NULL')->getSingleScalarResult();
        $expected = (int) $em->createQuery('SELECT COUNT(g.id) FROM App\Entity\Guest g JOIN g.booking b WHERE b.status = :paid')->setParameter('paid', \App\Enum\BookingStatus::Paid)->getSingleScalarResult();

        return $this->render('control/home.html.twig', [
            'term' => $term,
            'results' => $bookings->searchGuests(mb_strlen($term) >= 2 ? $term : ''),
            'present' => $present,
            'expected' => $expected,
        ]);
    }

    /** Le QR d'un billet de réservation ouvre la réservation ; celui d'un carton numéroté ouvre la personne. */
    #[Route('/{token}', name: 'control_show', methods: ['GET'], requirements: ['token' => '[a-f0-9]{32}'])]
    public function show(string $token, BookingRepository $bookings, TicketRepository $tickets): Response
    {
        if (null !== $booking = $bookings->findOneBy(['accessToken' => $token])) {
            return $this->render('control/show.html.twig', ['booking' => $booking]);
        }
        if (null !== $ticket = $tickets->findOneByToken($token)) {
            return $this->render('control/ticket.html.twig', ['ticket' => $ticket]);
        }

        throw $this->createNotFoundException();
    }

    /** Valide (ou annule) l'entrée de la personne d'un carton. */
    #[Route('/{token}/carton', name: 'control_ticket_checkin', methods: ['POST'], requirements: ['token' => '[a-f0-9]{32}'])]
    public function ticketCheckIn(string $token, Request $request, TicketRepository $tickets, EntityManagerInterface $em): Response
    {
        $ticket = $tickets->findOneByToken($token) ?? throw $this->createNotFoundException();
        $guest = $ticket->getGuest();
        if (null !== $guest && $guest->getBooking()->isPaid() && $this->isCsrfTokenValid('carton'.$ticket->getId(), $request->request->getString('_token'))) {
            $guest->setCheckedInAt($request->request->getBoolean('undo') ? null : ($guest->getCheckedInAt() ?? new \DateTimeImmutable()));
            $em->flush();
        }

        return $this->redirectToRoute('control_show', ['token' => $ticket->getToken()]);
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
