<?php

use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('public');

    $this->seed(RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole(Role::Manager->value);

    $this->order = Order::factory()->confirmed()->create();
});

it('stores a photo against the order', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/photos", [
            'photos' => [UploadedFile::fake()->image('khat.jpg', 2400, 1800)],
        ])
        ->assertRedirect();

    expect($this->order->getMedia('photos'))->toHaveCount(1)
        ->and(Media::sole()->model_id)->toBe($this->order->id);
});

it('stores several photos at once', function () {
    $this->actingAs($this->manager)->post("/orders/{$this->order->id}/photos", [
        'photos' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
            UploadedFile::fake()->image('three.jpg'),
        ],
    ]);

    expect($this->order->getMedia('photos'))->toHaveCount(3);
});

it('keeps design drawings in their own collection', function () {
    $this->actingAs($this->manager)->post("/orders/{$this->order->id}/photos", [
        'photos' => [UploadedFile::fake()->image('naksha.jpg')],
        'collection' => 'designs',
    ]);

    expect($this->order->getMedia('designs'))->toHaveCount(1)
        ->and($this->order->getMedia('photos'))->toHaveCount(0);
});

/**
 * The rollout checklist asks for server-side compression with thumbnails. A
 * phone photo straight off the camera is several megabytes, and the shop is on
 * a slow connection.
 */
it('generates the thumbnail and web conversions', function () {
    $this->actingAs($this->manager)->post("/orders/{$this->order->id}/photos", [
        'photos' => [UploadedFile::fake()->image('khat.jpg', 2400, 1800)],
    ]);

    $media = Media::sole();

    expect($media->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($media->hasGeneratedConversion('web'))->toBeTrue();
});

it('never exceeds 1600px on the stored web copy', function () {
    $this->actingAs($this->manager)->post("/orders/{$this->order->id}/photos", [
        'photos' => [UploadedFile::fake()->image('big.jpg', 3000, 2000)],
    ]);

    $path = Media::sole()->getPath('web');

    [$width, $height] = getimagesize($path);

    expect(max($width, $height))->toBeLessThanOrEqual(1600);
});

it('serves the web copy rather than the original', function () {
    $this->actingAs($this->manager)->post("/orders/{$this->order->id}/photos", [
        'photos' => [UploadedFile::fake()->image('khat.jpg', 2400, 1800)],
    ]);

    $this->actingAs($this->manager)
        ->get("/orders/{$this->order->id}")
        ->assertInertia(fn ($page) => $page
            ->has('order.photos', 1)
            ->where('order.photos.0.collection', 'photos')
        );

    $photos = $this->actingAs($this->manager)
        ->get("/orders/{$this->order->id}")
        ->viewData('page')['props']['order']['photos'];

    expect($photos[0]['url'])->toContain('web')
        ->and($photos[0]['thumb'])->toContain('thumb');
});

it('records who uploaded it', function () {
    $this->actingAs($this->manager)->post("/orders/{$this->order->id}/photos", [
        'photos' => [UploadedFile::fake()->image('khat.jpg')],
    ]);

    expect(Media::sole()->getCustomProperty('uploaded_by'))->toBe($this->manager->id);
});

it('refuses a file that is not an image', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/photos", [
            'photos' => [UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')],
        ])
        ->assertSessionHasErrors('photos.0');

    expect(Media::count())->toBe(0);
});

it('refuses a photo over ten megabytes', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/photos", [
            'photos' => [UploadedFile::fake()->image('huge.jpg')->size(11000)],
        ])
        ->assertSessionHasErrors('photos.0');

    expect(Media::count())->toBe(0);
});

it('refuses more than ten at once', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/photos", [
            'photos' => array_map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, 11)),
        ])
        ->assertSessionHasErrors('photos');

    expect(Media::count())->toBe(0);
});

it('refuses an unknown collection', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/photos", [
            'photos' => [UploadedFile::fake()->image('khat.jpg')],
            'collection' => 'receipts',
        ])
        ->assertSessionHasErrors('collection');
});

it('deletes a photo', function () {
    $this->actingAs($this->manager)->post("/orders/{$this->order->id}/photos", [
        'photos' => [UploadedFile::fake()->image('khat.jpg')],
    ]);

    $media = Media::sole();

    $this->actingAs($this->manager)
        ->delete("/orders/{$this->order->id}/photos/{$media->id}")
        ->assertRedirect();

    expect(Media::count())->toBe(0);
});

/**
 * A media id guessed from another order must not be reachable through this
 * order's URL.
 */
it('will not delete a photo belonging to another order', function () {
    $other = Order::factory()->confirmed()->create();

    $this->actingAs($this->manager)->post("/orders/{$other->id}/photos", [
        'photos' => [UploadedFile::fake()->image('khat.jpg')],
    ]);

    $media = Media::sole();

    $this->actingAs($this->manager)
        ->delete("/orders/{$this->order->id}/photos/{$media->id}")
        ->assertNotFound();

    expect(Media::count())->toBe(1);
});

it('keeps an accountant from uploading', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)
        ->post("/orders/{$this->order->id}/photos", [
            'photos' => [UploadedFile::fake()->image('khat.jpg')],
        ])
        ->assertForbidden();

    expect(Media::count())->toBe(0);
});

it('lets a storekeeper see the photos without a delete control', function () {
    $this->actingAs($this->manager)->post("/orders/{$this->order->id}/photos", [
        'photos' => [UploadedFile::fake()->image('khat.jpg')],
    ]);

    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)
        ->get("/orders/{$this->order->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('order.photos', 1)->where('canManage', false));
});
