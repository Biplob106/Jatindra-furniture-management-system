<?php

namespace App\Enums;

/**
 * Why a supplier_ledger row exists.
 *
 * Direction is fixed here rather than passed in at every call site, the same
 * way LedgerEntryType does it for workers, because a sign error in this table
 * is money quietly moving the wrong way.
 *
 * Positive balance means we owe them, so a purchase raises what we owe and a
 * payment lowers it. A goods return and a discount both lower it too: the
 * stock went back or the price came down, either way we owe less.
 */
enum SupplierLedgerEntryType: string implements LedgerEntryKind
{
    // We owe more.
    case Purchase = 'purchase';

    // We owe less.
    case Payment = 'payment';
    case Return = 'return';
    case Discount = 'discount';

    // Either direction, caller decides. An opening due is a credit, but a
    // supplier we had already overpaid on day one is a debit.
    case Opening = 'opening';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'প্রারম্ভিক',
            self::Purchase => 'ক্রয়',
            self::Payment => 'পরিশোধ',
            self::Return => 'ফেরত',
            self::Discount => 'ছাড়',
            self::Adjustment => 'সংশোধন',
        };
    }

    /**
     * The direction this type always moves, or null when the caller must say.
     */
    public function direction(): ?LedgerDirection
    {
        return match ($this) {
            self::Purchase => LedgerDirection::Credit,
            self::Payment, self::Return, self::Discount => LedgerDirection::Debit,
            self::Opening, self::Adjustment => null,
        };
    }
}
