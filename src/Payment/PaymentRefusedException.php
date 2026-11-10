<?php

namespace App\Payment;

/** The provider refused the order data (e.g. a name HelloAsso does not accept); the message is meant for the buyer. */
final class PaymentRefusedException extends \RuntimeException
{
}
