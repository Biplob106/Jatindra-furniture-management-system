<?php

namespace App\Actions\Suppliers;

use App\Enums\LedgerDirection;
use App\Enums\SupplierLedgerEntryType;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Creates or edits a supplier.
 *
 * An opening due is what was owed on the day the shop moved off paper. It is
 * stored on the row for reference, but the money only counts once it is a
 * supplier_ledger `opening` credit: balances are SUM(credit) - SUM(debit) and
 * nothing else is allowed to contribute to them.
 *
 * The opening figure is a day-one number and the form hides it once the
 * supplier exists, so this writes the ledger row on create only. Correcting it
 * later is an `adjustment` entry, which is the general rule for this table.
 */
class SaveSupplier
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Supplier $supplier = null): Supplier
    {
        return DB::transaction(function () use ($data, $supplier) {
            $isNew = $supplier === null;
            $supplier ??= new Supplier;

            $supplier->fill($data)->save();

            if ($isNew && bccomp((string) $supplier->opening_due, '0.00', 2) > 0) {
                $this->ledger->recordSupplier(
                    supplier: $supplier,
                    type: SupplierLedgerEntryType::Opening,
                    amount: (string) $supplier->opening_due,
                    entryDate: now()->toDateString(),
                    direction: LedgerDirection::Credit,
                    note: 'খাতার আগের বকেয়া',
                );
            }

            return $supplier;
        });
    }

    /**
     * Whether this supplier's books have been touched since day one. The
     * opening figure stops being editable once they have.
     */
    public static function hasActivity(Supplier $supplier): bool
    {
        return SupplierLedger::query()
            ->where('supplier_id', $supplier->id)
            ->where('type', '!=', SupplierLedgerEntryType::Opening)
            ->exists();
    }
}
