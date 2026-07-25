<?php

namespace App\Http\Controllers;

use App\Http\Requests\Orders\OrderPhotoRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Photos of the piece and the design drawings that came with the order.
 *
 * These are what the shop argues from later: "this is the headboard you
 * approved". Uploading is part of managing the order, not a separate right.
 */
class OrderPhotoController extends Controller
{
    public function store(OrderPhotoRequest $request, Order $order): RedirectResponse
    {
        $collection = $request->input('collection', 'photos');

        foreach ($request->file('photos') as $photo) {
            $order->addMedia($photo)
                // Keeps the uploaded file out of the way once it is stored.
                ->withCustomProperties(['uploaded_by' => $request->user()->id])
                ->toMediaCollection($collection);
        }

        return back()->with('success', 'ছবি যোগ করা হয়েছে।');
    }

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
