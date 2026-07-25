<?php

namespace App\Enums;

/**
 * A piece of work handed to a worker on one order item.
 *
 * `done` is the status that pays: an order_item_work reaching it with an
 * agreed_amount writes a piece_earned credit. `rejected` pays nothing, which is
 * the point of having it.
 */
enum OrderItemWorkStatus: string
{
    case Assigned = 'assigned';
    case Working = 'working';
    case Done = 'done';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'দেওয়া হয়েছে',
            self::Working => 'কাজ চলছে',
            self::Done => 'শেষ',
            self::Rejected => 'বাতিল',
        };
    }

    public function earnsPieceRate(): bool
    {
        return $this === self::Done;
    }
}
