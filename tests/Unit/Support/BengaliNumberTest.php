<?php

use App\Support\BengaliNumber;

/**
 * Mirrors resources/js/lib/number-format.ts, so a figure in a server message
 * reads the same as it does on the page.
 */
it('formats taka grouped, without .00 on whole amounts', function (string|int $value, string $expected) {
    expect(BengaliNumber::money($value))->toBe($expected);
})->with([
    ['57000.00', '৫৭,০০০'],
    ['1250000.00', '১২,৫০,০০০'],
    ['2500.50', '২,৫০০.৫০'],
    ['2500.05', '২,৫০০.০৫'],
    ['7501.5', '৭,৫০১.৫০'],
    ['0.00', '০'],
    ['999', '৯৯৯'],
    ['-4500.00', '-৪,৫০০'],
    [45000, '৪৫,০০০'],
]);

it('formats quantities without trailing zeros', function (string $value, string $expected) {
    expect(BengaliNumber::number($value))->toBe($expected);
})->with([
    ['1.00', '১'],
    ['12.500', '১২.৫'],
    ['1500.000', '১,৫০০'],
]);
