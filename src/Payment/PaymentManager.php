<?php

namespace App\Payment;

use App\Service\BookingMailer;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class PaymentManager
{
    public function __construct(
        #[AutowireLocator('app.payment_provider', defaultIndexMethod: 'getName')] private readonly ServiceLocator $providers,
        #[Autowire('%env(PAYMENT_PROVIDER)%')] private readonly string $providerName,
        private readonly EntityManagerInterface $em,
        private readonly BookingMailer $mailer,
    ) {
    }

    public function getProvider(): PaymentProviderInterface
    {
        return $this->providers->get($this->providerName);
    }

    public function start(Payable $payable): string
    {
        $url = $this->getProvider()->start($payable);
        $payable->setPaymentProvider($this->getProvider()::getName());
        $this->em->flush();

        return $url;
    }

    /** Vérifie le paiement auprès du prestataire et confirme la réservation une seule fois. Peut être appelé plusieurs fois. */
    public function confirm(Payable $payable): bool
    {
        if ($payable->isPaid()) {
            return true;
        }
        // Le retour du payeur et la notification HelloAsso peuvent arriver en même temps : on verrouille la ligne.
        $confirmed = $this->em->wrapInTransaction(function () use ($payable): ?bool {
            $this->em->refresh($payable, LockMode::PESSIMISTIC_WRITE);
            if ($payable->isPaid()) {
                return false;
            }
            if (!$this->getProvider()->isPaid($payable)) {
                return null;
            }
            $payable->markPaid($this->getProvider()::getName(), $payable->getPaymentReference());

            return true;
        });
        if (true === $confirmed) {
            $this->mailer->sendConfirmation($payable);
        }

        return null !== $confirmed;
    }
}
