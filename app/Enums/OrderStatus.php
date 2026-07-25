<?php

namespace App\Enums;

/**
 * Where an order stands on the shop floor.
 *
 * A draft is not yet an order: it carries no order_no, because a number burned
 * on an abandoned draft would leave a gap in a sequence that gets written on
 * paper slips, and a missing number reads as a lost order.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case InProduction = 'in_production';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'খসড়া',
            self::Confirmed => 'নিশ্চিত',
            self::InProduction => 'কাজ চলছে',
            self::Ready => 'তৈরি',
            self::Delivered => 'ডেলিভারি হয়েছে',
            self::Cancelled => 'বাতিল',
        };
    }

    /**
     * Which statuses an order may move to from here.
     *
     * Delivered and cancelled are the two ends of the line: an order that has
     * gone out of the door or been called off does not move again. Correcting
     * either is a deliberate act, not a status change.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::InProduction, self::Ready, self::Cancelled],
            self::InProduction => [self::Ready, self::Cancelled],
            self::Ready => [self::Delivered, self::InProduction],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** A number is issued the moment an order stops being a draft. */
    public function needsNumber(): bool
    {
        return $this !== self::Draft;
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Delivered, self::Cancelled], true);
    }
}
