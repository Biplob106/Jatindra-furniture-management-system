<?php

namespace App\Enums;

/**
 * Furniture is measured in inches on this shop floor, occasionally in feet.
 * Centimetres are there for the rare drawing that arrives in metric.
 */
enum DimensionUnit: string
{
    case Inch = 'inch';
    case Feet = 'feet';
    case Cm = 'cm';

    public function label(): string
    {
        return match ($this) {
            self::Inch => 'ইঞ্চি',
            self::Feet => 'ফুট',
            self::Cm => 'সেন্টিমিটার',
        };
    }
}
