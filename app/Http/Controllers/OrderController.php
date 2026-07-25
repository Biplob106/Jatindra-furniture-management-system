<?php

namespace App\Http\Controllers;

use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\RecordOrderAdvance;
use App\Actions\Orders\SaveOrder;
use App\Enums\CashPaymentMethod;
use App\Enums\DimensionUnit;
use App\Enums\OrderItemWorkStatus;
use App\Enums\OrderStatus;
use App\Enums\TransactionSource;
use App\Enums\WageType;
use App\Http\Requests\Orders\OrderPaymentRequest;
use App\Http\Requests\Orders\OrderRequest;
use App\Http\Requests\Orders\OrderStatusRequest;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\Transaction;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OrderController extends Controller
{
    /**
     * The order book. Phone search first, because that is how the counter finds
     * an order: the customer reads their number off the slip.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $orders = Order::query()
            ->with('customer:id,name,phone')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('order_no', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c
                    ->where('phone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                )
            ))
            ->when($status !== '' && $status !== 'open', fn ($query) => $query->where('status', $status))
            ->when($status === 'open', fn ($query) => $query->open())
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'status' => $order->status->value,
                'order_date' => $order->order_date->toDateString(),
                'expected_delivery_date' => $order->expected_delivery_date?->toDateString(),
                'total_amount' => $order->total_amount,
                'due_amount' => $order->due_amount,
                'customer' => [
                    'name' => $order->customer->name,
                    'phone' => $order->customer->phone,
                ],
            ]);

        return Inertia::render('orders/index', [
            'orders' => $orders,
            'search' => $search,
            'status' => $status,
            'statuses' => $this->statusOptions(),
            'canManage' => $request->user()->can('orders.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('orders/create', $this->formProps($request));
    }

    public function store(OrderRequest $request, SaveOrder $saveOrder): RedirectResponse
    {
        $validated = $request->validated();

        $order = $saveOrder->handle(
            data: $validated,
            items: $validated['items'],
            userId: $request->user()->id,
        );

        return to_route('orders.show', $order)->with('success', 'অর্ডার খসড়া হিসেবে সংরক্ষণ করা হয়েছে।');
    }

    public function show(Request $request, Order $order): Response
    {
        $order->load([
            'customer:id,name,phone,area,address',
            'shop:id,name',
            'items.category:id,name',
            'items.works.employee:id,name,wage_type',
            'media',
            'statusLogs.changedBy:id,name',
            'creator:id,name',
        ]);

        return Inertia::render('orders/show', [
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'status' => $order->status->value,
                'order_date' => $order->order_date->toDateString(),
                'expected_delivery_date' => $order->expected_delivery_date?->toDateString(),
                'delivered_at' => $order->delivered_at?->toDateTimeString(),
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'delivery_charge' => $order->delivery_charge,
                'total_amount' => $order->total_amount,
                'paid_amount' => $order->paid_amount,
                'due_amount' => $order->due_amount,
                'delivery_address' => $order->delivery_address,
                'note' => $order->note,
                'created_by' => $order->creator?->name,
                'customer' => [
                    'id' => $order->customer->id,
                    'name' => $order->customer->name,
                    'phone' => $order->customer->phone,
                    'area' => $order->customer->area,
                    'address' => $order->customer->address,
                ],
                'shop' => $order->shop->name,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'category' => $item->category?->name,
                    'wood_type' => $item->wood_type,
                    'design_no' => $item->design_no,
                    'polish_type' => $item->polish_type,
                    'dimensions' => $this->dimensionLabel($item),
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                    'status' => $item->status->value,
                    'target_date' => $item->target_date?->toDateString(),
                    'remarks' => $item->remarks,
                    'works' => $item->works->map(fn ($work) => [
                        'id' => $work->id,
                        'employee_id' => $work->employee_id,
                        'employee' => $work->employee->name,
                        'work_type' => $work->work_type,
                        'agreed_amount' => $work->agreed_amount,
                        'status' => $work->status->value,
                        'assigned_date' => $work->assigned_date?->toDateString(),
                        'completed_at' => $work->completed_at?->toDateTimeString(),
                    ])->all(),
                ])->all(),
                'status_logs' => $order->statusLogs->sortByDesc('id')->values()->map(fn ($log) => [
                    'id' => $log->id,
                    'from_status' => $log->from_status,
                    'to_status' => $log->to_status,
                    'changed_by' => $log->changedBy?->name,
                    'note' => $log->note,
                    'created_at' => $log->created_at?->toDateTimeString(),
                ])->all(),
                'photos' => $order->getMedia('photos')
                    ->concat($order->getMedia('designs'))
                    ->map(fn ($media) => [
                        'id' => $media->id,
                        'collection' => $media->collection_name,
                        'name' => $media->file_name,
                        // The web conversion, not the original: a phone photo
                        // straight off the camera is several megabytes.
                        'url' => $media->getUrl('web'),
                        'thumb' => $media->getUrl('thumb'),
                    ])
                    ->values()
                    ->all(),
                'payments' => Transaction::query()
                    ->where('source_type', TransactionSource::OrderPayment)
                    ->where('source_id', $order->id)
                    ->orderByDesc('txn_date')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (Transaction $payment) => [
                        'id' => $payment->id,
                        'txn_date' => $payment->txn_date->toDateString(),
                        'amount' => $payment->amount,
                        'direction' => $payment->direction->value,
                        'payment_method' => $payment->payment_method->value,
                        'note' => $payment->note,
                    ])
                    ->all(),
            ],
            // Where this order may go from here, straight off the enum, so the
            // buttons cannot offer a move the action would refuse.
            'nextStatuses' => array_map(
                fn (OrderStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                $order->status->allowedNext()
            ),
            'accounts' => Account::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Account $account) => ['value' => $account->id, 'label' => $account->name])
                ->all(),
            'paymentMethods' => array_map(
                fn (CashPaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()],
                CashPaymentMethod::cases()
            ),
            'today' => now()->toDateString(),
            // Only piece workers may carry a contract amount, so the form
            // knows which of them it can offer one for.
            'workers' => Employee::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'wage_type'])
                ->map(fn (Employee $employee) => [
                    'value' => $employee->id,
                    'label' => $employee->name,
                    'isPieceWorker' => $employee->wage_type === WageType::Piece,
                ])
                ->all(),
            'workStatuses' => array_map(
                fn (OrderItemWorkStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                OrderItemWorkStatus::cases()
            ),
            'canManage' => $request->user()->can('orders.manage'),
            'canTakePayment' => $request->user()->can('orders.payment'),
        ]);
    }

    /**
     * Moves an order along. The transition rules are ChangeOrderStatus's, so a
     * refusal comes back as its Bengali message rather than a validation error.
     */
    public function updateStatus(OrderStatusRequest $request, Order $order, ChangeOrderStatus $changeStatus): RedirectResponse
    {
        try {
            $changeStatus->handle(
                order: $order,
                to: OrderStatus::from($request->string('status')->toString()),
                userId: $request->user()->id,
                note: $request->input('note'),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'অর্ডারের অবস্থা বদলানো হয়েছে।');
    }

    /**
     * Takes money against the order.
     */
    public function storePayment(OrderPaymentRequest $request, Order $order, RecordOrderAdvance $recordAdvance): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $recordAdvance->handle(
                order: $order,
                amount: number_format((float) $validated['amount'], 2, '.', ''),
                account: Account::findOrFail($validated['account_id']),
                paidOn: $validated['paid_on'],
                paymentMethod: CashPaymentMethod::from($validated['payment_method'] ?? 'cash'),
                note: $validated['note'] ?? null,
                userId: $request->user()->id,
            );
        } catch (RuntimeException $e) {
            // Over the outstanding amount. Nothing was written.
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'টাকা জমা নেওয়া হয়েছে।');
    }

    /**
     * "৭২ × ৬০ × ২৪ ইঞ্চি", or nothing when the piece was not measured.
     */
    private function dimensionLabel(OrderItem $item): ?string
    {
        $parts = array_filter(
            [$item->length, $item->width, $item->height],
            fn ($value) => $value !== null && bccomp((string) $value, '0.00', 2) !== 0
        );

        if ($parts === []) {
            return null;
        }

        $trimmed = array_map(fn ($value) => rtrim(rtrim((string) $value, '0'), '.'), $parts);

        return implode(' × ', $trimmed).' '.$item->dimension_unit->label();
    }

    public function edit(Request $request, Order $order): Response|RedirectResponse
    {
        // A delivered or cancelled order is history. Sending the form back for
        // one would invite an edit the update() guard then refuses anyway.
        if (! $order->status->isOpen()) {
            return to_route('orders.show', $order)
                ->with('error', 'ডেলিভারি হয়ে যাওয়া বা বাতিল অর্ডার বদলানো যাবে না।');
        }

        return Inertia::render('orders/create', [
            ...$this->formProps($request),
            'order' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'customer_id' => $order->customer_id,
                'shop_id' => $order->shop_id,
                'order_date' => $order->order_date->toDateString(),
                'expected_delivery_date' => $order->expected_delivery_date?->toDateString(),
                'discount' => $order->discount,
                'delivery_charge' => $order->delivery_charge,
                'delivery_address' => $order->delivery_address,
                'note' => $order->note,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'category_id' => $item->category_id,
                    'item_name' => $item->item_name,
                    'description' => $item->description,
                    'wood_type' => $item->wood_type,
                    'design_no' => $item->design_no,
                    'length' => $item->length,
                    'width' => $item->width,
                    'height' => $item->height,
                    'dimension_unit' => $item->dimension_unit->value,
                    'polish_type' => $item->polish_type,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'target_date' => $item->target_date?->toDateString(),
                    'remarks' => $item->remarks,
                ])->all(),
            ],
        ]);
    }

    public function update(OrderRequest $request, Order $order, SaveOrder $saveOrder): RedirectResponse
    {
        if (! $order->status->isOpen()) {
            return back()->with('error', 'ডেলিভারি হয়ে যাওয়া বা বাতিল অর্ডার বদলানো যাবে না।');
        }

        $validated = $request->validated();

        try {
            $saveOrder->handle(
                data: $validated,
                items: $validated['items'],
                order: $order,
                userId: $request->user()->id,
            );
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('orders.show', $order)->with('success', 'অর্ডার বদলানো হয়েছে।');
    }

    /**
     * Props the create and edit forms share.
     *
     * The customer list is filtered server-side through a partial reload rather
     * than shipping every customer to the browser: a shop with thousands of
     * them would otherwise send the lot on every page load.
     *
     * @return array<string, mixed>
     */
    private function formProps(Request $request): array
    {
        $customerSearch = $request->string('customer_search')->toString();

        return [
            'customerSearch' => $customerSearch,
            'customers' => Customer::query()
                ->when($customerSearch !== '', fn ($query) => $query->where(fn ($q) => $q
                    ->where('phone', 'like', "%{$customerSearch}%")
                    ->orWhere('alt_phone', 'like', "%{$customerSearch}%")
                    ->orWhere('name', 'like', "%{$customerSearch}%")
                ))
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'phone', 'area'])
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'area' => $customer->area,
                ])
                ->all(),
            'shops' => Shop::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Shop $shop) => ['value' => $shop->id, 'label' => $shop->name])
                ->all(),
            'categories' => ProductCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ProductCategory $category) => ['value' => $category->id, 'label' => $category->name])
                ->all(),
            'dimensionUnits' => array_map(
                fn (DimensionUnit $unit) => ['value' => $unit->value, 'label' => $unit->label()],
                DimensionUnit::cases()
            ),
            'today' => now()->toDateString(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (OrderStatus $status) => ['value' => $status->value, 'label' => $status->label()],
            OrderStatus::cases()
        );
    }
}
