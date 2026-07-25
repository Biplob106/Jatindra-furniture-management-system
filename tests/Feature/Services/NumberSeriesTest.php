<?php

use App\Models\Customer;
use App\Models\Shop;
use App\Services\NumberSeries;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->series = app(NumberSeries::class);
});

it('issues the documented shape', function () {
    expect($this->series->issue(NumberSeries::ORDER, '2026-07-20'))->toBe('SH-2607-0001');
});

it('counts up within a month', function () {
    $issued = collect(range(1, 3))->map(fn () => $this->series->issue(NumberSeries::ORDER, '2026-07-20'))->all();

    expect($issued)->toBe(['SH-2607-0001', 'SH-2607-0002', 'SH-2607-0003']);
});

it('starts each month from one', function () {
    $this->series->issue(NumberSeries::ORDER, '2026-07-31');
    $this->series->issue(NumberSeries::ORDER, '2026-07-31');

    expect($this->series->issue(NumberSeries::ORDER, '2026-08-01'))->toBe('SH-2608-0001');
});

it('keeps a counter per prefix', function () {
    $this->series->issue(NumberSeries::ORDER, '2026-07-20');
    $this->series->issue(NumberSeries::ORDER, '2026-07-20');

    expect($this->series->issue(NumberSeries::SALE, '2026-07-20'))->toBe('INV-2607-0001')
        ->and($this->series->issue(NumberSeries::PURCHASE, '2026-07-20'))->toBe('PO-2607-0001')
        ->and($this->series->issue(NumberSeries::CNC_JOB, '2026-07-20'))->toBe('CNC-2607-0001')
        ->and($this->series->issue(NumberSeries::ORDER, '2026-07-20'))->toBe('SH-2607-0003');
});

it('pads to four digits and grows past them', function () {
    DB::table('number_series')->insert([
        'prefix' => 'SH', 'period' => '2607', 'last_number' => 9999,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Four digits is the documented shape, not a ceiling. A shop doing more
    // than 9999 orders in a month gets a longer number, not a wrapped one.
    expect($this->series->issue(NumberSeries::ORDER, '2026-07-20'))->toBe('SH-2607-10000');
});

it('refuses a prefix it does not know', function () {
    expect(fn () => $this->series->issue('XYZ'))->toThrow(InvalidArgumentException::class);

    expect(DB::table('number_series')->count())->toBe(0);
});

it('creates exactly one counter row per prefix and month', function () {
    foreach (range(1, 5) as $i) {
        $this->series->issue(NumberSeries::ORDER, '2026-07-20');
    }

    expect(DB::table('number_series')->count())->toBe(1)
        ->and(DB::table('number_series')->value('last_number'))->toBe(5);
});

it('peeks without taking the number', function () {
    expect($this->series->peek(NumberSeries::ORDER, '2026-07-20'))->toBe('SH-2607-0001')
        ->and($this->series->peek(NumberSeries::ORDER, '2026-07-20'))->toBe('SH-2607-0001')
        ->and(DB::table('number_series')->count())->toBe(0);

    $this->series->issue(NumberSeries::ORDER, '2026-07-20');

    expect($this->series->peek(NumberSeries::ORDER, '2026-07-20'))->toBe('SH-2607-0002');
});

it('issues two hundred distinct numbers in a row', function () {
    $issued = collect(range(1, 200))
        ->map(fn () => $this->series->issue(NumberSeries::ORDER, '2026-07-20'))
        ->all();

    expect(array_unique($issued))->toHaveCount(200)
        ->and(end($issued))->toBe('SH-2607-0200');
});

/**
 * Defence in depth behind the lock.
 *
 * Serialising the increment is what stops two clerks being handed the same
 * number, but the guarantee should not rest on that alone. The counter is
 * unique on (prefix, period), so a second row for a month cannot exist, and
 * orders.order_no is unique, so a duplicate number cannot be stored even if
 * issuing were somehow wrong.
 *
 * True simultaneous load is not exercised here: the suite runs in one process
 * inside a transaction, so a second connection could not see these rows. The
 * concurrency claim rests on lockForUpdate plus these two constraints.
 */
it('cannot hold two counter rows for the same prefix and month', function () {
    $this->series->issue(NumberSeries::ORDER, '2026-07-20');

    expect(fn () => DB::table('number_series')->insert([
        'prefix' => 'SH', 'period' => '2607', 'last_number' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(DB::table('number_series')->count())->toBe(1);
});

it('cannot store the same order number twice', function () {
    $customer = Customer::factory()->create();
    $shop = Shop::factory()->create();

    $row = [
        'order_no' => 'SH-2607-0001',
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'order_date' => '2026-07-20',
        'status' => 'confirmed',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('orders')->insert($row);

    expect(fn () => DB::table('orders')->insert($row))
        ->toThrow(QueryException::class);

    expect(DB::table('orders')->count())->toBe(1);
});
