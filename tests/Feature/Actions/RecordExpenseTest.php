<?php

use App\Actions\Expenses\RecordExpense;
use App\Enums\PaymentMethod;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashService;

beforeEach(function () {
    $this->action = app(RecordExpense::class);
    $this->account = Account::factory()->create(['opening_balance' => 20000, 'current_balance' => 20000]);
    $this->category = ExpenseCategory::factory()->create(['name' => 'কারেন্ট বিল']);
});

function expenseData(array $overrides = []): array
{
    return [
        'category_id' => test()->category->id,
        'expense_date' => '2026-07-20',
        'amount' => '3500',
        ...$overrides,
    ];
}

/**
 * Section 9: an expense is the operational row plus the money leaving. Both or
 * neither, so the closing can never show a cost with no withdrawal.
 */
it('writes one expense row and one cash row out', function () {
    $this->action->handle(expenseData(), $this->account);

    expect(Expense::count())->toBe(1)
        ->and(Transaction::count())->toBe(1);

    $transaction = Transaction::sole();

    expect($transaction->direction)->toBe(TransactionDirection::Out)
        ->and($transaction->amount)->toBe('3500.00')
        ->and($transaction->source_type)->toBe(TransactionSource::Expense)
        ->and($transaction->source_id)->toBe(Expense::sole()->id);
});

it('lowers the account balance by the amount', function () {
    $this->action->handle(expenseData(), $this->account);

    expect($this->account->fresh()->current_balance)->toBe('16500.00')
        ->and(app(CashService::class)->computedBalanceFor($this->account->fresh()))->toBe('16500.00');
});

it('names the category on the cash row when no note is given', function () {
    $this->action->handle(expenseData(), $this->account);

    expect(Transaction::sole()->note)->toBe('কারেন্ট বিল');
});

it('keeps the note when one is given', function () {
    $this->action->handle(expenseData(['note' => 'জুলাই মাসের বিল']), $this->account);

    expect(Transaction::sole()->note)->toBe('জুলাই মাসের বিল')
        ->and(Expense::sole()->note)->toBe('জুলাই মাসের বিল');
});

it('records who paid and how', function () {
    $user = User::factory()->create();

    $expense = $this->action->handle(
        expenseData(['paid_to' => 'পল্লী বিদ্যুৎ', 'payment_method' => 'bkash']),
        $this->account,
        $user->id
    );

    expect($expense->paid_to)->toBe('পল্লী বিদ্যুৎ')
        ->and($expense->payment_method)->toBe(PaymentMethod::Bkash)
        ->and($expense->created_by)->toBe($user->id)
        ->and(Transaction::sole()->payment_method->value)->toBe('bkash');
});

it('inherits the shop from the account', function () {
    $shop = Shop::factory()->create();
    $account = Account::factory()->create(['shop_id' => $shop->id, 'current_balance' => 5000]);

    $expense = $this->action->handle(expenseData(), $account);

    expect($expense->shop_id)->toBe($shop->id)
        ->and(Transaction::sole()->shop_id)->toBe($shop->id);
});

it('rejects a zero or negative amount', function (string $amount) {
    expect(fn () => $this->action->handle(expenseData(['amount' => $amount]), $this->account))
        ->toThrow(InvalidArgumentException::class);

    expect(Expense::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
})->with(['0', '-500']);

/**
 * Definition of done, clause 4. The cash box refuses, and the expense row
 * written moments earlier goes with it.
 */
it('leaves no expense behind when the drawer cannot cover it', function () {
    $small = Account::factory()->create(['opening_balance' => 100, 'current_balance' => 100]);

    expect(fn () => $this->action->handle(expenseData(), $small))->toThrow(RuntimeException::class);

    expect(Expense::count())->toBe(0)
        ->and(Transaction::count())->toBe(0)
        ->and($small->fresh()->current_balance)->toBe('100.00');
});

it('holds to the paisa', function () {
    $this->action->handle(expenseData(['amount' => '1234.56']), $this->account);

    expect(Expense::sole()->amount)->toBe('1234.56')
        ->and($this->account->fresh()->current_balance)->toBe('18765.44');
});
