<?php

namespace App\Support;

/**
 * Figures for Bengali messages, written the way the pages write them.
 *
 * Mirrors resources/js/lib/number-format.ts: Bengali digits, Bangladeshi
 * grouping (১২,৫০,০০০), no ".০০" on whole taka. Works on the decimal string
 * digit by digit, so no money passes through a float.
 */
final class BengaliNumber
{
    /** Taka: ৫৭,০০০ or ২,৫০০.৫০. */
    public static function money(int|string $value): string
    {
        [$sign, $whole, $fraction] = self::parts($value);

        $paisa = trim($fraction, '0') === ''
            ? ''
            : '.'.substr(str_pad($fraction, 2, '0'), 0, 2);

        return self::digits($sign.self::group($whole).$paisa);
    }

    /** Quantities: ১ rather than ১.০০, ১২.৫ rather than ১২.৫০০. */
    public static function number(int|string $value): string
    {
        [$sign, $whole, $fraction] = self::parts($value);

        $fraction = rtrim($fraction, '0');

        return self::digits($sign.self::group($whole).($fraction === '' ? '' : '.'.$fraction));
    }

    /**
     * @return array{string, string, string}
     */
    private static function parts(int|string $value): array
    {
        $text = trim((string) $value);
        $sign = str_starts_with($text, '-') ? '-' : '';
        [$whole, $fraction] = array_pad(explode('.', ltrim($text, '+-'), 2), 2, '');

        return [$sign, $whole === '' ? '0' : $whole, $fraction];
    }

    /** Last three digits, then pairs: 1250000 → 12,50,000. */
    private static function group(string $whole): string
    {
        if (strlen($whole) <= 3) {
            return $whole;
        }

        $head = substr($whole, 0, -3);
        $pairs = ltrim(strrev(implode(',', str_split(strrev($head), 2))), ',');

        return $pairs.','.substr($whole, -3);
    }

    private static function digits(string $value): string
    {
        return strtr($value, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}
