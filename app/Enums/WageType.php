<?php

namespace App\Enums;

/**
 * How an employee earns. Drives which ledger entries the wage automation
 * writes, so changing a value here changes money.
 */
enum WageType: string
{
    /** Present writes a full daily_rate credit, half_day writes half. */
    case Daily = 'daily';

    /** Attendance is still recorded, but the credit lands once, month end. */
    case Monthly = 'monthly';

    /** Earnings come from order_item_works reaching done. */
    case Piece = 'piece';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'দৈনিক',
            self::Monthly => 'মাসিক',
            self::Piece => 'কাজ চুক্তি',
        };
    }
}
