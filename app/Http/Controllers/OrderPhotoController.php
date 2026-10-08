<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Order-level photos from before images moved onto order items.
 *
 * New images are saved with the order form against each item. What is left
 * here only lets an old picture be deleted.
 */
class OrderPhotoController extends Controller
{
    public function destroy(Request $request, Order $order, Media $media): RedirectResponse
    {
        abort_unless(
            $media->model_type === Order::class && $media->model_id === $order->id,
            404
        );

        abort_unless($request->user()->can('orders.manage'), 403);

        $media->delete();

        return back()->with('success', 'ছবি মুছে ফেলা হয়েছে।');
    }
}
