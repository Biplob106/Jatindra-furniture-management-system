<?php

namespace App\Enums;

enum CustomerType: string
{
    case Retail = 'retail';
    case Dealer = 'dealer';
    case Contractor = 'contractor';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'খুচরা',
            self::Dealer => 'ডিলার',
            self::Contractor => 'ঠিকাদার',
        };
    }
}
