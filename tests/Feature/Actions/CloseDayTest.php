<?php

use App\Actions\Cash\CloseDay;
use App\Enums\AccountType;
use App\Enums\OrderStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\DailyClosing;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Shop;
use App\Models\User;
use App\Services\CashService;

beforeEach(function () {
    $this->action = app(CloseDay::class);
    $this->cash = app(CashService::class);

    $this->shop = Shop::factory()->create();
    $this->drawer = Account::factory()->create([
        'type' => AccountType::Cash,
        'shop_id' => $this->shop->id,
        'opening_balance' => 5000,
        'current_balance' => 5000,
    ]);
});

function takeIn(string $amount, string $date = '2026-07-20'): void
{
    test()->cash->record(
        account: test()->drawer,
        direction: TransactionDirection::In,
        amount: $amount,
        txnDate: $date,
        source: TransactionSource::Sale,
        shopId: test()->shop->id,
    );
}

function payOut(string $amount, string $date = '2026-07-20'): void
{
    test()->cash->record(
        account: test()->drawer,
        direction: TransactionDirection::Out,
        amount: $amount,
        txnDate: $date,
        source: TransactionSource::Expense,
        shopId: test()->shop->id,
    );
}

it('computes the day from the opening balance and the movement', function () {
    takeIn('12000');
    payOut('3500');

    $closing = $this->action->handle($this->shop, '2026-07-20', '13500');

    expect($closing->opening_balance)->toBe('5000.00')
        ->and($closing->total_in)->toBe('12000.00')
        ->and($closing->total_out)->toBe('3500.00')
        ->and($closing->net_amount)->toBe('8500.00')
        ->and($closing->expected_closing)->toBe('13500.00')
        ->and($closing->counted_cash)->toBe('13500.00')
        ->and($closing->difference)->toBe('0.00');
});

/**
 * The whole reason the screen exists: a drawer that is short says so.
 */
it('reports a short drawer as a negative difference', function () {
    takeIn('10000');

    $closing = $this->action->handle($this->shop, '2026-07-20', '14500');

    expect($closing->expected_closing)->toBe('15000.00')
        ->and($closing->difference)->toBe('-500.00');
});

it('reports an over drawer as a positive difference', function () {
    takeIn('10000');

    $closing = $this->action->handle($this->shop, '2026-07-20', '15200');

    expect($closing->difference)->toBe('200.00');
});

/**
 * Yesterday's money is today's opening balance.
 */
it('carries the previous days into the opening balance', function () {
    takeIn('4000', '2026-07-18');
    payOut('1000', '2026-07-19');

    $closing = $this->action->handle($this->shop, '2026-07-20', '8000');

    // 5000 opening + 4000 - 1000
    expect($closing->opening_balance)->toBe('8000.00')
        ->and($closing->total_in)->toBe('0.00')
        ->and($closing->expected_closing)->toBe('8000.00')
        ->and($closing->difference)->toBe('0.00');
});

it('leaves tomorrow out of today', function () {
    takeIn('10000');
    takeIn('9999', '2026-07-21');

    $closing = $this->action->handle($this->shop, '2026-07-20', '15000');

    expect($closing->total_in)->toBe('10000.00')
        ->and($closing->difference)->toBe('0.00');
});

/**
 * counted_cash is a count of notes. Mixing a bKash payment into the expected
 * figure would make the difference meaningless.
 */
