<?php

namespace App\Actions\Orders;

use App\Enums\LedgerEntryType;
use App\Enums\OrderItemWorkStatus;
use App\Enums\WageType;
use App\Models\Employee;
use App\Models\OrderItem;
use App\Models\OrderItemWork;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hands a piece of work on an order item to a worker, or moves it along.
 *
 * This is the third and last way a worker earns. Attendance pays daily staff,
 * the month-end run pays monthly staff, and work reaching `done` with an
 * agreed_amount pays piece workers.
 *
 * The credit is synced against the work row rather than inserted, exactly as
 * attendance syncs against the attendance row. So marking a job done twice
 * pays once, correcting the agreed amount afterwards adjusts the one credit,
 * and moving it back off `done` takes the money away again instead of leaving
 * it sitting in the ledger.
 *
 * An agreed amount is only accepted for a piece worker. A daily or monthly
 * worker is already being paid for the same hours, so a contract amount on top
 * would pay them twice for one day's work. Refusing it loudly is better than
 * writing a row nobody notices.
 */
class SaveItemWork
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(
        OrderItem $item,
        array $data,
        ?OrderItemWork $work = null,
        ?int $userId = null,
    ): OrderItemWork {
        $employee = Employee::findOrFail($data['employee_id']);
        $status = OrderItemWorkStatus::from($data['status'] ?? OrderItemWorkStatus::Assigned->value);
        $agreed = (string) ($data['agreed_amount'] ?? 0);

        if (bccomp($agreed, '0.00', 2) > 0 && $employee->wage_type !== WageType::Piece) {
            throw new RuntimeException(
                "{$employee->name} কাজ চুক্তির কর্মী নন, তাই চুক্তির টাকা দেওয়া যাবে না।"
            );
        }

        return DB::transaction(function () use ($item, $data, $work, $employee, $status, $agreed, $userId) {
            $work ??= new OrderItemWork;

            $work->fill([
                'order_item_id' => $item->id,
                'employee_id' => $employee->id,
                'trade_id' => $data['trade_id'] ?? $employee->trade_id,
                'work_type' => $data['work_type'] ?? null,
                'agreed_amount' => $agreed,
                'assigned_date' => $data['assigned_date'] ?? now()->toDateString(),
                'status' => $status,
                'note' => $data['note'] ?? null,
            ]);

            // Timestamps follow the status rather than being sent in, so the
            // record of when work started and finished cannot be back-written.
            if ($status === OrderItemWorkStatus::Working && $work->started_at === null) {
                $work->started_at = now();
            }

            $work->completed_at = $status === OrderItemWorkStatus::Done
                ? ($work->completed_at ?? now())
                : null;

            $work->save();

            $this->syncEarning($work, $employee, $status, $agreed, $userId);

            return $work;
        });
    }

    /**
     * Only `done` pays. Rejected work pays nothing, which is the point of
     * having the status at all.
     */
    private function syncEarning(
        OrderItemWork $work,
        Employee $employee,
        OrderItemWorkStatus $status,
        string $agreed,
        ?int $userId,
    ): void {
        $amount = $status->earnsPieceRate() ? $agreed : '0.00';

        $this->ledger->syncForReference(
            employee: $employee,
            type: LedgerEntryType::PieceEarned,
            amount: $amount,
            entryDate: ($work->completed_at ?? now())->toDateString(),
            reference: $work,
            note: $work->work_type,
            createdBy: $userId,
        );
    }
}
