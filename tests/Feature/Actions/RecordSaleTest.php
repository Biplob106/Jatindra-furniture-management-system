<?php

use App\Actions\Sales\RecordSale;
use App\Enums\AccountType;
use App\Enums\CashPaymentMethod;
use App\Enums\StockMovementType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\SupplierLedger;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->action = app(RecordSale::class);

    $this->shop = Shop::factory()->create();
    $this->customer = Customer::factory()->create(['name' => 'করিম সাহেব']);

    $this->almirah = Product::factory()->inStock('5.00', '18000.00')->create([
        'name' => 'সেগুন আলমারি',
        'sale_price' => '25000.00',
    ]);

    $this->drawer = Account::factory()->create([
        'type' => AccountType::Cash,
        'shop_id' => $this->shop->id,
        'opening_balance' => 50000,
        'current_balance' => 50000,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @param  list<array<string, mixed>>|null  $items
 */
function sell(array $overrides = [], ?array $items = null, bool $withCustomer = false, bool $withAccount = true): Sale
{
    return test()->action->handle(
        data: array_merge([
            'sale_date' => '2026-07-20',
            'shop_id' => test()->shop->id,
        ], $overrides),
        items: $items ?? [['product_id' => test()->almirah->id, 'quantity' => '1']],
        customer: $withCustomer ? test()->customer : null,
        account: $withAccount ? test()->drawer : null,
    );
}

it('writes the invoice, its lines, the stock leaving and the money in', function () {
    $sale = sell();

    expect(Sale::count())->toBe(1)
        ->and(SaleItem::count())->toBe(1)
        ->and(StockMovement::count())->toBe(1)
        ->and(Transaction::count())->toBe(1)
        ->and($sale->total_amount)->toBe('25000.00')
        ->and($sale->paid_amount)->toBe('25000.00')
        ->and($sale->due_amount)->toBe('0.00');
});

/**
 * The retail twin of the credit purchase. The almirah left the floor and the
 * customer owes for it, but no note came into the drawer.
 */
it('writes no transactions row for a credit sale', function () {
    $sale = sell(['paid_amount' => '0'], withCustomer: true, withAccount: false);

    expect(Transaction::count())->toBe(0)
        ->and($sale->due_amount)->toBe('25000.00')
        ->and(StockMovement::count())->toBe(1)
        ->and($this->drawer->refresh()->current_balance)->toBe('50000.00');
});

/**
 * There is no customer ledger table: what a customer owes lives on the sale's
 * own due_amount.
 */
it('leaves the supplier ledger alone entirely', function () {
    sell();

    expect(SupplierLedger::count())->toBe(0);
});

it('takes the stock off the floor', function () {
    sell([], [['product_id' => $this->almirah->id, 'quantity' => '2']]);

    $movement = StockMovement::sole();

    expect($movement->type)->toBe(StockMovementType::SaleOut)
        ->and($movement->quantity)->toBe('2.00')
        // Costed at what it cost to make, not what it sold for.
        ->and($movement->unit_cost)->toBe('18000.00')
        ->and($this->almirah->refresh()->current_stock)->toBe('3.00');
});

it('refuses to sell more than is on the floor', function () {
    expect(fn () => sell([], [['product_id' => $this->almirah->id, 'quantity' => '9']]))
        ->toThrow(RuntimeException::class);

    expect(Sale::count())->toBe(0)
        ->and(SaleItem::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0)
        ->and(Transaction::count())->toBe(0)
        ->and($this->almirah->refresh()->current_stock)->toBe('5.00');
});

it('allows selling the last one', function () {
    sell([], [['product_id' => $this->almirah->id, 'quantity' => '5']]);

    expect($this->almirah->refresh()->current_stock)->toBe('0.00');
});

it('takes the price off the product when the counter does not say', function () {
    $sale = sell();

    expect(SaleItem::sole()->unit_price)->toBe('25000.00')
        ->and($sale->subtotal)->toBe('25000.00');
});

it('lets the counter agree a different price', function () {
    $sale = sell([], [['product_id' => $this->almirah->id, 'quantity' => '1', 'unit_price' => '23000']]);

    expect(SaleItem::sole()->unit_price)->toBe('23000.00')
        ->and($sale->total_amount)->toBe('23000.00');
});

it('adds delivery and takes off the discount', function () {
    $sale = sell(['delivery_charge' => '1500', 'discount' => '500']);

    expect($sale->subtotal)->toBe('25000.00')
        ->and($sale->delivery_charge)->toBe('1500.00')
        ->and($sale->discount)->toBe('500.00')
        ->and($sale->total_amount)->toBe('26000.00')
        ->and(Transaction::sole()->amount)->toBe('26000.00');
});

it('refuses a discount larger than the invoice', function () {
    expect(fn () => sell(['discount' => '99000']))->toThrow(InvalidArgumentException::class);

    expect(Sale::count())->toBe(0);
});

it('sums several lines to the paisa', function () {
    $chair = Product::factory()->inStock('10.00', '1200.00')->create(['sale_price' => '1234.57']);

    $sale = sell([], [
        ['product_id' => $this->almirah->id, 'quantity' => '1'],
        ['product_id' => $chair->id, 'quantity' => '3'],
    ]);

    // 25000 + 3 * 1234.57
    expect($sale->subtotal)->toBe('28703.71')
        ->and(SaleItem::count())->toBe(2)
        ->and(StockMovement::count())->toBe(2);
});

it('splits a part payment between the drawer and the due', function () {
    $sale = sell(['paid_amount' => '10000'], withCustomer: true);

    expect($sale->paid_amount)->toBe('10000.00')
        ->and($sale->due_amount)->toBe('15000.00')
        ->and(Transaction::sole()->amount)->toBe('10000.00')
        ->and($this->drawer->refresh()->current_balance)->toBe('60000.00');
});

/**
 * A walk-in can pay cash and leave. A walk-in cannot owe: there would be
 * nobody to ask for it.
 */
it('refuses to leave a walk-in owing', function () {
    expect(fn () => sell(['paid_amount' => '5000'], withAccount: true))
        ->toThrow(InvalidArgumentException::class);

    expect(Sale::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

it('sells to a walk-in who pays in full', function () {
    $sale = sell(['customer_name' => 'রফিক সাহেব', 'customer_phone' => '01711111111']);

    expect($sale->customer_id)->toBeNull()
        ->and($sale->buyerName())->toBe('রফিক সাহেব')
        ->and($sale->due_amount)->toBe('0.00');
});

it('names the buyer from their record when there is one', function () {
    $sale = sell([], withCustomer: true);

    expect($sale->customer_id)->toBe($this->customer->id)
        ->and($sale->buyerName())->toBe('করিম সাহেব');
});

it('refuses to take more than the invoice', function () {
    expect(fn () => sell(['paid_amount' => '30000']))->toThrow(InvalidArgumentException::class);

    expect(Sale::count())->toBe(0);
});

it('refuses to take money without an account for it to land in', function () {
    expect(fn () => sell([], withAccount: false))->toThrow(InvalidArgumentException::class);

    expect(Sale::count())->toBe(0);
});

it('puts the money in the account it was taken into', function () {
    $sale = sell();

    $transaction = Transaction::sole();

    expect($transaction->direction)->toBe(TransactionDirection::In)
        ->and($transaction->source_type)->toBe(TransactionSource::Sale)
        ->and($transaction->source_id)->toBe($sale->id)
        ->and($transaction->shop_id)->toBe($this->shop->id)
        ->and($this->drawer->refresh()->current_balance)->toBe('75000.00');
});

it('records the method the money came in by', function () {
    sell(['payment_method' => CashPaymentMethod::Bkash]);

    expect(Transaction::sole()->payment_method)->toBe(CashPaymentMethod::Bkash);
});

it('issues a printable invoice number', function () {
    $first = sell();
    $second = sell();

    expect($first->invoice_no)->toBe('INV-2607-0001')
        ->and($second->invoice_no)->toBe('INV-2607-0002');
});

it('refuses an invoice with no lines', function () {
    expect(fn () => sell([], []))->toThrow(InvalidArgumentException::class);

    expect(Sale::count())->toBe(0);
});

it('refuses a line that sells nothing', function (string $quantity) {
    expect(fn () => sell([], [['product_id' => $this->almirah->id, 'quantity' => $quantity]]))
        ->toThrow(InvalidArgumentException::class);

    expect(Sale::count())->toBe(0);
})->with(['0', '-2']);

it('refuses a line for something that is not stocked', function () {
    expect(fn () => sell([], [['product_id' => 9999, 'quantity' => '1']]))
        ->toThrow(InvalidArgumentException::class);

    expect(Sale::count())->toBe(0);
});

it('persists nothing when one line of several cannot be filled', function () {
    $chair = Product::factory()->inStock('1.00')->create();

    expect(fn () => sell([], [
        ['product_id' => $this->almirah->id, 'quantity' => '1'],
        ['product_id' => $chair->id, 'quantity' => '4'],
    ]))->toThrow(RuntimeException::class);

    expect(Sale::count())->toBe(0)
        ->and(SaleItem::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0)
        // The first line's stock was already taken when the second failed.
        ->and($this->almirah->refresh()->current_stock)->toBe('5.00')
        ->and($chair->refresh()->current_stock)->toBe('1.00');
});

it('records who sold it', function () {
    $user = User::factory()->create();

    $sale = $this->action->handle(
        data: ['sale_date' => '2026-07-20', 'shop_id' => $this->shop->id, 'note' => 'ডেলিভারি বাকি'],
        items: [['product_id' => $this->almirah->id, 'quantity' => '1']],
        account: $this->drawer,
        userId: $user->id,
    );

    expect($sale->created_by)->toBe($user->id)
        ->and($sale->note)->toBe('ডেলিভারি বাকি')
        ->and(StockMovement::sole()->created_by)->toBe($user->id);
});
