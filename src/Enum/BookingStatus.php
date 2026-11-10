<?php

namespace App\Enum;

enum BookingStatus: string
{
    /** Places retenues quelques minutes, en attente du paiement HelloAsso. */
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de paiement',
            self::Paid => 'Payée',
            self::Cancelled => 'Annulée',
        };
    }
}
