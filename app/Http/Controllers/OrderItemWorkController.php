<?php

namespace App\Http\Controllers;

use App\Actions\Orders\DeleteItemWork;
use App\Actions\Orders\SaveItemWork;
use App\Http\Requests\Orders\ItemWorkRequest;
use App\Models\OrderItem;
use App\Models\OrderItemWork;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * Work handed to a worker on one order item. Completing it is what pays a
 * piece worker, so every refusal here is about money.
 */
class OrderItemWorkController extends Controller
{
    public function store(ItemWorkRequest $request, OrderItem $item, SaveItemWork $saveWork): RedirectResponse
    {
        try {
            $saveWork->handle($item, $request->validated(), userId: $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'কাজ দেওয়া হয়েছে।');
    }

    public function update(ItemWorkRequest $request, OrderItem $item, OrderItemWork $work, SaveItemWork $saveWork): RedirectResponse
    {
        abort_unless($work->order_item_id === $item->id, 404);

        try {
            $saveWork->handle($item, $request->validated(), $work, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'কাজের তথ্য বদলানো হয়েছে।');
    }

    public function destroy(OrderItem $item, OrderItemWork $work, DeleteItemWork $deleteWork): RedirectResponse
    {
        abort_unless($work->order_item_id === $item->id, 404);

        try {
            $deleteWork->handle($work);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'কাজ বাদ দেওয়া হয়েছে।');
    }
}
