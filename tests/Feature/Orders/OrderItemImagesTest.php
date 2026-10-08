<?php

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('public');

    $this->seed(RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole(Role::Manager->value);

    $this->customer = Customer::factory()->create();
    $this->shop = Shop::factory()->create();
});

function imagesPayload(array $items): array
{
    return [
        'customer_id' => test()->customer->id,
        'shop_id' => test()->shop->id,
        'order_date' => '2026-10-08',
        'items' => $items,
    ];
}

function imageItem(array $overrides = []): array
{
    return ['item_name' => 'সেগুন খাট', 'quantity' => '1', 'unit_price' => '45000', ...$overrides];
}

it('stores product photos and design images on the item, apart', function () {
    $item = OrderItem::factory()->create();

    $item->addMedia(UploadedFile::fake()->image('khat.jpg'))->toMediaCollection('photos');
    $item->addMedia(UploadedFile::fake()->image('naksha.jpg'))->toMediaCollection('designs');

    expect($item->getMedia('photos'))->toHaveCount(1)
        ->and($item->getMedia('designs'))->toHaveCount(1)
        ->and(Media::where('model_type', OrderItem::class)->count())->toBe(2);
});

it('generates thumb and web conversions for item images, web at most 1600px', function () {
    $item = OrderItem::factory()->create();

    $media = $item->addMedia(UploadedFile::fake()->image('big.jpg', 3000, 2000))->toMediaCollection('photos');

    [$width, $height] = getimagesize($media->getPath('web'));

    expect($media->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($media->hasGeneratedConversion('web'))->toBeTrue()
        ->and(max($width, $height))->toBeLessThanOrEqual(1600);
});

it('refuses a non-image file in an item collection', function () {
    $item = OrderItem::factory()->create();

    expect(fn () => $item->addMedia(UploadedFile::fake()->create('slip.pdf', 10, 'application/pdf'))
        ->toMediaCollection('photos'))->toThrow(FileUnacceptableForCollection::class);
});

it('refuses a non-image under an item', function () {
    $this->actingAs($this->manager)
        ->post('/orders', imagesPayload([imageItem([
            'new_photos' => [UploadedFile::fake()->create('slip.pdf', 10, 'application/pdf')],
        ])]))
        ->assertSessionHasErrors('items.0.new_photos.0');

    expect(Order::count())->toBe(0)->and(Media::count())->toBe(0);
});

it('refuses an eleventh image in one zone', function () {
    $this->actingAs($this->manager)
        ->post('/orders', imagesPayload([imageItem([
            'new_designs' => array_map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, 11)),
        ])]))
        ->assertSessionHasErrors('items.0.new_designs');

    expect(Order::count())->toBe(0)->and(Media::count())->toBe(0);
});

it('refuses an image over ten megabytes', function () {
    $this->actingAs($this->manager)
        ->post('/orders', imagesPayload([imageItem([
            'new_photos' => [UploadedFile::fake()->image('huge.jpg')->size(11000)],
        ])]))
        ->assertSessionHasErrors('items.0.new_photos.0');
});

it('accepts preset and custom wood and polish values', function () {
    $this->actingAs($this->manager)
        ->post('/orders', imagesPayload([
            imageItem(['wood_type' => 'সেগুন', 'polish_type' => 'লেকার']),
            imageItem(['item_name' => 'আলমারি', 'wood_type' => 'পুরনো কাঠ', 'polish_type' => 'হালকা বাদামি']),
        ]))
        ->assertSessionHasNoErrors();

    expect(OrderItem::pluck('wood_type')->all())->toBe(['সেগুন', 'পুরনো কাঠ']);
});
