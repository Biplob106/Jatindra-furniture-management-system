<?php

namespace App\Enums;

enum MaterialCategory: string
{
    case Wood = 'wood';
    case Board = 'board';
    case Hardware = 'hardware';
    case Paint = 'paint';
    case Polish = 'polish';
    case Glue = 'glue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Wood => 'কাঠ',
            self::Board => 'বোর্ড',
            self::Hardware => 'হার্ডওয়্যার',
            self::Paint => 'রং',
            self::Polish => 'পলিশ',
            self::Glue => 'আঠা',
            self::Other => 'অন্যান্য',
        };
    }
}
