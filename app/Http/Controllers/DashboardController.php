<?php

namespace App\Http\Controllers;

use App\Queries\DashboardSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardSummary $summary) {}

    /**
     * The front page, assembled from whatever the reader is allowed to see.
     *
     * A block the reader has no permission for is not computed and not sent,
     * rather than sent and hidden. A storekeeper's dashboard never runs the
     * cash queries at all.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('dashboard', [
            'cash' => $user->can('transactions.view') ? $this->summary->cash() : null,
            'orders' => $user->can('orders.view') ? $this->summary->orders() : null,
            'payable' => $user->can('supplier_ledger.view') ? $this->summary->payable() : null,
            'labour' => $user->can('employee_ledger.view') ? $this->summary->labour() : null,
            'stock' => $user->can('stock.view') ? $this->summary->stock() : null,
        ]);
    }
}