it('ignores money that never touched the drawer', function () {
    $bkash = Account::factory()->create([
        'type' => AccountType::MobileBanking,
        'shop_id' => $this->shop->id,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->cash->record($bkash, TransactionDirection::In, '20000', '2026-07-20', TransactionSource::Sale, shopId: $this->shop->id);
    takeIn('3000');

    $closing = $this->action->handle($this->shop, '2026-07-20', '8000');

    expect($closing->total_in)->toBe('3000.00')
        ->and($closing->expected_closing)->toBe('8000.00')
        ->and($closing->difference)->toBe('0.00');
});

it('keeps two shops apart', function () {
    $otherShop = Shop::factory()->create();
    $otherDrawer = Account::factory()->create([
        'type' => AccountType::Cash,
        'shop_id' => $otherShop->id,
        'opening_balance' => 1000,
        'current_balance' => 1000,
    ]);

    takeIn('10000');
    $this->cash->record($otherDrawer, TransactionDirection::In, '7000', '2026-07-20', TransactionSource::Sale, shopId: $otherShop->id);

    $mine = $this->action->handle($this->shop, '2026-07-20', '15000');
    $theirs = $this->action->handle($otherShop, '2026-07-20', '8000');

    expect($mine->total_in)->toBe('10000.00')
        ->and($mine->difference)->toBe('0.00')
        ->and($theirs->total_in)->toBe('7000.00')
        ->and($theirs->difference)->toBe('0.00');
});

/**
 * A day recounted after a mistake should correct itself, not become two
 * conflicting answers.
 */
it('closes the same day twice into one row', function () {
    takeIn('10000');

    $this->action->handle($this->shop, '2026-07-20', '14000');
    $this->action->handle($this->shop, '2026-07-20', '15000');

    expect(DailyClosing::count())->toBe(1)
        ->and(DailyClosing::sole()->counted_cash)->toBe('15000.00')
        ->and(DailyClosing::sole()->difference)->toBe('0.00');
});

it('recomputes the books when a day is closed again after a late entry', function () {
    takeIn('10000');
    $this->action->handle($this->shop, '2026-07-20', '15000');

    // Someone remembers an expense from that day.
    payOut('2000');
    $closing = $this->action->handle($this->shop, '2026-07-20', '13000');

    expect(DailyClosing::count())->toBe(1)
        ->and($closing->total_out)->toBe('2000.00')
        ->and($closing->expected_closing)->toBe('13000.00')
        ->and($closing->difference)->toBe('0.00');
});

it('keeps separate days apart', function () {
    takeIn('10000', '2026-07-20');
    takeIn('4000', '2026-07-21');

    $this->action->handle($this->shop, '2026-07-20', '15000');
    $this->action->handle($this->shop, '2026-07-21', '19000');

    expect(DailyClosing::count())->toBe(2)
        ->and(DailyClosing::where('closing_date', '2026-07-21')->sole()->opening_balance)->toBe('15000.00');
});

it('records what customers still owe', function () {
    Order::factory()->confirmed()->withTotals('50000.00', '20000.00')->create(['shop_id' => $this->shop->id]);
    Order::factory()->confirmed()->withTotals('10000.00')->create(['shop_id' => $this->shop->id]);

    $closing = $this->action->handle($this->shop, '2026-07-20', '5000');

    expect($closing->total_receivable)->toBe('40000.00');
});

it('leaves finished orders out of the receivable', function () {
    Order::factory()->confirmed()->withTotals('50000.00')->create([
        'shop_id' => $this->shop->id,
        'status' => OrderStatus::Delivered,
    ]);

    $closing = $this->action->handle($this->shop, '2026-07-20', '5000');

    expect($closing->total_receivable)->toBe('0.00');
});

it('stamps who closed it and when', function () {
    $user = User::factory()->create();

    $closing = $this->action->handle($this->shop, '2026-07-20', '5000', 'সব ঠিক আছে', $user->id);

    expect($closing->closed_by)->toBe($user->id)
        ->and($closing->closed_at)->not->toBeNull()
        ->and($closing->note)->toBe('সব ঠিক আছে');
});

it('rejects a counted amount that is not a plain figure', function () {
    expect(fn () => $this->action->handle($this->shop, '2026-07-20', '15,000'))
        ->toThrow(InvalidArgumentException::class);

    expect(DailyClosing::count())->toBe(0);
});

it('holds to the paisa', function () {
    takeIn('1234.56');
    payOut('234.56');

    $closing = $this->action->handle($this->shop, '2026-07-20', '6000.01');

    expect($closing->expected_closing)->toBe('6000.00')
        ->and($closing->difference)->toBe('0.01');
});

/**
 * Goods taken on credit never touch the drawer, so they are invisible to the
 * arithmetic above. That is exactly why the figure sits beside it: a night
 * that looks quiet in cash can still have added to what we owe.
 */
it('records what was taken on credit today', function () {
    Purchase::factory()->onCredit('18000.00')->create([
        'shop_id' => $this->shop->id,
        'purchase_date' => '2026-07-20',
    ]);
    // Half settled at the counter, so only the rest was taken on credit.
    Purchase::factory()->withTotals('10000.00', '4000.00')->create([
        'shop_id' => $this->shop->id,
        'purchase_date' => '2026-07-20',
    ]);
    // Paid in full, so nothing was taken on credit.
    Purchase::factory()->withTotals('5000.00', '5000.00')->create([
        'shop_id' => $this->shop->id,
        'purchase_date' => '2026-07-20',
    ]);

    $closing = $this->action->handle($this->shop, '2026-07-20', '5000');

    expect($closing->credit_purchase_today)->toBe('24000.00');
});

it('leaves yesterday out of what was taken on credit today', function () {
    Purchase::factory()->onCredit('18000.00')->create([
        'shop_id' => $this->shop->id,
        'purchase_date' => '2026-07-19',
    ]);

    $closing = $this->action->handle($this->shop, '2026-07-20', '5000');

    expect($closing->credit_purchase_today)->toBe('0.00')
        // Yesterday's challan is still owed, so it stays in the total.
        ->and($closing->total_payable)->toBe('18000.00');
});

it('records what we still owe suppliers', function () {
    Purchase::factory()->onCredit('18000.00')->create([
        'shop_id' => $this->shop->id,
        'purchase_date' => '2026-07-10',
    ]);
    Purchase::factory()->withTotals('10000.00', '4000.00')->create([
        'shop_id' => $this->shop->id,
        'purchase_date' => '2026-07-18',
    ]);
    Purchase::factory()->withTotals('5000.00', '5000.00')->create([
        'shop_id' => $this->shop->id,
        'purchase_date' => '2026-07-19',
    ]);

    $closing = $this->action->handle($this->shop, '2026-07-20', '5000');

    expect($closing->total_payable)->toBe('24000.00');
});

it('keeps another shop out of what we owe', function () {
    $otherShop = Shop::factory()->create();

    Purchase::factory()->onCredit('18000.00')->create([
        'shop_id' => $otherShop->id,
        'purchase_date' => '2026-07-20',
    ]);

    $closing = $this->action->handle($this->shop, '2026-07-20', '5000');

    expect($closing->credit_purchase_today)->toBe('0.00')
        ->and($closing->total_payable)->toBe('0.00');
});
