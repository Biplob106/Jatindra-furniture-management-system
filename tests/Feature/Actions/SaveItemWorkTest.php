<?php

use App\Actions\Orders\DeleteItemWork;
use App\Actions\Orders\SaveItemWork;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\OrderItemWorkStatus;
use App\Enums\WageType;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\OrderItem;
use App\Models\OrderItemWork;
use App\Models\User;
use App\Services\LedgerService;
use App\Support\ReferencedRecordException;

beforeEach(function () {
    $this->action = app(SaveItemWork::class);
    $this->ledger = app(LedgerService::class);
    $this->item = OrderItem::factory()->create();
    $this->worker = Employee::factory()->piece()->create(['name' => 'সুমন']);
});

function workData(array $overrides = []): array
{
    return [
        'employee_id' => test()->worker->id,
        'agreed_amount' => '3000',
        'work_type' => 'নকশা',
        'assigned_date' => '2026-07-20',
        'status' => 'assigned',
        ...$overrides,
    ];
}

it('assigns work without paying anything yet', function () {
    $this->action->handle($this->item, workData());

    expect(OrderItemWork::count())->toBe(1)
        ->and(EmployeeLedger::count())->toBe(0)
        ->and($this->ledger->balanceFor($this->worker))->toBe('0.00');
});

it('pays nothing while the work is only in progress', function () {
    $this->action->handle($this->item, workData(['status' => 'working']));

    expect(EmployeeLedger::count())->toBe(0)
        ->and(OrderItemWork::sole()->started_at)->not->toBeNull();
});

/**
 * The third and last way a worker earns.
 */
it('credits the agreed amount when the work is done', function () {
    $this->action->handle($this->item, workData(['status' => 'done']));

    expect(EmployeeLedger::count())->toBe(1);

    $entry = EmployeeLedger::sole();

    expect($entry->type)->toBe(LedgerEntryType::PieceEarned)
        ->and($entry->direction)->toBe(LedgerDirection::Credit)
        ->and($entry->amount)->toBe('3000.00')
        ->and($entry->reference_type)->toBe(OrderItemWork::class)
        ->and($entry->reference_id)->toBe(OrderItemWork::sole()->id)
        ->and($this->ledger->balanceFor($this->worker))->toBe('3000.00');
});

it('stamps completed_at when the work is done', function () {
    $this->action->handle($this->item, workData(['status' => 'done']));

    expect(OrderItemWork::sole()->completed_at)->not->toBeNull();
});

/**
 * Rejected work pays nothing. That is the point of having the status.
 */
it('pays nothing for rejected work', function () {
    $this->action->handle($this->item, workData(['status' => 'rejected']));

    expect(EmployeeLedger::count())->toBe(0)
        ->and($this->ledger->balanceFor($this->worker))->toBe('0.00');
});

/**
 * The same reference-sync that keeps attendance idempotent.
 */
it('pays once when the same job is marked done twice', function () {
    $work = $this->action->handle($this->item, workData(['status' => 'done']));
    $this->action->handle($this->item, workData(['status' => 'done']), $work);

    expect(EmployeeLedger::count())->toBe(1)
        ->and($this->ledger->balanceFor($this->worker))->toBe('3000.00');
});

it('adjusts the one credit when the agreed amount is corrected', function () {
    $work = $this->action->handle($this->item, workData(['status' => 'done']));

    $this->action->handle($this->item, workData(['status' => 'done', 'agreed_amount' => '3500']), $work);

    expect(EmployeeLedger::count())->toBe(1)
        ->and($this->ledger->balanceFor($this->worker))->toBe('3500.00');
});

/**
 * Work walked back off done has to take the money with it, not leave a credit
 * for a job that is no longer finished.
 */
it('takes the money back when done work is reopened', function () {
    $work = $this->action->handle($this->item, workData(['status' => 'done']));
    expect($this->ledger->balanceFor($this->worker))->toBe('3000.00');

    $this->action->handle($this->item, workData(['status' => 'working']), $work);

    expect(EmployeeLedger::count())->toBe(0)
        ->and($this->ledger->balanceFor($this->worker))->toBe('0.00')
        ->and($work->fresh()->completed_at)->toBeNull();
});

