<?php

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('public');

    $this->migration = require database_path('migrations/2026_10_08_000001_move_order_media_to_single_items.php');
});

function oldPhoto(Order $order, string $collection = 'photos'): Media
{
    return $order->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection($collection);
}

it('moves order-level images onto the only item, keeping the collection', function () {
    $item = OrderItem::factory()->create();
    $photo = oldPhoto($item->order);
    $design = oldPhoto($item->order, 'designs');

    $this->migration->up();

    expect($photo->fresh()->model_type)->toBe(OrderItem::class)
        ->and($photo->fresh()->model_id)->toBe($item->id)
        ->and($photo->fresh()->collection_name)->toBe('photos')
        ->and($design->fresh()->collection_name)->toBe('designs')
        ->and(file_exists($photo->fresh()->getPath()))->toBeTrue();
});

it('leaves orders with several items or none alone', function () {
    $multi = Order::factory()->create();
    OrderItem::factory()->count(2)->create(['order_id' => $multi->id]);
    $empty = Order::factory()->create();

    $a = oldPhoto($multi);
    $b = oldPhoto($empty);

    $this->migration->up();

    expect($a->fresh()->model_type)->toBe(Order::class)
        ->and($b->fresh()->model_type)->toBe(Order::class);
});

it('changes nothing on a second run', function () {
    $item = OrderItem::factory()->create();
    oldPhoto($item->order);

    $this->migration->up();
    $before = DB::table('media')->orderBy('id')->get(['id', 'model_type', 'model_id', 'collection_name'])->all();

    $this->migration->up();

    expect(DB::table('media')->orderBy('id')->get(['id', 'model_type', 'model_id', 'collection_name'])->all())->toEqual($before);
});

it('moves item images back to the order on rollback', function () {
    $item = OrderItem::factory()->create();
    $photo = oldPhoto($item->order);

    $this->migration->up();
    $this->migration->down();

    expect($photo->fresh()->model_type)->toBe(Order::class)
        ->and($photo->fresh()->model_id)->toBe($item->order_id);
});
