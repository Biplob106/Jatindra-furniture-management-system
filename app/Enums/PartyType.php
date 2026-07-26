<?php

namespace App\Enums;

/**
 * Who is on the other side of a party_payments row. A supplier is paid, a
 * customer pays us.
 */
enum PartyType: string
{
    case Supplier = 'supplier';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'সরবরাহকারী',
            self::Customer => 'গ্রাহক',
        };
    }
}
