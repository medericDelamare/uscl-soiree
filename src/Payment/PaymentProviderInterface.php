<?php

namespace App\Payment;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.payment_provider')]
interface PaymentProviderInterface
{
    public static function getName(): string;

    /** Prepares the payment and returns the URL the payer must be redirected to. */
    public function start(Payable $payable): string;

    /** Asks the provider whether it has actually been paid (never trust the return URL alone). */
    public function isPaid(Payable $payable): bool;
}
