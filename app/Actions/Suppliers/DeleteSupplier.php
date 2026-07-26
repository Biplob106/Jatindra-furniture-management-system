<?php

namespace App\Actions\Suppliers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\LedgerService;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

/**
 * A supplier with challans behind them or money still owed either way does not
 * vanish. Switch them off instead.
 *
 * The balance is checked in both directions: we may owe them, or we may have
 * paid ahead and be owed goods. Either is money with a name on it.
 */
class DeleteSupplier
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function handle(Supplier $supplier): void
    {
        if (Purchase::where('supplier_id', $supplier->id)->exists()) {
            throw new ReferencedRecordException(
                'এই সরবরাহকারীর কেনাকাটার হিসাব আছে, তাই মুছে ফেলা যাবে না। বদলে নিষ্ক্রিয় করে দিন।'
            );
        }

        if (bccomp($this->ledger->supplierBalanceFor($supplier), '0.00', 2) !== 0) {
            throw new ReferencedRecordException(
                'এই সরবরাহকারীর হিসাব এখনো শূন্য হয়নি, তাই মুছে ফেলা যাবে না।'
            );
        }

        DB::transaction(fn () => $supplier->delete());
    }
}
