<?php

use App\Models\OrderItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('public');
});

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
