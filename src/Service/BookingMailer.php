<?php

namespace App\Service;

use App\Entity\Booking;
use App\Payment\Payable;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

final class BookingMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly TicketQrCode $qrCode,
        #[Autowire('%env(SOIREE_EMAIL_FROM)%')] private readonly string $from,
        #[Autowire('%env(SOIREE_EMAIL_ADMIN)%')] private readonly string $admin,
    ) {
    }

    /** Billet (QR code) au payeur, notification au club. */
    public function sendConfirmation(Payable $booking): void
    {
        if (!$booking instanceof Booking) {
            return;
        }
        $qr = (new DataPart($this->qrCode->png($booking), 'billet.png', 'image/png'))->asInline();
        $this->send((new TemplatedEmail())
            ->to(new Address($booking->getEmail(), $booking->getFullName()))
            ->subject('Votre réservation '.$booking->getReference().' – Soirée de l\'US Cormeilles Lieurey')
            ->addPart($qr)
            ->htmlTemplate('emails/confirmation.html.twig')
            ->context(['booking' => $booking, 'qr_cid' => 'billet.png']));

        $this->send((new TemplatedEmail())
            ->to($this->admin)
            ->subject(sprintf('Nouvelle réservation %s : %d place(s), %s €', $booking->getReference(), $booking->getGuests()->count(), number_format($booking->getTotal() / 100, 2, ',', ' ')))
            ->htmlTemplate('emails/admin.html.twig')
            ->context(['booking' => $booking]));
    }

    private function send(TemplatedEmail $email): void
    {
        try {
            $this->mailer->send($email->from(new Address($this->from, 'Soirée US Cormeilles Lieurey')));
        } catch (TransportExceptionInterface $e) {
            // Un échec d'envoi ne doit jamais annuler une réservation payée : le billet reste sur la page de réservation.
            $this->logger->error('Envoi e-mail impossible', ['exception' => $e]);
        }
    }
}
