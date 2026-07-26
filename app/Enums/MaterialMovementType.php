<?php

namespace App\Enums;

/**
 * Which way stock moved.
 *
 * `in` is a purchase arriving, `out` is an order consuming it, `wastage` is
 * the offcut that never becomes furniture.
 *
 * `return` is unused material coming back from a job into the store, so it
 * raises stock. docs/schema.md does not say which way it points; sending goods
 * back to a supplier is the rarer case and is recorded as an `out` against the
 * purchase, which keeps `return` meaning one thing only.
 */
enum MaterialMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Wastage = 'wastage';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::In => 'জমা',
            self::Out => 'ব্যবহার',
            self::Wastage => 'নষ্ট',
            self::Return => 'ফেরত',
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
            self::In, self::Return => 1,
            self::Out, self::Wastage => -1,
            self::Adjustment => null,
        };
    }
}
