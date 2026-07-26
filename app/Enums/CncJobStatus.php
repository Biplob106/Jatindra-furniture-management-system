<?php

namespace App\Enums;

/**
 * Where a CNC job stands.
 *
 * The moves allowed out of each are fixed here rather than left to the screen,
 * the same way OrderStatus does it, so a button cannot offer a transition the
 * action would refuse.
 */
enum CncJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'অপেক্ষায়',
            self::Running => 'মেশিনে চলছে',
            self::Completed => 'কাজ শেষ',
            self::Delivered => 'ডেলিভারি হয়েছে',
            self::Cancelled => 'বাতিল',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Running, self::Cancelled],
            self::Running => [self::Completed, self::Cancelled],
            self::Completed => [self::Delivered],
            // Both are ends. A delivered job that comes back is a new job.
            self::Delivered, self::Cancelled => [],
        };
    }

    /** Still in the shop's hands. */
    public function isOpen(): bool
    {
        return $this !== self::Delivered && $this !== self::Cancelled;
    }
}
