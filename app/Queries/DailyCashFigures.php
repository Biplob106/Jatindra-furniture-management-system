<?php

namespace App\Queries;

use App\Enums\AccountType;
use App\Enums\TransactionDirection;
use App\Models\Account;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * What the books say a shop's cash drawer holds on a given day.
 *
 * Cash accounts only. counted_cash is a physical count of notes, so mixing
 * bKash and bank movement into the expected figure would make the difference
 * meaningless: it would never be zero and nobody would trust it. Money moving
 * through a mobile wallet is real, it just is not in the drawer.
 *
 * Nothing here is stored. The figures are computed from transactions every
 * time, so a closing can be reopened and recomputed without the numbers having
 * drifted in the meantime.
 */
class DailyCashFigures
{
    /**
     * @return array{
     *     opening_balance: string,
     *     total_in: string,
     *     total_out: string,
     *     net_amount: string,
     *     expected_closing: string,
     *     total_receivable: string,
     *     credit_purchase_today: string,
     *     total_payable: string
     * }
     */
    public function forShopOn(int $shopId, string $date): array
    {
        $accountIds = $this->cashAccountIdsFor($shopId);

        $opening = $this->movementBefore($accountIds, $date, $shopId);
        $in = $this->movementOn($accountIds, $date, $shopId, TransactionDirection::In);
        $out = $this->movementOn($accountIds, $date, $shopId, TransactionDirection::Out);

        $net = bcsub($in, $out, 2);

        return [
            'opening_balance' => $opening,
            'total_in' => $in,
            'total_out' => $out,
            'net_amount' => $net,
            'expected_closing' => bcadd($opening, $net, 2),
            'total_receivable' => $this->receivableFor($shopId),
            'credit_purchase_today' => $this->creditPurchasesOn($shopId, $date),
            'total_payable' => $this->payableFor($shopId),
        ];
    }

    /**
     * Cash accounts for this shop, plus the ones not tied to any shop, since a
     * single-shop business usually leaves shop_id null.
     *
     * @return list<int>
     */
    private function cashAccountIdsFor(int $shopId): array
    {
        return Account::query()
            ->where('type', AccountType::Cash)
            ->where(fn ($query) => $query->where('shop_id', $shopId)->orWhereNull('shop_id'))
            ->pluck('id')
            ->all();
    }

    /**
     * The drawer at the start of the day: opening balances plus everything
     * that moved before this date.
     *
     * @param  list<int>  $accountIds
     */
    private function movementBefore(array $accountIds, string $date, int $shopId): string
    {
        if ($accountIds === []) {
            return '0.00';
        }

        $openingBalances = Account::whereIn('id', $accountIds)->sum('opening_balance');

        $movement = Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->where('txn_date', '<', $date)
            ->where(fn ($query) => $query->where('shop_id', $shopId)->orWhereNull('shop_id'))
            ->sum(DB::raw("CASE WHEN direction = 'in' THEN amount ELSE -amount END"));

        return bcadd(
            number_format((float) $openingBalances, 2, '.', ''),
            number_format((float) $movement, 2, '.', ''),
            2
        );
    }

    /**
     * @param  list<int>  $accountIds
     */
    private function movementOn(array $accountIds, string $date, int $shopId, TransactionDirection $direction): string
    {
        if ($accountIds === []) {
            return '0.00';
        }

        $total = Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->where('txn_date', $date)
            ->where('direction', $direction)
            ->where(fn ($query) => $query->where('shop_id', $shopId)->orWhereNull('shop_id'))
            ->sum('amount');

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * What customers still owe on open orders. Not part of the drawer, but the
     * figure the owner wants beside it at closing time.
     */
    private function receivableFor(int $shopId): string
    {
        $total = Order::query()
            ->where('shop_id', $shopId)
            ->open()
            ->sum('due_amount');

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * What was taken on credit today: the part of today's challans that was
     * not settled at the counter.
     *
     * Goods that arrived without money leaving are invisible to the drawer
     * arithmetic above, which is exactly why the figure sits beside it. A
     * night that looks quiet in cash can still have added to what we owe.
     */
    private function creditPurchasesOn(int $shopId, string $date): string
    {
        $total = Purchase::query()
            ->where('purchase_date', $date)
            ->where('due_amount', '>', 0)
            ->where(fn ($query) => $query->where('shop_id', $shopId)->orWhereNull('shop_id'))
            ->sum('due_amount');

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * What we still owe suppliers in total, from the purchases themselves.
     *
     * Read from purchases rather than supplier_ledger on purpose: this is the
     * figure the aging list totals to, invoice by invoice. The ledger balance
     * is the same money seen from the supplier's side and can differ by an
     * on-account payment that has not been allocated to a challan yet.
     */
    private function payableFor(int $shopId): string
    {
        $total = Purchase::query()
            ->where('due_amount', '>', 0)
            ->where(fn ($query) => $query->where('shop_id', $shopId)->orWhereNull('shop_id'))
            ->sum('due_amount');

        return number_format((float) $total, 2, '.', '');
    }
}
