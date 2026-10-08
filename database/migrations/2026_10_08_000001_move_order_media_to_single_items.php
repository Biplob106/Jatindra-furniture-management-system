<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Images used to hang off the order. They now belong to items.
 *
 * Where an order has exactly one item there is no doubt which piece a photo
 * shows, so it moves to that item. Orders with several items keep their
 * photos at order level, shown as "পুরনো ছবি", because guessing would put a
 * picture on the wrong piece.
 *
 * Only owner columns change. The default path generator stores files by media
 * id, so nothing moves on disk.
 */
return new class extends Migration
{
    private const ORDER = 'App\\Models\\Order';

    private const ITEM = 'App\\Models\\OrderItem';

    public function up(): void
    {
        $singleItemOrders = DB::table('order_items')
            ->select('order_id', DB::raw('MIN(id) as item_id'))
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) = 1')
            ->get();

        foreach ($singleItemOrders as $row) {
            DB::table('media')
                ->where('model_type', self::ORDER)
                ->where('model_id', $row->order_id)
                ->whereIn('collection_name', ['photos', 'designs'])
                ->update(['model_type' => self::ITEM, 'model_id' => $row->item_id]);
        }
    }

    public function down(): void
    {
        $items = DB::table('media')
            ->join('order_items', 'order_items.id', '=', 'media.model_id')
            ->where('media.model_type', self::ITEM)
            ->get(['media.id as media_id', 'order_items.order_id']);

        foreach ($items as $row) {
            DB::table('media')
                ->where('id', $row->media_id)
                ->update(['model_type' => self::ORDER, 'model_id' => $row->order_id]);
        }
    }
};
