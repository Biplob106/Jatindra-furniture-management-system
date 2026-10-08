<?php

use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Order-level photos are legacy: uploaded before images moved onto items.
 * They must stay visible and deletable, and nothing new may land there.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->seed(RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole(Role::Manager->value);

    $this->order = Order::factory()->confirmed()->create();
});

function legacyPhoto(Order $order, string $collection = 'photos'): Media
{
    return $order->addMedia(UploadedFile::fake()->image('old.jpg', 2400, 1800))->toMediaCollection($collection);
}

it('shows legacy order photos and designs on the order page', function () {
    legacyPhoto($this->order);
    legacyPhoto($this->order, 'designs');

    $this->actingAs($this->manager)
        ->get("/orders/{$this->order->id}")
        ->assertInertia(fn ($page) => $page
            ->has('legacyPhotos', 2)
            ->where('legacyPhotos.0.collection', 'photos')
            ->where('legacyPhotos.1.collection', 'designs')
        );
});

it('deletes a legacy photo', function () {
    $media = legacyPhoto($this->order);

    $this->actingAs($this->manager)
        ->delete("/orders/{$this->order->id}/photos/{$media->id}")
        ->assertRedirect();

    expect(Media::count())->toBe(0);
});

it('will not delete a photo belonging to another order', function () {
    $media = legacyPhoto(Order::factory()->confirmed()->create());

    $this->actingAs($this->manager)
        ->delete("/orders/{$this->order->id}/photos/{$media->id}")
        ->assertNotFound();

    expect(Media::count())->toBe(1);
});

it('keeps an accountant from deleting', function () {
    $media = legacyPhoto($this->order);
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)
        ->delete("/orders/{$this->order->id}/photos/{$media->id}")
        ->assertForbidden();

    expect(Media::count())->toBe(1);
});

it('lets a storekeeper see legacy photos without a delete control', function () {
    legacyPhoto($this->order);
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)
        ->get("/orders/{$this->order->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('legacyPhotos', 1)->where('canManage', false));
});
