<?php

namespace App\Enums;

/**
 * Who supplied the board a CNC job was cut from.
 *
 * When the customer brings their own, the job is pure machine time. When the
 * shop supplies it, the board came out of stock that was paid for earlier, and
 * the job's margin is not the whole amount charged.
 */
enum CncMaterialBy: string
{
    case Customer = 'customer';
    case Shop = 'shop';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'কাস্টমারের মাল',
            self::Shop => 'দোকানের মাল',
        };
    }
}
