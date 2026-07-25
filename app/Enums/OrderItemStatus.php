<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'বাকি',
            self::InProgress => 'কাজ চলছে',
            self::Completed => 'শেষ',
        };
    }
}
