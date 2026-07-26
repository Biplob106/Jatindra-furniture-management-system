<?php

use App\Actions\Materials\AdjustStock;
use App\Actions\Materials\IssueMaterial;
use App\Enums\MaterialMovementType;
use App\Enums\MaterialUnit;
use App\Models\EmployeeLedger;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\Order;
use App\Models\SupplierLedger;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->issue = app(IssueMaterial::class);
    $this->adjust = app(AdjustStock::class);

    $this->material = Material::factory()
        ->inStock('100.000', '1200.00')
        ->create(['unit' => MaterialUnit::Cft]);
});

/**
 * Section 9: a material issue writes material_movements and nothing else. The
 * money left when the stock was bought; handing timber to a carpenter moves
 * none at all.
 */
it('writes one movement and touches no money', function () {
    $this->issue->handle($this->material, '10', '2026-07-20');

    expect(MaterialMovement::count())->toBe(1)
        ->and(Transaction::count())->toBe(0)
        ->and(SupplierLedger::count())->toBe(0)
        ->and(EmployeeLedger::count())->toBe(0);
});

it('takes the stock out of the store', function () {
    $movement = $this->issue->handle($this->material, '12.500', '2026-07-20');

    expect($movement->type)->toBe(MaterialMovementType::Out)
        ->and($movement->quantity)->toBe('12.500')
        ->and($this->material->refresh()->current_stock)->toBe('87.500');
});

/**
 * order_id is what makes per-order material cost answerable later.
 */
it('ties the issue to the job that consumed it', function () {
    $order = Order::factory()->create();

    $movement = $this->issue->handle($this->material, '10', '2026-07-20', order: $order);

    expect($movement->order_id)->toBe($order->id)
        ->and($movement->reference_type)->toBe(Order::class)
        ->and($movement->reference_id)->toBe($order->id);
});

it('issues to general use with no job behind it', function () {
    $movement = $this->issue->handle($this->material, '10', '2026-07-20');

    expect($movement->order_id)->toBeNull()
        ->and($movement->reference_type)->toBeNull();
});

/**
 * A job that consumed timber from three deliveries cannot be told which planks
 * came from which, so the average is the only honest answer.
 */
it('costs the issue at the average, not the last price paid', function () {
    $movement = $this->issue->handle($this->material, '10', '2026-07-20');

    expect($movement->unit_cost)->toBe('1200.00')
        // An issue does not change what the stock was bought at.
        ->and($this->material->refresh()->avg_cost)->toBe('1200.00');
});

it('writes off an offcut as wastage', function () {
    $movement = $this->issue->handle($this->material, '2.250', '2026-07-20', MaterialMovementType::Wastage);

    expect($movement->type)->toBe(MaterialMovementType::Wastage)
        ->and($this->material->refresh()->current_stock)->toBe('97.750');
});

it('puts unused material back on the shelf', function () {
    $this->issue->handle($this->material, '10', '2026-07-20');
    $this->issue->handle($this->material, '4', '2026-07-21', MaterialMovementType::Return);

    expect($this->material->refresh()->current_stock)->toBe('94.000')
        ->and(MaterialMovement::count())->toBe(2);
});

/**
 * A store room cannot hand out what it does not have. Same rule CashService
 * applies to a cash box.
 */
it('refuses to issue more than is on the floor', function () {
    expect(fn () => $this->issue->handle($this->material, '150', '2026-07-20'))
        ->toThrow(RuntimeException::class);

    expect(MaterialMovement::count())->toBe(0)
        ->and($this->material->refresh()->current_stock)->toBe('100.000');
});

it('allows issuing every last bit of it', function () {
    $this->issue->handle($this->material, '100', '2026-07-20');

    expect($this->material->refresh()->current_stock)->toBe('0.000');
});

it('refuses a quantity that moves nothing', function (string $quantity) {
    expect(fn () => $this->issue->handle($this->material, $quantity, '2026-07-20'))
        ->toThrow(InvalidArgumentException::class);

    expect(MaterialMovement::count())->toBe(0);
})->with(['0', '-5']);

