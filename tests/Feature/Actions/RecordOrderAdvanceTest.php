<?php

use App\Actions\Orders\RecordOrderAdvance;
use App\Enums\CashPaymentMethod;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashService;

beforeEach(function () {
    $this->action = app(RecordOrderAdvance::class);
    $this->customer = Customer::factory()->create();
    $this->account = Account::factory()->create(['opening_balance' => 0, 'current_balance' => 0]);

    $this->order = Order::factory()
        ->confirmed()
        ->withTotals('50000.00')
        ->create(['customer_id' => $this->customer->id]);
});

/**
 * Section 9, first row: money in, the order's due comes down, and no party
 * ledger is touched. A customer's debt lives on the order.
 */
it('writes one cash row in and brings the due down', function () {
    $this->action->handle($this->order, '20000.00', $this->account, '2026-07-20');

    expect(Transaction::count())->toBe(1);

    $transaction = Transaction::sole();

    expect($transaction->direction)->toBe(TransactionDirection::In)
        ->and($transaction->amount)->toBe('20000.00')
        ->and($transaction->source_type)->toBe(TransactionSource::OrderPayment)
        ->and($transaction->source_id)->toBe($this->order->id)
        ->and($transaction->party_type)->toBe(Customer::class)
        ->and($transaction->party_id)->toBe($this->customer->id);

    $order = $this->order->fresh();

    expect($order->paid_amount)->toBe('20000.00')
        ->and($order->due_amount)->toBe('30000.00');
});

it('raises the account balance by what was taken', function () {
    $this->action->handle($this->order, '20000.00', $this->account, '2026-07-20');

    expect($this->account->fresh()->current_balance)->toBe('20000.00')
        ->and(app(CashService::class)->computedBalanceFor($this->account->fresh()))->toBe('20000.00');
});

it('adds up across several payments', function () {
    $this->action->handle($this->order, '20000.00', $this->account, '2026-07-20');
    $this->action->handle($this->order, '15000.50', $this->account, '2026-07-25');
    $this->action->handle($this->order, '4999.50', $this->account, '2026-07-30');

    $order = $this->order->fresh();

    expect(Transaction::count())->toBe(3)
        ->and($order->paid_amount)->toBe('40000.00')
        ->and($order->due_amount)->toBe('10000.00');
});

it('settles an order exactly', function () {
    $this->action->handle($this->order, '50000.00', $this->account, '2026-07-20');

    expect($this->order->fresh()->due_amount)->toBe('0.00');
});

/**
 * Taking more than is owed would leave a negative due, which reads as the shop
 * owing the customer and would flow straight into the receivables report.
 */
it('refuses to take more than is owed', function () {
    expect(fn () => $this->action->handle($this->order, '50000.01', $this->account, '2026-07-20'))
        ->toThrow(RuntimeException::class);

    expect(Transaction::count())->toBe(0)
        ->and($this->order->fresh()->due_amount)->toBe('50000.00')
        ->and($this->account->fresh()->current_balance)->toBe('0.00');
});

it('refuses to take more than what is left after earlier payments', function () {
    $this->action->handle($this->order, '45000.00', $this->account, '2026-07-20');

    expect(fn () => $this->action->handle($this->order, '6000.00', $this->account, '2026-07-21'))
        ->toThrow(RuntimeException::class);

    expect(Transaction::count())->toBe(1)
        ->and($this->order->fresh()->due_amount)->toBe('5000.00');
});

it('rejects a zero or negative payment', function (string $amount) {
    expect(fn () => $this->action->handle($this->order, $amount, $this->account, '2026-07-20'))
        ->toThrow(InvalidArgumentException::class);

    expect(Transaction::count())->toBe(0);
})->with(['0.00', '-100.00']);

it('holds to the paisa', function () {
    $order = Order::factory()->confirmed()->withTotals('999.99')->create();

    $this->action->handle($order, '333.33', $this->account, '2026-07-20');
    $this->action->handle($order, '333.33', $this->account, '2026-07-21');

    expect($order->fresh()->paid_amount)->toBe('666.66')
        ->and($order->fresh()->due_amount)->toBe('333.33');
});

it('records the payment method and note', function () {
    $transaction = $this->action->handle(
        order: $this->order,
        amount: '10000.00',
        account: $this->account,
        paidOn: '2026-07-20',
        paymentMethod: CashPaymentMethod::Bkash,
        note: 'অগ্রিম বাবদ',
    );

    expect($transaction->payment_method)->toBe(CashPaymentMethod::Bkash)
        ->and($transaction->note)->toBe('অগ্রিম বাবদ');
});

it('names the order in the note when none is given', function () {
    $transaction = $this->action->handle($this->order, '1000.00', $this->account, '2026-07-20');

    expect($transaction->note)->toContain($this->order->order_no);
});

it('stamps who took the money', function () {
    $user = User::factory()->create();

    $this->action->handle($this->order, '1000.00', $this->account, '2026-07-20', userId: $user->id);

    expect(Transaction::sole()->created_by)->toBe($user->id);
});

it('inherits the shop from the order', function () {
    $transaction = $this->action->handle($this->order, '1000.00', $this->account, '2026-07-20');

    expect($transaction->shop_id)->toBe($this->order->shop_id);
});

/**
 * paid_amount is rebuilt from the cash rows rather than incremented, so a
 * figure that has drifted is corrected by the next payment instead of carrying
 * the error forward.
 */
it('rebuilds paid_amount from the cash rows rather than trusting the column', function () {
    $this->action->handle($this->order, '10000.00', $this->account, '2026-07-20');

    // Something corrupted the denormalised figure.
    $this->order->forceFill(['paid_amount' => '999999.00', 'due_amount' => '0.00'])->save();

    $this->action->handle($this->order, '5000.00', $this->account, '2026-07-21');

    expect($this->order->fresh()->paid_amount)->toBe('15000.00')
        ->and($this->order->fresh()->due_amount)->toBe('35000.00');
});

it('keeps two orders on the same account apart', function () {
    $other = Order::factory()->confirmed()->withTotals('10000.00')->create();

    $this->action->handle($this->order, '20000.00', $this->account, '2026-07-20');
    $this->action->handle($other, '4000.00', $this->account, '2026-07-20');

    expect($this->order->fresh()->paid_amount)->toBe('20000.00')
        ->and($other->fresh()->paid_amount)->toBe('4000.00')
        ->and($this->account->fresh()->current_balance)->toBe('24000.00');
});
