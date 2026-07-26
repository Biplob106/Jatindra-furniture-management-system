<?php

namespace App\Enums;

/**
 * How much of a challan is still owed. Derived from due_amount when a payment
 * lands, never set by hand on a form.
 */
enum PurchaseStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'বাকি',
            self::Partial => 'আংশিক পরিশোধ',
            self::Paid => 'পরিশোধিত',
            self::Returned => 'ফেরত',
        };
    }

    /** Still owed something, so it shows on the payable and aging lists. */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Partial;
    }
}
