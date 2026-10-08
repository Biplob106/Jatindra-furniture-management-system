<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Creates or edits an order, its items, and the items' images.
 *
 * Money is never taken from the form. line_total is computed from quantity and
 * unit_price, subtotal is summed in SQL from the items that actually landed,
 * and the order total follows from those. A client sending its own totals is
 * ignored, because the arithmetic is the one thing that must not be arguable.
 */
class SaveOrder
{
    /**
     * Media to delete once the save has committed.
     *
     * Deleting a media row removes its file from disk, and a rollback cannot
     * put the file back. So removals are collected inside the transaction and
     * carried out only after it commits.
     *
     * @var list<Media>
     */
    private array $mediaToDelete = [];

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function handle(array $data, array $items, ?Order $order = null, ?int $userId = null): Order
    {
        $this->mediaToDelete = [];

        $order = DB::transaction(function () use ($data, $items, $order, $userId) {
            $isNew = $order === null;

            if ($isNew) {
                $order = new Order;
                $order->created_by = $userId;
                // Every order starts as a draft. It earns its number when it
                // is confirmed, not before.
                $order->status = OrderStatus::Draft;
            }

            $order->fill([
                'customer_id' => $data['customer_id'],
                'shop_id' => $data['shop_id'],
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'discount' => $data['discount'] ?? 0,
                'delivery_charge' => $data['delivery_charge'] ?? 0,
                'delivery_address' => $data['delivery_address'] ?? null,
                'note' => $data['note'] ?? null,
            ])->save();

            $this->syncItems($order, $items, $userId);
            $this->recalculateTotals($order);

            return $order->refresh();
        });

        foreach ($this->mediaToDelete as $media) {
            $media->delete();
        }

        return $order;
    }

    /**
     * Brings the order's items in line with what was submitted.
     *
     * Items are matched by id rather than wiped and re-inserted, because
     * order_items cascades to order_item_works: deleting an item would take
     * a worker's piece-work record with it, and the ledger entry that record
     * paid for is never deleted. That would leave money pointing at nothing.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(Order $order, array $items, ?int $userId): void
    {
        $keptIds = [];

        foreach ($items as $index => $row) {
            $quantity = (string) ($row['quantity'] ?? 1);
            $unitPrice = (string) ($row['unit_price'] ?? 0);

            $attributes = [
                'category_id' => $row['category_id'] ?? null,
                'item_name' => $row['item_name'],
                'description' => $row['description'] ?? null,
                'wood_type' => $row['wood_type'] ?? null,
                'design_no' => $row['design_no'] ?? null,
                'length' => $row['length'] ?? null,
                'width' => $row['width'] ?? null,
                'height' => $row['height'] ?? null,
                'dimension_unit' => $row['dimension_unit'] ?? 'inch',
                'polish_type' => $row['polish_type'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                // Computed here, never accepted from the caller.
                'line_total' => bcmul($quantity, $unitPrice, 2),
                'target_date' => $row['target_date'] ?? null,
                'remarks' => $row['remarks'] ?? null,
            ];

            $item = isset($row['id'])
                ? $order->items()->whereKey($row['id'])->firstOrFail()
                : $order->items()->make();

            $item->fill($attributes);
            $item->order_id = $order->id;
            $item->save();

            $this->syncMedia($item, $row, $index, $userId);

            $keptIds[] = $item->id;
        }

        $this->removeDroppedItems($order, $keptIds);
    }

    /**
     * Adds the images picked for this item and queues the ones removed from it.
     *
     * A removal is honoured only for media this very item owns. An id from
     * another item, or from the order's older order-level photos, refuses the
     * whole save rather than deleting someone else's picture.
     *
     * @param  array<string, mixed>  $row
     */
    private function syncMedia(OrderItem $item, array $row, int $index, ?int $userId): void
    {
        foreach ($row['remove_media'] ?? [] as $mediaId) {
            $media = Media::query()
                ->whereKey($mediaId)
                ->where('model_type', $item->getMorphClass())
                ->where('model_id', $item->id)
                ->first();

            if ($media === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.remove_media" => 'এই ছবিটি এই পণ্যের নয়, তাই মোছা যায়নি। পাতাটি আবার খুলুন।',
                ]);
            }

            $this->mediaToDelete[] = $media;
        }

        foreach (['new_photos' => 'photos', 'new_designs' => 'designs'] as $field => $collection) {
            foreach ($row[$field] ?? [] as $file) {
                $item->addMedia($file)
                    ->withCustomProperties(['uploaded_by' => $userId])
                    ->toMediaCollection($collection);
            }
        }
    }

    /**
     * @param  list<int>  $keptIds
     */
    private function removeDroppedItems(Order $order, array $keptIds): void
    {
        $dropped = $order->items()
            ->when($keptIds !== [], fn ($query) => $query->whereKeyNot($keptIds))
            ->withCount('works')
            ->get();

        foreach ($dropped as $item) {
            if ($item->works_count > 0) {
                throw new ReferencedRecordException(
                    "\"{$item->item_name}\" এর কাজ কর্মীকে দেওয়া হয়েছে, তাই এটি বাদ দেওয়া যাবে না।"
                );
            }

            // The row goes now; its images go after commit, with the rest.
            array_push($this->mediaToDelete, ...$item->media()->get()->all());
            $item->deletePreservingMedia();
        }
    }

    /**
     * subtotal from the items in SQL, then the order total from that.
     *
     * discount and delivery_charge are read back from the stored row rather
     * than the input, so the arithmetic uses the values that were actually
     * saved after casting.
     */
    private function recalculateTotals(Order $order): void
    {
        $subtotal = number_format(
            (float) OrderItem::where('order_id', $order->id)->sum('line_total'),
            2, '.', ''
        );

        $total = bcadd(
            bcsub($subtotal, (string) $order->discount, 2),
            (string) $order->delivery_charge,
            2
        );

        $order->forceFill([
            'subtotal' => $subtotal,
            'total_amount' => $total,
            // paid_amount is owned by whatever records payments; due follows it.
            'due_amount' => bcsub($total, (string) $order->paid_amount, 2),
        ])->save();
    }
}
