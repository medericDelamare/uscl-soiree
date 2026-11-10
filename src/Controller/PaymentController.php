<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Payment\FakePaymentProvider;
use App\Payment\PaymentManager;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/paiement')]
final class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly BookingRepository $bookings,
    ) {
    }

    /** Où le payeur revient après la page de paiement (succès, échec ou annulation). */
    #[Route('/retour/{token}', name: 'payment_return', methods: ['GET'], requirements: ['token' => '[a-f0-9]{32}'])]
    public function return(string $token, LoggerInterface $logger): Response
    {
        $booking = $this->booking($token);
        try {
            $paid = $this->payments->confirm($booking);
        } catch (\Throwable $e) {
            $logger->error('Vérification du paiement impossible', ['exception' => $e, 'booking' => $booking->getReference()]);
            $paid = false;
        }
        $this->addFlash(...$paid
            ? ['success', 'Merci ! Votre paiement est confirmé. Votre billet vous a aussi été envoyé par e-mail.']
            : ['warning', 'Le paiement n\'a pas abouti : votre réservation n\'est pas encore confirmée.']);

        return $this->redirectToRoute('booking_show', ['token' => $booking->getAccessToken()]);
    }

    /** Notification serveur à serveur de HelloAsso. Son contenu n'est pas cru : le paiement est revérifié par l'API. */
    #[Route('/helloasso/notification', name: 'payment_webhook', methods: ['POST'])]
    public function webhook(Request $request, LoggerInterface $logger): Response
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $reference = $payload['metadata']['reference'] ?? null;
        $booking = \is_string($reference) ? $this->bookings->findOneBy(['reference' => $reference]) : null;
        if ($booking && 'helloasso' === $booking->getPaymentProvider()) {
            try {
                $this->payments->confirm($booking);
            } catch (\Throwable $e) {
                $logger->error('Notification HelloAsso : vérification impossible', ['exception' => $e, 'booking' => $reference]);

                return new Response('', 500);
            }
        }

        return new Response('', 200);
    }

    /** Page de paiement locale utilisée quand PAYMENT_PROVIDER=fake. */
    #[Route('/simulation/{token}', name: 'payment_fake', methods: ['GET', 'POST'], requirements: ['token' => '[a-f0-9]{32}'])]
    public function fake(string $token, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->payments->getProvider() instanceof FakePaymentProvider) {
            throw $this->createNotFoundException();
        }
        $booking = $this->booking($token);
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('fake'.$booking->getAccessToken(), $request->request->getString('_token'))) {
            if ($request->request->has('pay')) {
                $booking->setPaymentReference(FakePaymentProvider::PAID);
                $em->flush();
            }

            return $this->redirectToRoute('payment_return', ['token' => $booking->getAccessToken()]);
        }

        return $this->render('booking/payment_fake.html.twig', ['booking' => $booking]);
    }

    private function booking(string $token): Booking
    {
        return $this->bookings->findOneBy(['accessToken' => $token]) ?? throw $this->createNotFoundException();
    }
}
