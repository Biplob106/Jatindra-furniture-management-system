<?php

namespace App\Enums;

/**
 * What a supplier sells us. Transport is a supplier too: the truck that brings
 * the timber is bought on credit the same way the timber is.
 */
enum SupplierType: string
{
    case Wood = 'wood';
    case Hardware = 'hardware';
    case Paint = 'paint';
    case Transport = 'transport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Wood => 'কাঠ',
            self::Hardware => 'হার্ডওয়্যার',
            self::Paint => 'রং',
            self::Transport => 'পরিবহন',
            self::Other => 'অন্যান্য',
        };
    }
}
