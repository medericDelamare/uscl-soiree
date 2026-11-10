<?php

namespace App\Service;

use App\Entity\Booking;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** QR code du billet : il contient l'adresse de contrôle, que n'importe quel appareil photo de téléphone sait ouvrir. */
final class TicketQrCode
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function url(Booking $booking): string
    {
        return $this->urls->generate('control_show', ['token' => $booking->getAccessToken()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /** Adresse de contrôle d'un carton numéroté. */
    public function urlForToken(string $token): string
    {
        return $this->urls->generate('control_show', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function png(Booking $booking, int $size = 360): string
    {
        return $this->pngForUrl($this->url($booking), $size);
    }

    public function pngForUrl(string $url, int $size = 360): string
    {
        return (new Builder(
            writer: new PngWriter(),
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 10,
        ))->build()->getString();
    }

    public function dataUri(Booking $booking, int $size = 360): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($booking, $size));
    }
}
