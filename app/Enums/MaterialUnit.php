<?php

namespace App\Enums;

/**
 * How a material is measured. Timber is bought in cft, board in sqft, and
 * quantities are DECIMAL(12,3) because a third of a cft is a real amount of
 * wood.
 */
enum MaterialUnit: string
{
    case Cft = 'cft';
    case Sqft = 'sqft';
    case Piece = 'piece';
    case Kg = 'kg';
    case Litre = 'litre';
    case Bundle = 'bundle';
    case Set = 'set';

    public function label(): string
    {
        return match ($this) {
            self::Cft => 'ঘনফুট',
            self::Sqft => 'বর্গফুট',
            self::Piece => 'পিস',
            self::Kg => 'কেজি',
            self::Litre => 'লিটার',
            self::Bundle => 'বান্ডিল',
            self::Set => 'সেট',
        };
    }
}
