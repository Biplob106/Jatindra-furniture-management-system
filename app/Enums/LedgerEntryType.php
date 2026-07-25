<?php

namespace App\Enums;

/**
 * Why an employee_ledger row exists.
 *
 * Each type has one natural direction and it is fixed here rather than passed
 * in at every call site, because a sign error in this table is money quietly
 * moving the wrong way. `opening` and `adjustment` are the two that genuinely
 * go either way and must be told which.
 */
enum LedgerEntryType: string
{
    // Earned by the worker, so the shop owes more.
    case WageEarned = 'wage_earned';
    case PieceEarned = 'piece_earned';
    case Overtime = 'overtime';
    case Bonus = 'bonus';

    // Handed to the worker, so the shop owes less.
    case Advance = 'advance';
    case Tiffin = 'tiffin';
    case Payout = 'payout';
    case Fine = 'fine';

    // Either direction, caller decides.
    case Opening = 'opening';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'প্রারম্ভিক',
            self::WageEarned => 'হাজিরা',
            self::PieceEarned => 'কাজের মজুরি',
            self::Overtime => 'ওভারটাইম',
            self::Bonus => 'বোনাস',
            self::Advance => 'অগ্রিম',
            self::Tiffin => 'টিফিন',
            self::Payout => 'পরিশোধ',
            self::Fine => 'জরিমানা',
            self::Adjustment => 'সংশোধন',
        };
    }

    /**
     * The direction this type always moves, or null when the caller must say.
     */
    public function direction(): ?LedgerDirection
    {
        return match ($this) {
            self::WageEarned, self::PieceEarned, self::Overtime, self::Bonus => LedgerDirection::Credit,
            self::Advance, self::Tiffin, self::Payout, self::Fine => LedgerDirection::Debit,
            self::Opening, self::Adjustment => null,
        };
    }
}
