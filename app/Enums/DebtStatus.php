<?php

namespace App\Enums;

enum DebtStatus: string
{
    case PendingAcceptance = 'pending_acceptance';
    case Active = 'active';
    case PaymentSubmitted = 'payment_submitted';
    case Settled = 'settled';
    case Declined = 'declined';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingAcceptance => 'Pending Acceptance',
            self::Active => 'Active',
            self::PaymentSubmitted => 'Payment Submitted',
            self::Settled => 'Settled',
            self::Declined => 'Declined',
        };
    }
}
