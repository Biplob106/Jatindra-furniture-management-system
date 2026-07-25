<?php

namespace App\Enums;

/**
 * Which way physical money moved.
 *
 * `in` raises the account balance, `out` lowers it. Unlike the party ledgers,
 * this is not a matter of who owes whom; it is cash entering or leaving a
 * drawer or a bKash wallet.
 */
enum TransactionDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'জমা',
            self::Out => 'উত্তোলন',
        };
    }

    public function sign(): int
    {
        return $this === self::In ? 1 : -1;
    }
}
