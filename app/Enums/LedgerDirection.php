<?php

namespace App\Enums;

/**
 * Which way a ledger row moves the balance.
 *
 * employee_ledger: SUM(credit) - SUM(debit), positive means the shop owes the
 * worker. supplier_ledger: same arithmetic, positive means we owe the supplier.
 * Inverting either of these is silently wrong money.
 */
enum LedgerDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'জমা',
            self::Debit => 'খরচ',
        };
    }

    /**
     * The multiplier this direction contributes to a balance sum.
     */
    public function sign(): int
    {
        return $this === self::Credit ? 1 : -1;
    }
}
