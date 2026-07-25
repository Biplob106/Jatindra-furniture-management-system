<?php

namespace App\Support;

use RuntimeException;

/**
 * Raised when master data is deleted while operational rows still point at it.
 *
 * The message is Bengali and names what is in the way, because "cannot delete"
 * on its own leaves the person at the counter with nowhere to go.
 */
class ReferencedRecordException extends RuntimeException
{
    /**
     * @param  array<string, int>  $counts  Bengali label => number of referencing rows
     */
    public static function throwIfReferenced(string $subject, array $counts): void
    {
        $blocking = array_filter($counts);

        if ($blocking === []) {
            return;
        }

        $parts = [];

        foreach ($blocking as $label => $count) {
            $parts[] = static::bengaliDigits($count).' টি '.$label;
        }

        throw new static(
            "এই {$subject} মুছে ফেলা যাবে না, কারণ এর সাথে ".implode(', ', $parts).' যুক্ত আছে। বদলে নিষ্ক্রিয় করে দিন।'
        );
    }

    public static function bengaliDigits(int|string $value): string
    {
        return strtr((string) $value, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}
