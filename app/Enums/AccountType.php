<?php

namespace App\Enums;

/**
 * Where money physically sits. Every transactions row points at one of these.
 */
enum AccountType: string
{
    case Cash = 'cash';
    case MobileBanking = 'mobile_banking';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'ক্যাশ',
            self::MobileBanking => 'মোবাইল ব্যাংকিং',
            self::Bank => 'ব্যাংক',
        };
    }
}
