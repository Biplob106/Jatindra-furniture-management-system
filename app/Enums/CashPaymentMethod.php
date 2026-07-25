<?php

namespace App\Enums;

/**
 * How money physically changed hands.
 *
 * Deliberately separate from PaymentMethod, which is the employee_ledger
 * column and has no cheque. docs/schema.md gives the two columns different
 * value sets and this mirrors that exactly rather than papering over it.
 */
enum CashPaymentMethod: string
{
    case Cash = 'cash';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Bank = 'bank';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'ক্যাশ',
            self::Bkash => 'বিকাশ',
            self::Nagad => 'নগদ',
            self::Bank => 'ব্যাংক',
            self::Cheque => 'চেক',
        };
    }
}
