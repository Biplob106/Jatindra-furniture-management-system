<?php

namespace App\Enums;

/**
 * How a CNC job is priced. total = quantity * rate for the first three;
 * `fixed` is a price agreed for the whole job, where quantity is 1.
 */
enum CncRateType: string
{
    case PerSqft = 'per_sqft';
    case PerPiece = 'per_piece';
    case PerHour = 'per_hour';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::PerSqft => 'প্রতি বর্গফুট',
            self::PerPiece => 'প্রতি পিস',
            self::PerHour => 'প্রতি ঘণ্টা',
            self::Fixed => 'একদর',
        };
    }
}
