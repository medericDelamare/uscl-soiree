<?php

namespace App\Payment;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Development provider: a local page lets you simulate a successful or cancelled payment. */
final class FakePaymentProvider implements PaymentProviderInterface
{
    public const PAID = 'simulation-ok';

    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public static function getName(): string
    {
        return 'fake';
    }

    public function start(Payable $payable): string
    {
        return $this->urls->generate('payment_fake', ['token' => $payable->getAccessToken()]);
    }

    public function isPaid(Payable $payable): bool
    {
        return self::PAID === $payable->getPaymentReference();
    }
}
