<?php

namespace App\Enums;

/**
 * What a challan line points at: a raw material we consume, or a readymade
 * product we resell. Decides which table purchase_items.item_id refers to.
 */
enum PurchaseItemType: string
{
    case Material = 'material';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'কাঁচামাল',
            self::Product => 'পণ্য',
        };
    }
}
