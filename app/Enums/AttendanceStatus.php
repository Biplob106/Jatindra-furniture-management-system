<?php

namespace App\Enums;

/**
 * What a worker did on a given day.
 *
 * Only present and half_day earn a daily wage. The other three record that the
 * day was accounted for and write no ledger row at all.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case HalfDay = 'half_day';
    case Leave = 'leave';
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'উপস্থিত',
            self::Absent => 'অনুপস্থিত',
            self::HalfDay => 'অর্ধদিবস',
            self::Leave => 'ছুটি',
            self::Holiday => 'বন্ধের দিন',
        };
    }

    /**
     * The share of a day's wage this status earns. Anything not listed earns
     * nothing, which is why absent, leave and holiday write no ledger row.
     */
    public function wageFraction(): string
    {
        return match ($this) {
            self::Present => '1',
            self::HalfDay => '0.5',
            default => '0',
        };
    }

    public function earnsWage(): bool
    {
        return $this->wageFraction() !== '0';
    }
}
