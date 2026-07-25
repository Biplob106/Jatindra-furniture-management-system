<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'ক্যাশ',
            self::Bkash => 'বিকাশ',
            self::Nagad => 'নগদ',
            self::Bank => 'ব্যাংক',
        };
    }
}
