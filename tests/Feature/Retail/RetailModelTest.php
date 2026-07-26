<?php

use App\Enums\CncJobStatus;
use App\Enums\CncMaterialBy;
use App\Enums\CncRateType;
use App\Enums\StockMovementType;
use App\Models\CncJob;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\MaterialMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\StockMovement;
use Illuminate\Database\QueryException;

it('casts money and stock on a product', function () {
    $product = Product::factory()->inStock('4.00')->create([
        'cost_price' => '18000.50',
        'sale_price' => '25000.00',
    ]);

    expect($product->cost_price)->toBe('18000.50')
        ->and($product->sale_price)->toBe('25000.00')
        ->and($product->current_stock)->toBe('4.00');
});

it('soft-deletes a product rather than losing its history', function () {
    $product = Product::factory()->create();
    StockMovement::factory()->create(['product_id' => $product->id]);

    $product->delete();

    expect(Product::count())->toBe(0)
        ->and(Product::withTrashed()->count())->toBe(1)
        ->and(StockMovement::count())->toBe(1);
});

it('refuses two products with the same code', function () {
    Product::factory()->create(['sku' => 'ALM-001']);

    expect(fn () => Product::factory()->create(['sku' => 'ALM-001']))
        ->toThrow(QueryException::class);
});

it('reaches its category and shop', function () {
    $category = ProductCategory::factory()->create(['name' => 'শোকেস']);
    $shop = Shop::factory()->create();

    $product = Product::factory()->create(['category_id' => $category->id, 'shop_id' => $shop->id]);

    expect($product->category->name)->toBe('শোকেস')
        ->and($product->shop->id)->toBe($shop->id);
});

it('lists only what is on the floor', function () {
    $available = Product::factory()->inStock('2.00')->create();
    Product::factory()->inStock('0.00')->create();

    expect(Product::inStock()->pluck('id')->all())->toBe([$available->id]);
});

it('flags a product at or below its reorder line', function () {
    $low = Product::factory()->inStock('1.00')->create(['min_stock' => '2.00']);
    $exactly = Product::factory()->inStock('2.00')->create(['min_stock' => '2.00']);
    Product::factory()->inStock('9.00')->create(['min_stock' => '2.00']);
    // No reorder line set, so it never alerts however empty it is.
    Product::factory()->inStock('0.00')->create(['min_stock' => '0.00']);

    expect(Product::lowStock()->pluck('id')->all())->toBe([$low->id, $exactly->id]);
});

/**
 * Raw timber and a finished almirah are counted in different units and move
 * for different reasons, so they keep separate logs.
 */
it('keeps a product movement out of the material log', function () {
    $movement = StockMovement::factory()->sold('2.00')->create();

    expect($movement->type)->toBe(StockMovementType::SaleOut)
        ->and($movement->quantity)->toBe('2.00')
        ->and(MaterialMovement::count())->toBe(0);
});

it('knows which way each movement type takes stock', function (string $type, ?int $sign) {
    expect(StockMovementType::from($type)->sign())->toBe($sign);
})->with([
    'production_in adds' => ['production_in', 1],
    'purchase_in adds' => ['purchase_in', 1],
    'transfer_in adds' => ['transfer_in', 1],
    'sale_out takes' => ['sale_out', -1],
    'order_out takes' => ['order_out', -1],
    'transfer_out takes' => ['transfer_out', -1],
    'damage takes' => ['damage', -1],
    'adjustment goes either way' => ['adjustment', null],
]);

it('casts money and dates on a sale', function () {
    $sale = Sale::factory()->withTotals('25000.00', '10000.00')->create();

    expect($sale->total_amount)->toBe('25000.00')
        ->and($sale->paid_amount)->toBe('10000.00')
        ->and($sale->due_amount)->toBe('15000.00')
        ->and($sale->sale_date->toDateString())->toBe('2026-07-20');
});

it('drops the lines with the invoice', function () {
    $sale = Sale::factory()->create();
    SaleItem::factory()->count(3)->create(['sale_id' => $sale->id]);

    $sale->delete();

    expect(SaleItem::count())->toBe(0);
});

it('lists only the invoices still owed', function () {
    $owed = Sale::factory()->withTotals('25000.00', '5000.00')->create();
    Sale::factory()->withTotals('9000.00', '9000.00')->create();

    expect(Sale::owing()->pluck('id')->all())->toBe([$owed->id]);
});

/**
 * Most counter sales are to somebody who will never come back and does not
 * want to be entered into a customer list to buy one chair.
 */
it('names a walk-in buyer from the invoice itself', function () {
    $sale = Sale::factory()->create(['customer_id' => null, 'customer_name' => 'রফিক সাহেব']);

    expect($sale->buyerName())->toBe('রফিক সাহেব')
        ->and($sale->customer)->toBeNull();
});

it('names a known buyer from their customer record', function () {
    $customer = Customer::factory()->create(['name' => 'করিম সাহেব']);
    $sale = Sale::factory()->create(['customer_id' => $customer->id, 'customer_name' => 'পুরোনো নাম']);

    expect($sale->buyerName())->toBe('করিম সাহেব');
});

it('falls back to a plain label when nobody was named at all', function () {
    $sale = Sale::factory()->create(['customer_id' => null, 'customer_name' => null]);

    expect($sale->buyerName())->toBe('নগদ ক্রেতা');
});

it('casts a cnc job', function () {
    $job = CncJob::factory()->create([
        'material_by' => CncMaterialBy::Shop,
        'rate_type' => CncRateType::PerHour,
        'machine_hours' => '3.50',
    ]);

    expect($job->material_by)->toBe(CncMaterialBy::Shop)
        ->and($job->rate_type)->toBe(CncRateType::PerHour)
        ->and($job->status)->toBe(CncJobStatus::Pending)
        ->and($job->machine_hours)->toBe('3.50')
        ->and($job->total_amount)->toBe('3000.00');
});

it('ties a cnc job to one of our own orders', function () {
    $order = Order::factory()->confirmed()->create();
    $operator = Employee::factory()->create();

    $job = CncJob::factory()->create(['order_id' => $order->id, 'operator_id' => $operator->id]);

    expect($job->order->id)->toBe($order->id)
        ->and($job->operator->id)->toBe($operator->id);
});

it('lists only the jobs still in our hands', function () {
    $pending = CncJob::factory()->create();
    $running = CncJob::factory()->status(CncJobStatus::Running)->create();
    CncJob::factory()->status(CncJobStatus::Delivered)->create();
    CncJob::factory()->status(CncJobStatus::Cancelled)->create();

    expect(CncJob::open()->pluck('id')->all())->toBe([$pending->id, $running->id]);
});

/**
 * The moves out of each status are fixed on the enum, so a button cannot offer
 * a transition the action would refuse.
 */
it('allows only the sensible next moves', function (string $from, array $expected) {
    $next = array_map(fn (CncJobStatus $status) => $status->value, CncJobStatus::from($from)->allowedNext());

    expect($next)->toBe($expected);
})->with([
    'pending' => ['pending', ['running', 'cancelled']],
    'running' => ['running', ['completed', 'cancelled']],
    'completed' => ['completed', ['delivered']],
    'delivered is an end' => ['delivered', []],
    'cancelled is an end' => ['cancelled', []],
]);
