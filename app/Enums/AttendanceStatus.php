<?php

namespace App\Enums;

/**
 * What a worker did on a given day.
 *
 * The day-wage share lives in wageFraction(); the night allowance is a separate
 * axis in hasNight(), so "full day + night" earns a full day wage AND a night
 * allowance as two distinct ledger rows. Statuses that earn neither (absent,
 * leave, holiday) write no ledger row at all.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case HalfDay = 'half_day';
    case Leave = 'leave';
    case Holiday = 'holiday';
    case NightOnly = 'night_only';
    case FullDayNight = 'full_day_night';
    case HalfDayNight = 'half_day_night';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'উপস্থিত',
            self::Absent => 'অনুপস্থিত',
            self::HalfDay => 'অর্ধদিবস',
            self::Leave => 'ছুটি',
            self::Holiday => 'বন্ধের দিন',
            self::NightOnly => 'শুধু রাত',
            self::FullDayNight => 'পূর্ণ দিন + রাত',
            self::HalfDayNight => 'অর্ধদিবস + রাত',
        };
    }

    /**
     * The share of a day's wage this status earns. Night-only earns nothing for
     * the day — its pay is the night allowance alone. Anything not listed earns
     * nothing, which is why absent, leave and holiday write no ledger row.
     */
    public function wageFraction(): string
    {
        return match ($this) {
            self::Present, self::FullDayNight => '1',
            self::HalfDay, self::HalfDayNight => '0.5',
            default => '0',
        };
    }

    public function earnsWage(): bool
    {
        return $this->wageFraction() !== '0';
    }

    /**
     * Whether a night allowance is due for this status. The amount is resolved
     * elsewhere (employee override or a fraction of the daily wage); here we
     * only say that a night was worked.
     */
    public function hasNight(): bool
    {
        return match ($this) {
            self::NightOnly, self::FullDayNight, self::HalfDayNight => true,
            default => false,
        };
    }
}
