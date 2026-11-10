<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Guest;
use App\Event\EventInfo;
use App\Form\BookingType;
use App\Payment\PaymentManager;
use App\Payment\PaymentRefusedException;
use App\Service\BookingFactory;
use App\Service\TicketQrCode;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;

final class BookingController extends AbstractController
{
    public function __construct(
        private readonly EventInfo $event,
        private readonly BookingFactory $factory,
        private readonly PaymentManager $payments,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET', 'POST'])]
    public function home(Request $request, #[Autowire(service: 'limiter.booking')] RateLimiterFactoryInterface $limiter): Response
    {
        $seatsLeft = $this->factory->seatsLeft();
        $open = $this->event->isOpen();
        $booking = new Booking();
        $booking->addGuest(new Guest());
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($open && $seatsLeft > 0 && $form->isSubmitted() && $form->isValid()) {
            if (!$limiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('warning', 'Trop de tentatives. Réessayez dans un moment.');
            } elseif (!$this->factory->place($booking)) {
                $this->addFlash('warning', sprintf('Il ne reste que %d place(s) : réduisez le nombre de convives.', $this->factory->seatsLeft()));
            } else {
                return $this->pay($booking);
            }
        }

        return $this->render('booking/home.html.twig', [
            'form' => $form,
            'seatsLeft' => $seatsLeft,
            'open' => $open,
        ]);
    }

    #[Route('/reservation/{token}', name: 'booking_show', methods: ['GET'], requirements: ['token' => '[a-f0-9]{32}'])]
    public function show(#[MapEntity(mapping: ['token' => 'accessToken'])] Booking $booking, TicketQrCode $qr): Response
    {
        return $this->render('booking/show.html.twig', [
            'booking' => $booking,
            'qr' => $booking->isPaid() ? $qr->dataUri($booking) : null,
        ]);
    }

    #[Route('/reservation/{token}/payer', name: 'booking_pay', methods: ['POST'], requirements: ['token' => '[a-f0-9]{32}'])]
    public function retry(#[MapEntity(mapping: ['token' => 'accessToken'])] Booking $booking, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('pay'.$booking->getId(), $request->request->getString('_token'))) {
            return $this->redirectToRoute('booking_show', ['token' => $booking->getAccessToken()]);
        }
        try {
            // Une page de paiement précédente a peut-être été payée sans que le retour ne nous parvienne : jamais deux fois.
            if ($this->payments->confirm($booking)) {
                $this->addFlash('success', 'Cette réservation est déjà payée.');

                return $this->redirectToRoute('booking_show', ['token' => $booking->getAccessToken()]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Vérification du paiement impossible', ['exception' => $e, 'booking' => $booking->getReference()]);
            $this->addFlash('danger', 'Le paiement en ligne est momentanément indisponible. Réessayez dans quelques minutes.');

            return $this->redirectToRoute('booking_show', ['token' => $booking->getAccessToken()]);
        }
        if (\App\Enum\BookingStatus::Cancelled === $booking->getStatus()) {
            $this->addFlash('warning', 'Cette réservation a expiré : faites-en une nouvelle.');

            return $this->redirectToRoute('home');
        }

        return $this->pay($booking);
    }

    private function pay(Booking $booking): Response
    {
        try {
            return $this->redirect($this->payments->start($booking));
        } catch (PaymentRefusedException $e) {
            $this->addFlash('danger', $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Création du paiement impossible', ['exception' => $e, 'booking' => $booking->getReference()]);
            $this->addFlash('danger', 'Le paiement en ligne est momentanément indisponible. Votre réservation est enregistrée : réessayez depuis cette page.');
        }

        return $this->redirectToRoute('booking_show', ['token' => $booking->getAccessToken()]);
    }
}
