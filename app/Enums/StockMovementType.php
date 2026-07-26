<?php

namespace App\Enums;

/**
 * Why a finished product moved.
 *
 * `production_in` is a piece the shop built for the floor rather than for an
 * order; `order_out` is a piece taken off the floor to fill one. Both happen,
 * and telling them apart is what keeps the retail margin honest.
 */
enum StockMovementType: string
{
    case ProductionIn = 'production_in';
    case PurchaseIn = 'purchase_in';
    case SaleOut = 'sale_out';
    case OrderOut = 'order_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Damage = 'damage';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::ProductionIn => 'তৈরি হয়েছে',
            self::PurchaseIn => 'কেনা হয়েছে',
            self::SaleOut => 'বিক্রি',
            self::OrderOut => 'অর্ডারে গেছে',
            self::TransferIn => 'দোকানে এসেছে',
            self::TransferOut => 'অন্য দোকানে গেছে',
            self::Damage => 'নষ্ট',
            self::Adjustment => 'সংশোধন',
        };
    }

    /**
     * The multiplier this type contributes to stock on hand, or null for
     * `adjustment`, which goes whichever way the counted stock says.
     */
    public function sign(): ?int
    {
        return match ($this) {
            self::ProductionIn, self::PurchaseIn, self::TransferIn => 1,
            self::SaleOut, self::OrderOut, self::TransferOut, self::Damage => -1,
            self::Adjustment => null,
        };
    }
}
