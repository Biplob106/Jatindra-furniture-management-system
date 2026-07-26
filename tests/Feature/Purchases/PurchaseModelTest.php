<?php

use App\Enums\MaterialMovementType;
use App\Enums\PurchaseItemType;
use App\Enums\PurchasePaymentType;
use App\Enums\PurchaseStatus;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\Order;
use App\Models\PartyPayment;
use App\Models\PaymentAllocation;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;

it('casts money and dates on a purchase', function () {
    $purchase = Purchase::factory()->onCredit('12500.50', '2026-08-20')->create();

    expect($purchase->total_amount)->toBe('12500.50')
        ->and($purchase->due_amount)->toBe('12500.50')
        ->and($purchase->payment_type)->toBe(PurchasePaymentType::Credit)
        ->and($purchase->status)->toBe(PurchaseStatus::Pending)
        ->and($purchase->payment_due_date->toDateString())->toBe('2026-08-20');
});

it('carries quantities to three places', function () {
    $item = PurchaseItem::factory()->create(['quantity' => '12.125']);

    expect($item->quantity)->toBe('12.125')
        ->and($item->item_type)->toBe(PurchaseItemType::Material);
});

it('drops the lines with the challan', function () {
    $purchase = Purchase::factory()->create();
    PurchaseItem::factory()->count(3)->create(['purchase_id' => $purchase->id]);

    $purchase->delete();

    expect(PurchaseItem::count())->toBe(0);
});

it('lists only what is still owed', function () {
    $owed = Purchase::factory()->onCredit('12000.00')->create();
    Purchase::factory()->withTotals('5000.00', '5000.00')->create();

    expect(Purchase::owing()->pluck('id')->all())->toBe([$owed->id]);
});

/**
 * The aging list runs on this: what is past its credit terms, and by how long.
 */
it('lists what is past its credit terms', function () {
    $late = Purchase::factory()->onCredit('12000.00', '2026-07-10')->create();
    Purchase::factory()->onCredit('8000.00', '2026-08-30')->create();
    // Paid, so late or not it is nobody's problem.
    Purchase::factory()->withTotals('4000.00', '4000.00')->create(['payment_due_date' => '2026-07-01']);
    // No terms agreed, so it cannot be overdue.
    Purchase::factory()->onCredit('3000.00')->create();

    expect(Purchase::query()->overdue('2026-07-26')->pluck('id')->all())->toBe([$late->id]);
});

it('reaches its supplier and its lines', function () {
    $supplier = Supplier::factory()->create(['name' => 'করিম টিম্বার']);
    $purchase = Purchase::factory()->create(['supplier_id' => $supplier->id]);
    PurchaseItem::factory()->count(2)->create(['purchase_id' => $purchase->id]);

    expect($purchase->supplier->name)->toBe('করিম টিম্বার')
        ->and($purchase->items)->toHaveCount(2);
});

it('reaches the payments allocated against it', function () {
    $purchase = Purchase::factory()->onCredit('12000.00')->create();
    $payment = PartyPayment::factory()->create(['amount' => '5000.00']);

    PaymentAllocation::factory()->create([
        'party_payment_id' => $payment->id,
        'allocatable_type' => Purchase::class,
        'allocatable_id' => $purchase->id,
        'allocated_amount' => '5000.00',
    ]);

    expect($purchase->allocations)->toHaveCount(1)
        ->and($purchase->allocations->first()->allocated_amount)->toBe('5000.00')
        ->and($purchase->allocations->first()->payment->amount)->toBe('5000.00');
});

it('drops the allocations with the payment', function () {
    $payment = PartyPayment::factory()->create();
    PaymentAllocation::factory()->count(2)->create(['party_payment_id' => $payment->id]);

    $payment->delete();

    expect(PaymentAllocation::count())->toBe(0);
});

it('soft-deletes a supplier rather than losing their history', function () {
    $supplier = Supplier::factory()->create();
    Purchase::factory()->create(['supplier_id' => $supplier->id]);

    $supplier->delete();

    expect(Supplier::count())->toBe(0)
        ->and(Supplier::withTrashed()->count())->toBe(1)
        ->and(Purchase::count())->toBe(1);
});

it('ties a material movement to the job that consumed it', function () {
    $order = Order::factory()->create();
    $material = Material::factory()->create();

    $movement = MaterialMovement::factory()->consumed('4.500')->create([
        'material_id' => $material->id,
        'order_id' => $order->id,
    ]);

    expect($movement->quantity)->toBe('4.500')
        ->and($movement->type)->toBe(MaterialMovementType::Out)
        ->and($movement->order->id)->toBe($order->id)
        ->and($movement->material->id)->toBe($material->id);
});

it('flags material at or below its reorder line', function () {
    $low = Material::factory()->inStock('4.000')->create(['min_stock' => '5.000']);
    $exactly = Material::factory()->inStock('5.000')->create(['min_stock' => '5.000']);
    Material::factory()->inStock('50.000')->create(['min_stock' => '5.000']);
    // No reorder line set, so it never alerts.
    Material::factory()->inStock('0.000')->create(['min_stock' => '0.000']);

    expect(Material::lowStock()->pluck('id')->all())->toBe([$low->id, $exactly->id]);
});