it('refuses a quantity that is not a plain figure', function () {
    expect(fn () => $this->issue->handle($this->material, '1,000', '2026-07-20'))
        ->toThrow(InvalidArgumentException::class);

    expect(MaterialMovement::count())->toBe(0);
});

/**
 * An adjustment goes either way, so it cannot come from a type alone.
 */
it('refuses to record an adjustment as an issue', function () {
    expect(fn () => $this->issue->handle($this->material, '10', '2026-07-20', MaterialMovementType::Adjustment))
        ->toThrow(InvalidArgumentException::class);

    expect(MaterialMovement::count())->toBe(0);
});

it('holds quantities to three places', function () {
    $this->issue->handle($this->material, '0.125', '2026-07-20');
    $this->issue->handle($this->material, '0.376', '2026-07-20');

    expect($this->material->refresh()->current_stock)->toBe('99.499');
});

it('records who issued it', function () {
    $user = User::factory()->create();

    $movement = $this->issue->handle($this->material, '10', '2026-07-20', note: 'আলমারির জন্য', userId: $user->id);

    expect($movement->created_by)->toBe($user->id)
        ->and($movement->note)->toBe('আলমারির জন্য');
});

it('keeps two materials apart', function () {
    $other = Material::factory()->inStock('50.000')->create();

    $this->issue->handle($this->material, '10', '2026-07-20');

    expect($this->material->refresh()->current_stock)->toBe('90.000')
        ->and($other->refresh()->current_stock)->toBe('50.000');
});

it('corrects the books up to what was counted', function () {
    $movement = $this->adjust->handle($this->material, '104.500', '2026-07-20');

    expect($movement->type)->toBe(MaterialMovementType::Adjustment)
        ->and($movement->quantity)->toBe('4.500')
        ->and($movement->note)->toContain('বেশি পাওয়া গেছে')
        ->and($this->material->refresh()->current_stock)->toBe('104.500');
});

it('corrects the books down to what was counted', function () {
    $movement = $this->adjust->handle($this->material, '92.000', '2026-07-20');

    expect($movement->quantity)->toBe('8.000')
        ->and($movement->note)->toContain('কম পাওয়া গেছে')
        ->and($this->material->refresh()->current_stock)->toBe('92.000');
});

/**
 * Re-counting the same shelf twice must not leave two rows saying nothing
 * happened.
 */
it('writes nothing when the count agrees with the books', function () {
    $first = $this->adjust->handle($this->material, '100', '2026-07-20');
    $second = $this->adjust->handle($this->material, '100.000', '2026-07-21');

    expect($first)->toBeNull()
        ->and($second)->toBeNull()
        ->and(MaterialMovement::count())->toBe(0)
        ->and($this->material->refresh()->current_stock)->toBe('100.000');
});

it('settles at the counted figure however many times it is counted', function () {
    $this->adjust->handle($this->material, '95', '2026-07-20');
    $this->adjust->handle($this->material, '95', '2026-07-21');

    expect(MaterialMovement::count())->toBe(1)
        ->and($this->material->refresh()->current_stock)->toBe('95.000');
});

it('refuses a count that is not a plain figure', function (string $counted) {
    expect(fn () => $this->adjust->handle($this->material, $counted, '2026-07-20'))
        ->toThrow(InvalidArgumentException::class);

    expect(MaterialMovement::count())->toBe(0);
})->with(['-5', '1,000']);

it('keeps the reason the counter gave', function () {
    $movement = $this->adjust->handle($this->material, '90', '2026-07-20', note: 'পানিতে নষ্ট');

    expect($movement->note)->toContain('পানিতে নষ্ট');
});

it('moves no money on a recount either', function () {
    $this->adjust->handle($this->material, '90', '2026-07-20');

    expect(Transaction::count())->toBe(0)
        ->and(SupplierLedger::count())->toBe(0);
});
