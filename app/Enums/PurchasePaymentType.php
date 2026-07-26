<?php

namespace App\Enums;

/**
 * How a challan was settled at the counter.
 *
 * `credit` is the one that matters: it writes the purchase and the supplier
 * ledger credit and nothing to transactions, because no money moved.
 */
enum PurchasePaymentType: string
{
    case Cash = 'cash';
    case Credit = 'credit';
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'নগদ',
            self::Credit => 'বাকি',
            self::Partial => 'আংশিক',
        };
    }
}