it('takes the money back when done work is rejected', function () {
    $work = $this->action->handle($this->item, workData(['status' => 'done']));

    $this->action->handle($this->item, workData(['status' => 'rejected']), $work);

    expect($this->ledger->balanceFor($this->worker))->toBe('0.00');
});

it('moves the credit when the job is handed to someone else', function () {
    $other = Employee::factory()->piece()->create(['name' => 'রফিক']);

    $work = $this->action->handle($this->item, workData(['status' => 'done']));
    $this->action->handle($this->item, workData(['status' => 'done', 'employee_id' => $other->id]), $work);

    expect(EmployeeLedger::count())->toBe(1)
        ->and($this->ledger->balanceFor($this->worker))->toBe('0.00')
        ->and($this->ledger->balanceFor($other))->toBe('3000.00');
});

/**
 * A daily or monthly worker is already being paid for those hours. A contract
 * amount on top would pay them twice for one day's work.
 */
it('refuses a contract amount for a worker who is not on piece rate', function (string $wageType) {
    $employee = Employee::factory()->create(['wage_type' => WageType::from($wageType)]);

    expect(fn () => $this->action->handle($this->item, workData([
        'employee_id' => $employee->id,
        'status' => 'done',
    ])))->toThrow(RuntimeException::class);

    expect(OrderItemWork::count())->toBe(0)
        ->and(EmployeeLedger::count())->toBe(0);
})->with(['daily', 'monthly']);

it('lets a daily worker be assigned work with no contract amount', function () {
    $daily = Employee::factory()->create(['wage_type' => WageType::Daily]);

    $this->action->handle($this->item, workData([
        'employee_id' => $daily->id,
        'agreed_amount' => '0',
        'status' => 'done',
    ]));

    expect(OrderItemWork::count())->toBe(1)
        ->and(EmployeeLedger::count())->toBe(0);
});

it('writes no credit for done work agreed at nothing', function () {
    $this->action->handle($this->item, workData(['agreed_amount' => '0', 'status' => 'done']));

    expect(EmployeeLedger::count())->toBe(0);
});

it('holds to the paisa', function () {
    $this->action->handle($this->item, workData(['agreed_amount' => '1333.33', 'status' => 'done']));

    expect($this->ledger->balanceFor($this->worker))->toBe('1333.33');
});

it('keeps several jobs on one item apart', function () {
    $second = Employee::factory()->piece()->create();

    $this->action->handle($this->item, workData(['status' => 'done']));
    $this->action->handle($this->item, workData([
        'employee_id' => $second->id,
        'agreed_amount' => '1500',
        'status' => 'done',
    ]));

    expect(OrderItemWork::count())->toBe(2)
        ->and(EmployeeLedger::count())->toBe(2)
        ->and($this->ledger->balanceFor($this->worker))->toBe('3000.00')
        ->and($this->ledger->balanceFor($second))->toBe('1500.00');
});

it('stamps who recorded it', function () {
    $user = User::factory()->create();

    $this->action->handle($this->item, workData(['status' => 'done']), userId: $user->id);

    expect(EmployeeLedger::sole()->created_by)->toBe($user->id);
});

it('deletes an assignment that was never finished', function () {
    $work = $this->action->handle($this->item, workData());

    app(DeleteItemWork::class)->handle($work);

    expect(OrderItemWork::count())->toBe(0)
        ->and(EmployeeLedger::count())->toBe(0);
});

/**
 * A credit is money the worker has earned, and ledger rows are never deleted.
 */
it('refuses to delete work that is already done', function () {
    $work = $this->action->handle($this->item, workData(['status' => 'done']));

    expect(fn () => app(DeleteItemWork::class)->handle($work))
        ->toThrow(ReferencedRecordException::class);

    expect(OrderItemWork::count())->toBe(1)
        ->and($this->ledger->balanceFor($this->worker))->toBe('3000.00');
});

it('dates the credit from when the work was completed', function () {
    $work = OrderItemWork::factory()->create([
        'order_item_id' => $this->item->id,
        'employee_id' => $this->worker->id,
        'completed_at' => '2026-07-15 12:00:00',
        'status' => OrderItemWorkStatus::Working,
    ]);

    $this->action->handle($this->item, workData(['status' => 'done']), $work);

    expect(EmployeeLedger::sole()->entry_date->toDateString())->toBe('2026-07-15');
});
