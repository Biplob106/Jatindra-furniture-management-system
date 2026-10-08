# Order Item Images Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every order item its own product photos and নকশা/design images, uploaded from the order form, and cut typing on that form with chips, toggles and presets.

**Architecture:** `OrderItem` becomes a medialibrary owner with `photos` and `designs` collections (no schema change). Files travel with the order save as multipart under each item's index; `SaveOrder` attaches them inside its transaction and deletes removed media only after commit. The React form is split into an item card, an image picker and a chip control; images are compressed to 1600px in the browser before upload.

**Tech Stack:** Laravel 12, spatie/laravel-medialibrary 11, Pest, Inertia 2, React 19, TypeScript, Tailwind 4, shadcn/ui.

**Spec:** `docs/superpowers/specs/2026-10-08-order-item-images-design.md`

## Global Constraints

- All user-facing strings Bengali; code, comments, names and commit messages English.
- Validation lives in FormRequests; React only renders server errors (client-side checks are convenience, never the authority).
- No new tables or columns. `media` is the only file store.
- Controllers stay thin; all writes go through `SaveOrder::handle()` inside one `DB::transaction()`.
- Money is never taken from the form; `line_total`/`subtotal`/`total_amount` stay computed in `SaveOrder`.
- Image rules: jpg/jpeg/png/webp, max 10240 KB each, max 10 new files per collection per item per save.
- Conversions: `thumb` 400px q70, `web` 1600px q75, both `nonQueued()`.
- Wood presets: সেগুন, মেহগনি, চাম্বল, গর্জন, কড়ই, শাল, বোর্ড. Polish presets: বার্নিশ, লেকার, পিইউ, ডুকো, রং, পলিশ ছাড়া.
- Shop-floor page: 48px tap targets (`h-12`), sticky bottom save bar kept.
- Tests are Pest, run against MySQL test DB `jatindra_furniture_test`; use `Storage::fake('public')` and `UploadedFile::fake()->image()`.
- Run PHP tests with `php artisan test <path>`. The `PDO::MYSQL_ATTR_SSL_CA` deprecation notices are pre-existing and not failures.
- Commits end with the line `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **A save that fails after an image was marked for removal** — the person expects the image to still be there. Pinned in Task 3 (`keeps a removed image when the save fails`).
2. **Removing an item from the middle of the list in the browser** — the photos of the items below must stay with their items, not shift up by one. Pinned in Task 6 by keying cards on `client_key` and the manual check in Task 8 step 3.
3. **A media id from another item or another order posted in `remove_media`** (stale tab, crafted request) — nothing is deleted and the save is refused on that card. Pinned in Task 3.
4. **Editing an order whose old order-level photos were not migrated (several items)** — those photos stay visible and deletable on the order page and never appear inside an item. Pinned in Task 4 (`legacyPhotos`) and Task 5.
5. **Large phone photos over slow mobile data** — upload must not hit PHP's 2M/8M limits for normal use. Pinned in Task 6 (`compressImage` to ≤1600px JPEG) and Task 8 (README PHP minimums).

---

## File map

| File | Responsibility |
|---|---|
| `app/Models/Concerns/HasOrderImages.php` (new) | `photos`/`designs` collections + `thumb`/`web` conversions, shared by Order and OrderItem |
| `app/Models/Order.php` | use the trait; drop its own media methods |
| `app/Models/OrderItem.php` | implement `HasMedia`, use the trait |
| `app/Support/OrderItemPresets.php` (new) | wood/polish preset lists |
| `app/Http/Requests/Orders/OrderRequest.php` | per-item image rules + messages |
| `app/Actions/Orders/SaveOrder.php` | attach/remove item media; deferred deletes |
| `app/Http/Controllers/OrderController.php` | per-item images in show/edit, `legacyPhotos`, presets props |
| `app/Http/Controllers/OrderPhotoController.php` | `store` removed |
| `app/Http/Requests/Orders/OrderPhotoRequest.php` | deleted |
| `routes/web.php` | `orders.photos.store` removed |
| `database/migrations/2026_10_08_000001_move_order_media_to_single_items.php` (new) | move legacy media to the sole item |
| `resources/js/lib/compress-image.ts` (new) | canvas downscale |
| `resources/js/components/image-picker.tsx` (new) | one image zone + `ImageViewer` |
| `resources/js/components/choice-chips.tsx` (new) | chip select with অন্যান্য |
| `resources/js/pages/orders/order-item-card.tsx` (new) | one item card |
| `resources/js/pages/orders/create.tsx` | uses the card; multipart submit |
| `resources/js/pages/orders/show.tsx` | per-item galleries; legacy section |
| `resources/js/pages/orders/order-photos.tsx` | read-only legacy gallery |
| `README.md` | PHP upload minimums |
| Tests: `tests/Feature/Orders/OrderItemImagesTest.php` (new), `tests/Feature/Actions/SaveOrderTest.php`, `tests/Feature/Orders/OrderPhotoTest.php` (rewritten), `tests/Feature/Orders/OrderScreenTest.php`, `tests/Feature/Orders/MoveOrderMediaMigrationTest.php` (new) |

---

### Task 1: Item media collections via a shared trait

**Files:**
- Create: `app/Models/Concerns/HasOrderImages.php`
- Modify: `app/Models/Order.php` (replace `InteractsWithMedia` use and the two `register*` methods)
- Modify: `app/Models/OrderItem.php`
- Test: `tests/Feature/Orders/OrderItemImagesTest.php`

**Interfaces:**
- Produces: `App\Models\Concerns\HasOrderImages` (trait; includes `InteractsWithMedia`). `OrderItem implements HasMedia` with collections `photos`, `designs` and conversions `thumb`, `web`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Orders/OrderItemImagesTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Orders/OrderItemImagesTest.php`
Expected: FAIL — `Call to undefined method App\Models\OrderItem::addMedia()`.

- [ ] **Step 3: Create the trait**

`app/Models/Concerns/HasOrderImages.php`:

```php
<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Photos of the piece and design drawings, for orders and their items.
 *
 * Order still carries these for images uploaded before they moved onto items.
 * One trait so the two owners can never disagree on what is accepted or how it
 * is compressed.
 */
trait HasOrderImages
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('designs')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * The compression the rollout checklist asks for: nothing over 1600px, and
     * a thumbnail for the grid.
     *
     * Non-queued on purpose. A shop floor PC has no queue worker running, and
     * a photo that only appears once someone remembers to start one is worse
     * than a save that takes an extra second.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 400, 400)
            ->quality(70)
            ->nonQueued();

        $this->addMediaConversion('web')
            ->fit(Fit::Contain, 1600, 1600)
            ->quality(75)
            ->nonQueued();
    }
}
```

- [ ] **Step 4: Use it in Order**

In `app/Models/Order.php`:
- Replace the imports `use Spatie\Image\Enums\Fit;`, `use Spatie\MediaLibrary\InteractsWithMedia;`, `use Spatie\MediaLibrary\MediaCollections\Models\Media;` with `use App\Models\Concerns\HasOrderImages;` (keep `use Spatie\MediaLibrary\HasMedia;`).
- Change `use HasFactory, InteractsWithMedia;` to `use HasFactory, HasOrderImages;`.
- Delete the `registerMediaCollections()` and `registerMediaConversions()` methods and their docblocks (from `/**\n     * Photos of the piece and design drawings.` to the end of `registerMediaConversions`).

- [ ] **Step 5: Use it in OrderItem**

In `app/Models/OrderItem.php` add imports `use App\Models\Concerns\HasOrderImages;` and `use Spatie\MediaLibrary\HasMedia;`, then:

```php
/**
 * One piece of furniture on an order. Its photos and design drawings live
 * here, not on the order, so two items' images can never be confused.
 */
class OrderItem extends Model implements HasMedia
{
    use HasFactory, HasOrderImages;
```

- [ ] **Step 6: Run tests**

Run: `php artisan test tests/Feature/Orders/OrderItemImagesTest.php tests/Feature/Orders/OrderPhotoTest.php`
Expected: PASS (OrderPhotoTest proves Order's collections/conversions still work).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Concerns/HasOrderImages.php app/Models/Order.php app/Models/OrderItem.php tests/Feature/Orders/OrderItemImagesTest.php
git commit -m "feat: let order items own photos and design images

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Request rules for item images

**Files:**
- Modify: `app/Http/Requests/Orders/OrderRequest.php`
- Test: `tests/Feature/Orders/OrderItemImagesTest.php` (append)

**Interfaces:**
- Produces: validated item rows may contain `new_photos: UploadedFile[]`, `new_designs: UploadedFile[]`, `remove_media: int[]`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Orders/OrderItemImagesTest.php` (add imports at top: `use App\Enums\Role; use App\Models\Customer; use App\Models\Order; use App\Models\Shop; use App\Models\User; use Database\Seeders\RolePermissionSeeder;`), and extend `beforeEach`:

```php
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
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test tests/Feature/Orders/OrderItemImagesTest.php`
Expected: the three refusal tests FAIL (no errors in session; the order is created).

- [ ] **Step 3: Add the rules and messages**

In `OrderRequest::rules()`, after `'items.*.remarks' => ...`:

```php
            // Images for this item. Keyed under the item so a photo can only
            // ever arrive attached to the row it was picked in.
            'items.*.new_photos' => ['nullable', 'array', 'max:10'],
            'items.*.new_photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'items.*.new_designs' => ['nullable', 'array', 'max:10'],
            'items.*.new_designs.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            // Ownership is checked in SaveOrder against the item itself.
            'items.*.remove_media' => ['nullable', 'array'],
            'items.*.remove_media.*' => ['integer'],
```

In `messages()` add:

```php
            'items.*.new_photos.max' => 'একটি পণ্যে একবারে সর্বোচ্চ ১০টি ছবি দেওয়া যাবে।',
            'items.*.new_designs.max' => 'একটি পণ্যে একবারে সর্বোচ্চ ১০টি নকশা দেওয়া যাবে।',
            'items.*.new_photos.*.image' => 'শুধু ছবি দেওয়া যাবে।',
            'items.*.new_designs.*.image' => 'শুধু ছবি দেওয়া যাবে।',
            'items.*.new_photos.*.mimes' => 'ছবি jpg, png বা webp হতে হবে।',
            'items.*.new_designs.*.mimes' => 'ছবি jpg, png বা webp হতে হবে।',
            'items.*.new_photos.*.max' => 'প্রতিটি ছবি সর্বোচ্চ ১০ এমবি হতে পারে।',
            'items.*.new_designs.*.max' => 'প্রতিটি ছবি সর্বোচ্চ ১০ এমবি হতে পারে।',
```

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Feature/Orders/OrderItemImagesTest.php`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Orders/OrderRequest.php tests/Feature/Orders/OrderItemImagesTest.php
git commit -m "feat: validate per-item order images

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: SaveOrder attaches and removes item images

**Files:**
- Modify: `app/Actions/Orders/SaveOrder.php`
- Test: `tests/Feature/Actions/SaveOrderTest.php` (append)

**Interfaces:**
- Consumes: item rows with `new_photos`, `new_designs`, `remove_media` (Task 2); `OrderItem` media (Task 1).
- Produces: `SaveOrder::handle(array $data, array $items, ?Order $order = null, ?int $userId = null): Order` — signature unchanged. Throws `Illuminate\Validation\ValidationException` keyed `items.<index>.remove_media` when a media id is not owned by that item.

- [ ] **Step 1: Write the failing tests**

In `tests/Feature/Actions/SaveOrderTest.php` add imports `use Illuminate\Http\UploadedFile; use Illuminate\Support\Facades\Storage; use Illuminate\Validation\ValidationException; use Spatie\MediaLibrary\MediaCollections\Models\Media;`, add `Storage::fake('public');` as the first line of `beforeEach`, then append:

```php
it('attaches each item its own photos and designs, none to the order', function () {
    $order = $this->action->handle(orderData(), [
        itemRow(['new_photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]]),
        itemRow(['item_name' => 'আলমারি', 'new_designs' => [UploadedFile::fake()->image('naksha.jpg')]]),
    ], userId: 7);

    [$khat, $almari] = $order->items()->orderBy('id')->get()->all();

    expect(Media::count())->toBe(3)
        ->and($khat->getMedia('photos'))->toHaveCount(2)
        ->and($khat->getMedia('designs'))->toHaveCount(0)
        ->and($almari->getMedia('photos'))->toHaveCount(0)
        ->and($almari->getMedia('designs'))->toHaveCount(1)
        ->and($order->getMedia('photos'))->toHaveCount(0)
        ->and(Media::first()->getCustomProperty('uploaded_by'))->toBe(7);
});

it('removes only the named image of that item', function () {
    $order = $this->action->handle(orderData(), [
        itemRow(['new_photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]]),
    ]);
    $item = $order->items()->sole();
    [$keep, $drop] = $item->getMedia('photos')->all();

    $this->action->handle(orderData(), [
        itemRow(['id' => $item->id, 'remove_media' => [$drop->id]]),
    ], $order);

    expect(Media::pluck('id')->all())->toBe([$keep->id])
        ->and(file_exists($drop->getPath()))->toBeFalse();
});

it('refuses to remove another item\'s image and changes nothing', function () {
    $order = $this->action->handle(orderData(), [
        itemRow(['new_photos' => [UploadedFile::fake()->image('a.jpg')]]),
        itemRow(['item_name' => 'আলমারি']),
    ]);
    [$khat, $almari] = $order->items()->orderBy('id')->get()->all();
    $photo = $khat->getMedia('photos')->sole();

    try {
        $this->action->handle(orderData(), [
            itemRow(['id' => $khat->id, 'unit_price' => '1']),
            itemRow(['id' => $almari->id, 'item_name' => 'আলমারি', 'remove_media' => [$photo->id]]),
        ], $order);
        $this->fail('Expected a validation exception.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('items.1.remove_media');
    }

    expect(Media::count())->toBe(1)
        ->and(file_exists($photo->getPath()))->toBeTrue()
        ->and($khat->fresh()->unit_price)->toBe('25000.00');
});

it('refuses to remove an order-level legacy image through an item', function () {
    $order = $this->action->handle(orderData(), [itemRow()]);
    $legacy = $order->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('photos');

    expect(fn () => $this->action->handle(orderData(), [
        itemRow(['id' => $order->items()->sole()->id, 'remove_media' => [$legacy->id]]),
    ], $order))->toThrow(ValidationException::class);

    expect(Media::count())->toBe(1);
});

it('keeps a removed image when the save fails', function () {
    $order = $this->action->handle(orderData(), [
        itemRow(['new_photos' => [UploadedFile::fake()->image('a.jpg')]]),
    ]);
    $item = $order->items()->sole();
    $photo = $item->getMedia('photos')->sole();

    expect(fn () => $this->action->handle(orderData(), [
        itemRow(['id' => $item->id, 'remove_media' => [$photo->id]]),
        itemRow(['item_name' => str_repeat('ক', 300)]),
    ], $order))->toThrow(QueryException::class);

    expect(Media::count())->toBe(1)
        ->and(file_exists($photo->getPath()))->toBeTrue();
});

it('persists no media row when the save fails part way', function () {
    expect(fn () => $this->action->handle(orderData(), [
        itemRow(['new_photos' => [UploadedFile::fake()->image('a.jpg')]]),
        itemRow(['item_name' => str_repeat('ক', 300)]),
    ]))->toThrow(QueryException::class);

    expect(Order::count())->toBe(0)
        ->and(OrderItem::count())->toBe(0)
        ->and(Media::count())->toBe(0);
});

it('deletes a dropped item\'s images with it', function () {
    $order = $this->action->handle(orderData(), [
        itemRow(),
        itemRow(['item_name' => 'আলমারি', 'new_photos' => [UploadedFile::fake()->image('a.jpg')]]),
    ]);
    $kept = $order->items()->orderBy('id')->first();

    $this->action->handle(orderData(), [itemRow(['id' => $kept->id])], $order);

    expect(OrderItem::count())->toBe(1)->and(Media::count())->toBe(0);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test tests/Feature/Actions/SaveOrderTest.php`
Expected: new tests FAIL (no media attached / nothing thrown).

- [ ] **Step 3: Implement**

In `app/Actions/Orders/SaveOrder.php` add imports `use Illuminate\Validation\ValidationException;` and `use Spatie\MediaLibrary\MediaCollections\Models\Media;`. Update the class docblock's first line to `Creates or edits an order, its items, and the items' images.` Then:

```php
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

    public function handle(array $data, array $items, ?Order $order = null, ?int $userId = null): Order
    {
        $this->mediaToDelete = [];

        $order = DB::transaction(function () use ($data, $items, $order, $userId) {
            // ... existing body unchanged, except:
            $this->syncItems($order, $items, $userId);
            // ...
        });

        foreach ($this->mediaToDelete as $media) {
            $media->delete();
        }

        return $order;
    }
```

Keep the existing `@param` docblock on `handle`. `syncItems` becomes:

```php
    private function syncItems(Order $order, array $items, ?int $userId): void
    {
        $keptIds = [];

        foreach ($items as $index => $row) {
            // ... existing $attributes, $item lookup, fill, save unchanged ...

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
```

In `removeDroppedItems`, replace `$item->delete();` with:

```php
            // The row goes now; its images go after commit, with the rest.
            array_push($this->mediaToDelete, ...$item->media()->get()->all());
            $item->deletePreservingMedia();
```

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Feature/Actions/SaveOrderTest.php`
Expected: all PASS, including the pre-existing tests.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Orders/SaveOrder.php tests/Feature/Actions/SaveOrderTest.php
git commit -m "feat: save order item images with the order

Removals are deferred until after commit so a failed save never loses a
file, and an item may only remove media it owns.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Controller props, presets, and retiring order-level upload

**Files:**
- Create: `app/Support/OrderItemPresets.php`
- Modify: `app/Http/Controllers/OrderController.php` (`show`, `edit`, `formProps`)
- Modify: `app/Http/Controllers/OrderPhotoController.php` (remove `store`)
- Delete: `app/Http/Requests/Orders/OrderPhotoRequest.php`
- Modify: `routes/web.php:66` (remove `orders.photos.store`)
- Test: `tests/Feature/Orders/OrderItemImagesTest.php` (append), `tests/Feature/Orders/OrderPhotoTest.php` (rewrite)

**Interfaces:**
- Produces (Inertia props):
  - `orders/create` and edit: `woodTypes: string[]`, `polishTypes: string[]`; edit `order.items[n].photos` and `.designs`: `OrderImage[]`.
  - `orders/show`: `order.items[n].photos`, `order.items[n].designs`: `OrderImage[]`; top-level `legacyPhotos: {id, collection: 'photos'|'designs', name, url, thumb}[]`. `order.photos` is removed.
  - `OrderImage` = `{ id: number, name: string, url: string, thumb: string }`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Orders/OrderItemImagesTest.php` (add `use Illuminate\Support\Facades\Route;`):

```php
it('creates an order whose items carry their own images over HTTP', function () {
    $this->actingAs($this->manager)
        ->post('/orders', imagesPayload([
            imageItem(['new_photos' => [UploadedFile::fake()->image('a.jpg')]]),
            imageItem(['item_name' => 'আলমারি', 'new_designs' => [UploadedFile::fake()->image('n.jpg')]]),
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $order = Order::sole();

    $this->actingAs($this->manager)
        ->get("/orders/{$order->id}")
        ->assertInertia(fn ($page) => $page
            ->has('order.items.0.photos', 1)
            ->has('order.items.0.designs', 0)
            ->has('order.items.1.photos', 0)
            ->has('order.items.1.designs', 1)
            ->has('legacyPhotos', 0)
            ->missing('order.photos')
        );
});

it('serves item images as web copies with thumbnails', function () {
    $item = OrderItem::factory()->create();
    $item->addMedia(UploadedFile::fake()->image('khat.jpg', 2400, 1800))->toMediaCollection('photos');

    $photo = $this->actingAs($this->manager)
        ->get("/orders/{$item->order_id}")
        ->viewData('page')['props']['order']['items'][0]['photos'][0];

    expect($photo['url'])->toContain('web')->and($photo['thumb'])->toContain('thumb');
});

it('records the uploader on images saved through the form', function () {
    $this->actingAs($this->manager)->post('/orders', imagesPayload([
        imageItem(['new_photos' => [UploadedFile::fake()->image('a.jpg')]]),
    ]));

    expect(Media::sole()->getCustomProperty('uploaded_by'))->toBe($this->manager->id);
});

it('sends existing item images and the presets to the edit form', function () {
    $item = OrderItem::factory()->create();
    $item->addMedia(UploadedFile::fake()->image('khat.jpg'))->toMediaCollection('designs');

    $this->actingAs($this->manager)
        ->get("/orders/{$item->order_id}/edit")
        ->assertInertia(fn ($page) => $page
            ->has('order.items.0.designs', 1)
            ->has('order.items.0.photos', 0)
            ->where('woodTypes.0', 'সেগুন')
            ->where('polishTypes.0', 'বার্নিশ')
        );
});

it('removes an item image through the edit form', function () {
    $item = OrderItem::factory()->create();
    $media = $item->addMedia(UploadedFile::fake()->image('khat.jpg'))->toMediaCollection('photos');

    $this->actingAs($this->manager)
        ->put("/orders/{$item->order_id}", imagesPayload([
            imageItem(['id' => $item->id, 'remove_media' => [$media->id]]),
        ]))
        ->assertSessionHasNoErrors();

    expect(Media::count())->toBe(0);
});

it('no longer has an order-level upload route', function () {
    expect(Route::has('orders.photos.store'))->toBeFalse()
        ->and(Route::has('orders.photos.destroy'))->toBeTrue();
});
```

Note: `imagesPayload` sets `customer_id`/`shop_id` from `beforeEach`; the factory order has its own customer/shop, which update simply overwrites — acceptable for these tests.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test tests/Feature/Orders/OrderItemImagesTest.php`
Expected: new tests FAIL (missing props / route still exists).

- [ ] **Step 3: Presets class**

`app/Support/OrderItemPresets.php`:

```php
<?php

namespace App\Support;

/**
 * The choices offered as chips on the order form.
 *
 * Plain strings, not enums: wood_type and polish_type are free text in the
 * schema and the form also accepts an "অন্যান্য" value typed by hand. The
 * stored value is the Bengali word itself, so old orders read the same.
 */
final class OrderItemPresets
{
    /** @var list<string> */
    public const WOOD_TYPES = ['সেগুন', 'মেহগনি', 'চাম্বল', 'গর্জন', 'কড়ই', 'শাল', 'বোর্ড'];

    /** @var list<string> */
    public const POLISH_TYPES = ['বার্নিশ', 'লেকার', 'পিইউ', 'ডুকো', 'রং', 'পলিশ ছাড়া'];
}
```

- [ ] **Step 4: Controller changes**

In `OrderController` add `use App\Support\OrderItemPresets;` and `use Illuminate\Support\Collection;`. Add the helper at the bottom of the class:

```php
    /**
     * The web conversion, not the original: a phone photo straight off the
     * camera is several megabytes.
     *
     * @return list<array{id: int, name: string, url: string, thumb: string}>
     */
    private function images(Collection $media): array
    {
        return $media->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->file_name,
            'url' => $item->getUrl('web'),
            'thumb' => $item->getUrl('thumb'),
        ])->values()->all();
    }
```

In `show()`:
- In `$order->load([...])` add `'items.media',` after `'items.works.employee:id,name,wage_type',`.
- In each item map add after `'remarks' => $item->remarks,`:
  ```php
                    'photos' => $this->images($item->getMedia('photos')),
                    'designs' => $this->images($item->getMedia('designs')),
  ```
- Delete the whole `'photos' => $order->getMedia('photos')...->all(),` entry inside `'order' => [...]`.
- In the top-level props array passed to `Inertia::render('orders/show', [...])` add:
  ```php
            // Order-level images from before they moved onto items. Only ever
            // shown and deleted now; nothing new lands here.
            'legacyPhotos' => $order->getMedia('photos')
                ->concat($order->getMedia('designs'))
                ->map(fn ($media) => [
                    'id' => $media->id,
                    'collection' => $media->collection_name,
                    'name' => $media->file_name,
                    'url' => $media->getUrl('web'),
                    'thumb' => $media->getUrl('thumb'),
                ])
                ->values()
                ->all(),
  ```

In `edit()`: change the item map to include, after `'remarks' => $item->remarks,`:

```php
                    'photos' => $this->images($item->getMedia('photos')),
                    'designs' => $this->images($item->getMedia('designs')),
```

and add `$order->load('items.media');` as the first line after the `isOpen()` guard.

In `formProps()` add before `'today'`:

```php
            'woodTypes' => OrderItemPresets::WOOD_TYPES,
            'polishTypes' => OrderItemPresets::POLISH_TYPES,
```

- [ ] **Step 5: Retire order-level upload**

- `routes/web.php`: delete the line `Route::post('orders/{order}/photos', [OrderPhotoController::class, 'store'])->name('orders.photos.store');`.
- `OrderPhotoController`: delete `store()` and the `use App\Http\Requests\Orders\OrderPhotoRequest;` import; change the class docblock to:
  ```php
  /**
   * Order-level photos from before images moved onto order items.
   *
   * New images are saved with the order form against each item. What is left
   * here only lets an old picture be deleted.
   */
  ```
- `git rm app/Http/Requests/Orders/OrderPhotoRequest.php`

- [ ] **Step 6: Rewrite OrderPhotoTest for legacy-only behaviour**

Replace `tests/Feature/Orders/OrderPhotoTest.php` with:

```php
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
```

- [ ] **Step 7: Run tests**

Run: `php artisan test tests/Feature/Orders`
Expected: all PASS. If any `OrderScreenTest` assertion referenced `order.photos`, change it to `legacyPhotos`.

- [ ] **Step 8: Commit**

```bash
git add -A app/Support/OrderItemPresets.php app/Http routes/web.php tests/Feature/Orders
git commit -m "feat: send per-item images and wood/polish presets to order pages

Order-level photo upload is retired; existing order-level photos remain
viewable and deletable as legacy.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Migrate legacy images onto single-item orders

**Files:**
- Create: `database/migrations/2026_10_08_000001_move_order_media_to_single_items.php`
- Test: `tests/Feature/Orders/MoveOrderMediaMigrationTest.php`

**Interfaces:**
- Consumes: `OrderItem` media collections (Task 1).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\UploadedFile;
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
    $before = Media::orderBy('id')->get(['id', 'model_type', 'model_id', 'collection_name'])->toArray();

    $this->migration->up();

    expect(Media::orderBy('id')->get(['id', 'model_type', 'model_id', 'collection_name'])->toArray())->toBe($before);
});

it('moves item images back to the order on rollback', function () {
    $item = OrderItem::factory()->create();
    $photo = oldPhoto($item->order);

    $this->migration->up();
    $this->migration->down();

    expect($photo->fresh()->model_type)->toBe(Order::class)
        ->and($photo->fresh()->model_id)->toBe($item->order_id);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test tests/Feature/Orders/MoveOrderMediaMigrationTest.php`
Expected: FAIL — migration file not found.

- [ ] **Step 3: Write the migration**

```php
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
```

- [ ] **Step 4: Run tests, then migrate the dev DB**

Run: `php artisan test tests/Feature/Orders/MoveOrderMediaMigrationTest.php`
Expected: PASS.

Run: `php artisan migrate`
Expected: `2026_10_08_000001_move_order_media_to_single_items ... DONE`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_10_08_000001_move_order_media_to_single_items.php tests/Feature/Orders/MoveOrderMediaMigrationTest.php
git commit -m "feat: move old order photos onto the item for single-item orders

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Frontend building blocks

**Files:**
- Create: `resources/js/lib/compress-image.ts`
- Create: `resources/js/components/image-picker.tsx`
- Create: `resources/js/components/choice-chips.tsx`

**Interfaces:**
- Produces:
  - `compressImage(file: File): Promise<File>`
  - `export interface OrderImage { id: number; name: string; url: string; thumb: string }`
  - `ImagePicker` props: `{ label: string; existing: OrderImage[]; removedIds?: number[]; newFiles?: File[]; onAdd?: (files: File[]) => void; onRemoveNew?: (index: number) => void; onRemoveExisting?: (id: number) => void; onRestore?: () => void; error?: string; max?: number }` — read-only when `onAdd` is absent.
  - `ImageViewer` props: `{ images: { url: string; name: string }[]; index: number; onClose: () => void }`
  - `useObjectUrls(files: File[]): string[]`
  - `ChoiceChips` props: `{ id: string; label: string; value: string; onChange: (value: string) => void; options: string[]; error?: string }`

There is no JS test runner in this repo; verification is `tsc`, `eslint`, and the browser check in Task 8.

- [ ] **Step 1: compress-image.ts**

```ts
/**
 * Shrinks a phone photo before it is uploaded.
 *
 * The server keeps a 1600px copy anyway, so sending more than that only burns
 * the shop's mobile data and trips PHP's upload limits. Anything that cannot
 * be decoded is returned untouched for the server to judge.
 */
const MAX_EDGE = 1600;
const QUALITY = 0.8;

export async function compressImage(file: File): Promise<File> {
    if (!file.type.startsWith('image/')) return file;

    let bitmap: ImageBitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        return file;
    }

    const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);

    const context = canvas.getContext('2d');
    if (!context) {
        bitmap.close();
        return file;
    }

    // JPEG has no transparency; a transparent PNG would otherwise turn black.
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY));
    if (!blob || blob.size >= file.size) return file;

    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
}
```

- [ ] **Step 2: image-picker.tsx**

```tsx
import { toBengaliDigits } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { compressImage } from '@/lib/compress-image';
import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, ImagePlus, LoaderCircle, X } from 'lucide-react';
import { ChangeEvent, useEffect, useMemo, useState } from 'react';

export interface OrderImage {
    id: number;
    name: string;
    url: string;
    thumb: string;
}

/** Object URLs for local previews, revoked when the files change or unmount. */
export function useObjectUrls(files: File[]): string[] {
    const urls = useMemo(() => files.map((file) => URL.createObjectURL(file)), [files]);

    useEffect(() => () => urls.forEach((url) => URL.revokeObjectURL(url)), [urls]);

    return urls;
}

interface ViewerProps {
    images: { url: string; name: string }[];
    index: number;
    onClose: () => void;
}

export function ImageViewer({ images, index: start, onClose }: ViewerProps) {
    const [index, setIndex] = useState(start);
    const image = images[index];
    const step = (delta: number) => setIndex((i) => (i + delta + images.length) % images.length);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
            if (e.key === 'ArrowRight') step(1);
            if (e.key === 'ArrowLeft') step(-1);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    if (!image) return null;

    return (
        <div role="dialog" aria-modal="true" className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" onClick={onClose}>
            <button type="button" onClick={onClose} className="absolute top-4 right-4 rounded-full bg-white/10 p-3 text-white" aria-label="বন্ধ করুন">
                <X className="h-5 w-5" />
            </button>
            {images.length > 1 && (
                <>
                    <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); step(-1); }}
                        className="absolute left-2 rounded-full bg-white/10 p-3 text-white"
                        aria-label="আগের ছবি"
                    >
                        <ChevronLeft className="h-6 w-6" />
                    </button>
                    <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); step(1); }}
                        className="absolute right-2 rounded-full bg-white/10 p-3 text-white"
                        aria-label="পরের ছবি"
                    >
                        <ChevronRight className="h-6 w-6" />
                    </button>
                </>
            )}
            <img src={image.url} alt={image.name} className="max-h-full max-w-full rounded-lg object-contain" onClick={(e) => e.stopPropagation()} />
        </div>
    );
}

interface Props {
    label: string;
    existing: OrderImage[];
    removedIds?: number[];
    newFiles?: File[];
    /** Absent means read-only. */
    onAdd?: (files: File[]) => void;
    onRemoveNew?: (index: number) => void;
    onRemoveExisting?: (id: number) => void;
    onRestore?: () => void;
    error?: string;
    max?: number;
}

/**
 * One image zone of an order item: product photos or design drawings.
 *
 * Picked files stay in the form until the order is saved, shown with a "নতুন"
 * badge. Removing a saved image only marks it; it goes when the order saves.
 */
export function ImagePicker({ label, existing, removedIds = [], newFiles = [], onAdd, onRemoveNew, onRemoveExisting, onRestore, error, max = 10 }: Props) {
    const [busy, setBusy] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);
    const [viewing, setViewing] = useState<number | null>(null);
    const previews = useObjectUrls(newFiles);

    const kept = existing.filter((image) => !removedIds.includes(image.id));
    const tiles = [
        ...kept.map((image) => ({ key: `e${image.id}`, thumb: image.thumb, url: image.url, name: image.name, isNew: false, remove: () => onRemoveExisting?.(image.id) })),
        ...newFiles.map((file, i) => ({ key: `n${i}-${file.name}`, thumb: previews[i], url: previews[i], name: file.name, isNew: true, remove: () => onRemoveNew?.(i) })),
    ];
    const editable = Boolean(onAdd);

    const pick = async (event: ChangeEvent<HTMLInputElement>) => {
        const picked = Array.from(event.target.files ?? []);
        event.target.value = '';
        if (picked.length === 0) return;

        const images = picked.filter((file) => file.type.startsWith('image/'));
        const room = max - tiles.length;
        setLocalError(
            images.length < picked.length
                ? 'শুধু ছবি দেওয়া যাবে।'
                : images.length > room
                  ? `সর্বোচ্চ ${toBengaliDigits(max)}টি ছবি দেওয়া যাবে।`
                  : null,
        );
        if (room <= 0) return;

        setBusy(true);
        try {
            onAdd?.(await Promise.all(images.slice(0, room).map(compressImage)));
        } finally {
            setBusy(false);
        }
    };

    if (!editable && tiles.length === 0) return null;

    return (
        <div className="flex flex-col gap-2">
            <p className="text-sm font-medium">
                {label} ({toBengaliDigits(tiles.length)})
            </p>

            <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                {tiles.map((tile, i) => (
                    <div key={tile.key} className="relative aspect-square overflow-hidden rounded-lg border">
                        <button type="button" onClick={() => setViewing(i)} className="h-full w-full">
                            <img src={tile.thumb} alt={tile.name} loading="lazy" className="h-full w-full object-cover" />
                        </button>
                        {tile.isNew && <span className="bg-primary text-primary-foreground absolute bottom-1 left-1 rounded px-1 text-[10px]">নতুন</span>}
                        {editable && (
                            <button
                                type="button"
                                onClick={tile.remove}
                                aria-label="ছবি সরান"
                                className="bg-background/90 absolute top-1 right-1 rounded-full p-1.5 shadow"
                            >
                                <X className="text-destructive h-4 w-4" />
                            </button>
                        )}
                    </div>
                ))}

                {editable && tiles.length < max && (
                    <Button type="button" variant="outline" className={cn('aspect-square h-auto flex-col gap-1 border-dashed p-1 text-xs')} disabled={busy} asChild>
                        <label>
                            {busy ? <LoaderCircle className="h-5 w-5 animate-spin" /> : <ImagePlus className="h-5 w-5" />}
                            যোগ করুন
                            <input type="file" accept="image/*" multiple className="hidden" onChange={pick} disabled={busy} />
                        </label>
                    </Button>
                )}
            </div>

            {editable && removedIds.some((id) => existing.some((image) => image.id === id)) && (
                <p className="text-muted-foreground text-sm">
                    {toBengaliDigits(existing.filter((image) => removedIds.includes(image.id)).length)}টি ছবি সংরক্ষণের সময় মুছে যাবে।{' '}
                    <button type="button" onClick={onRestore} className="text-foreground underline">
                        পূর্বাবস্থায় ফেরান
                    </button>
                </p>
            )}

            {(localError ?? error) && <p className="text-destructive text-sm">{localError ?? error}</p>}

            {viewing !== null && <ImageViewer images={tiles} index={viewing} onClose={() => setViewing(null)} />}
        </div>
    );
}
```

- [ ] **Step 3: choice-chips.tsx**

```tsx
import { Field } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { useState } from 'react';

const OTHER = 'অন্যান্য';

interface Props {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: string[];
    error?: string;
}

/**
 * Tap a word instead of typing it. "অন্যান্য" opens a box for anything not on
 * the list, and a stored value that is not on the list opens there too, so
 * older orders keep what was written.
 */
export function ChoiceChips({ id, label, value, onChange, options, error }: Props) {
    const [otherOpen, setOtherOpen] = useState(value !== '' && !options.includes(value));

    const chip = (text: string, active: boolean, onClick: () => void) => (
        <button
            key={text}
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'h-11 rounded-full border px-4 text-sm transition-colors',
                active ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
            )}
        >
            {text}
        </button>
    );

    return (
        <Field id={id} label={label} error={error}>
            <div className="flex flex-wrap gap-2">
                {options.map((option) =>
                    chip(option, !otherOpen && value === option, () => {
                        setOtherOpen(false);
                        onChange(value === option ? '' : option);
                    }),
                )}
                {chip(OTHER, otherOpen, () => {
                    setOtherOpen(!otherOpen);
                    onChange('');
                })}
            </div>
            {otherOpen && (
                <Input id={id} value={value} onChange={(e) => onChange(e.target.value)} placeholder="লিখুন" className="h-12 text-base" autoFocus />
            )}
        </Field>
    );
}
```

- [ ] **Step 4: Type-check and lint**

Run: `npx tsc --noEmit && npx eslint resources/js/lib/compress-image.ts resources/js/components/image-picker.tsx resources/js/components/choice-chips.tsx && npx prettier --write resources/js/lib/compress-image.ts resources/js/components/image-picker.tsx resources/js/components/choice-chips.tsx`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/compress-image.ts resources/js/components/image-picker.tsx resources/js/components/choice-chips.tsx
git commit -m "feat: add image picker, chip select and browser image compression

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Order form with item cards and multipart save

**Files:**
- Create: `resources/js/pages/orders/order-item-card.tsx`
- Modify: `resources/js/pages/orders/create.tsx` (replace item section, form types, submit)

**Interfaces:**
- Consumes: `ImagePicker`, `OrderImage`, `useObjectUrls` (Task 6), `ChoiceChips` (Task 6), props `woodTypes`, `polishTypes`, `order.items[n].photos|designs` (Task 4).
- Produces: `ItemForm` type and `emptyItem()` exported from `order-item-card.tsx`; default export `OrderItemCard`.

- [ ] **Step 1: order-item-card.tsx**

```tsx
import { toBengaliDigits } from '@/components/data-table';
import { ChoiceChips } from '@/components/choice-chips';
import { Field, Option, SelectField, TextField } from '@/components/form-field';
import { ImagePicker, type OrderImage, useObjectUrls } from '@/components/image-picker';
import { Money } from '@/components/money';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { ChevronDown, Minus, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface ItemForm {
    [key: string]: string | number | undefined | OrderImage[] | File[] | number[];
    /** Browser-only identity. Never sent; keeps images with their card. */
    client_key: string;
    id?: number;
    category_id: string;
    item_name: string;
    description: string;
    wood_type: string;
    design_no: string;
    length: string;
    width: string;
    height: string;
    dimension_unit: string;
    polish_type: string;
    quantity: string;
    unit_price: string;
    target_date: string;
    remarks: string;
    photos: OrderImage[];
    designs: OrderImage[];
    new_photos: File[];
    new_designs: File[];
    remove_media: number[];
}

export const emptyItem = (): ItemForm => ({
    client_key: crypto.randomUUID(),
    category_id: '',
    item_name: '',
    description: '',
    wood_type: '',
    design_no: '',
    length: '',
    width: '',
    height: '',
    dimension_unit: 'inch',
    polish_type: '',
    quantity: '1',
    unit_price: '',
    target_date: '',
    remarks: '',
    photos: [],
    designs: [],
    new_photos: [],
    new_designs: [],
    remove_media: [],
});

interface Props {
    index: number;
    item: ItemForm;
    open: boolean;
    onToggle: () => void;
    /** Functional update so async image compression never writes stale state. */
    onUpdate: (update: (item: ItemForm) => ItemForm) => void;
    onRemove?: () => void;
    /** Errors for this item only, keys relative to it (e.g. "item_name", "new_photos.0"). */
    errors: Record<string, string>;
    categories: Option[];
    dimensionUnits: Option[];
    woodTypes: string[];
    polishTypes: string[];
}

const firstError = (errors: Record<string, string>, prefix: string) =>
    Object.entries(errors).find(([key]) => key === prefix || key.startsWith(`${prefix}.`))?.[1];

export default function OrderItemCard({ index, item, open, onToggle, onUpdate, onRemove, errors, categories, dimensionUnits, woodTypes, polishTypes }: Props) {
    const [moreOpen, setMoreOpen] = useState(Boolean(item.description || item.design_no || item.remarks));
    const patch = (values: Partial<ItemForm>) => onUpdate((current) => ({ ...current, ...values }));
    const hasErrors = Object.keys(errors).length > 0;

    const lineTotal = Number(item.quantity || 0) * Number(item.unit_price || 0);
    const keptPhotos = item.photos.filter((photo) => !item.remove_media.includes(photo.id));
    const keptDesigns = item.designs.filter((design) => !item.remove_media.includes(design.id));
    // Memoised: a fresh slice every render would mint and revoke a URL each time.
    const coverFiles = useMemo(() => item.new_photos.slice(0, 1), [item.new_photos]);
    const [firstNewPreview] = useObjectUrls(coverFiles);
    const cover = keptPhotos[0]?.thumb ?? firstNewPreview;

    const stepQuantity = (delta: number) => patch({ quantity: String(Math.max(1, Number(item.quantity || 0) + delta)) });

    const pickCategory = (value: string) => {
        const label = categories.find((c) => String(c.value) === value)?.label ?? '';
        patch(item.item_name === '' && label ? { category_id: value, item_name: label } : { category_id: value });
    };

    return (
        <div className={cn('rounded-lg border', hasErrors && 'border-destructive')}>
            <button type="button" onClick={onToggle} className="flex w-full items-center gap-3 p-3 text-left">
                <div className="bg-muted h-12 w-12 shrink-0 overflow-hidden rounded-md">
                    {cover && <img src={cover} alt="" className="h-full w-full object-cover" />}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">
                        {toBengaliDigits(index + 1)}. {item.item_name || 'নতুন পণ্য'}
                    </p>
                    <p className="text-muted-foreground text-sm">
                        {toBengaliDigits(item.quantity || '0')} × ৳ {toBengaliDigits(item.unit_price || '0')}
                        {' · '}ছবি {toBengaliDigits(keptPhotos.length + item.new_photos.length)}
                        {' · '}নকশা {toBengaliDigits(keptDesigns.length + item.new_designs.length)}
                    </p>
                </div>
                <Money amount={lineTotal.toFixed(2)} className="font-medium" />
                <ChevronDown className={cn('h-4 w-4 shrink-0 transition-transform', open && 'rotate-180')} />
            </button>

            {open && (
                <div className="grid gap-4 border-t p-4 sm:grid-cols-2">
                    <SelectField
                        id={`category_${item.client_key}`}
                        label="ক্যাটাগরি"
                        value={item.category_id}
                        onChange={pickCategory}
                        options={categories}
                        emptyLabel="নির্ধারিত নয়"
                        error={errors.category_id}
                    />
                    <TextField
                        id={`item_name_${item.client_key}`}
                        label="পণ্যের নাম"
                        value={item.item_name}
                        onChange={(value) => patch({ item_name: value })}
                        error={errors.item_name}
                        required
                        placeholder="যেমন: সেগুন কাঠের খাট"
                    />

                    <Field id={`quantity_${item.client_key}`} label="পরিমাণ" required error={errors.quantity}>
                        <div className="flex items-center gap-2">
                            <Button type="button" variant="outline" className="h-12 w-12" onClick={() => stepQuantity(-1)} aria-label="কমান">
                                <Minus className="h-4 w-4" />
                            </Button>
                            <Input
                                id={`quantity_${item.client_key}`}
                                inputMode="decimal"
                                value={item.quantity}
                                onChange={(e) => patch({ quantity: e.target.value })}
                                className="h-12 text-center text-base"
                            />
                            <Button type="button" variant="outline" className="h-12 w-12" onClick={() => stepQuantity(1)} aria-label="বাড়ান">
                                <Plus className="h-4 w-4" />
                            </Button>
                        </div>
                    </Field>
                    <TextField
                        id={`unit_price_${item.client_key}`}
                        label="দর"
                        type="number"
                        numeric
                        value={item.unit_price}
                        onChange={(value) => patch({ unit_price: value })}
                        error={errors.unit_price}
                        required
                    />

                    <div className="sm:col-span-2">
                        <ChoiceChips id={`wood_${item.client_key}`} label="কাঠ" value={item.wood_type} onChange={(value) => patch({ wood_type: value })} options={woodTypes} error={errors.wood_type} />
                    </div>
                    <div className="sm:col-span-2">
                        <ChoiceChips id={`polish_${item.client_key}`} label="পলিশ" value={item.polish_type} onChange={(value) => patch({ polish_type: value })} options={polishTypes} error={errors.polish_type} />
                    </div>

                    <div className="grid gap-2 sm:col-span-2">
                        <div className="grid grid-cols-3 gap-2">
                            {(['length', 'width', 'height'] as const).map((dimension) => (
                                <TextField
                                    key={dimension}
                                    id={`${dimension}_${item.client_key}`}
                                    label={{ length: 'লম্বা', width: 'চওড়া', height: 'উচ্চতা' }[dimension]}
                                    type="number"
                                    numeric
                                    value={item[dimension]}
                                    onChange={(value) => patch({ [dimension]: value })}
                                    error={errors[dimension]}
                                />
                            ))}
                        </div>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={item.dimension_unit}
                            onValueChange={(value) => value && patch({ dimension_unit: value })}
                            className="justify-start"
                        >
                            {dimensionUnits.map((unit) => (
                                <ToggleGroupItem key={unit.value} value={String(unit.value)} className="h-11 px-4">
                                    {unit.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                    </div>

                    <TextField
                        id={`target_date_${item.client_key}`}
                        label="কাজ শেষের তারিখ"
                        type="date"
                        value={item.target_date}
                        onChange={(value) => patch({ target_date: value })}
                        error={errors.target_date}
                    />

                    <div className="sm:col-span-2">
                        <ImagePicker
                            label="পণ্যের ছবি"
                            existing={item.photos}
                            removedIds={item.remove_media}
                            newFiles={item.new_photos}
                            onAdd={(files) => onUpdate((current) => ({ ...current, new_photos: [...current.new_photos, ...files] }))}
                            onRemoveNew={(i) => onUpdate((current) => ({ ...current, new_photos: current.new_photos.filter((_, n) => n !== i) }))}
                            onRemoveExisting={(id) => onUpdate((current) => ({ ...current, remove_media: [...current.remove_media, id] }))}
                            onRestore={() => onUpdate((current) => ({ ...current, remove_media: current.remove_media.filter((id) => !current.photos.some((p) => p.id === id)) }))}
                            error={firstError(errors, 'new_photos') ?? firstError(errors, 'remove_media')}
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <ImagePicker
                            label="নকশা / ডিজাইনের ছবি"
                            existing={item.designs}
                            removedIds={item.remove_media}
                            newFiles={item.new_designs}
                            onAdd={(files) => onUpdate((current) => ({ ...current, new_designs: [...current.new_designs, ...files] }))}
                            onRemoveNew={(i) => onUpdate((current) => ({ ...current, new_designs: current.new_designs.filter((_, n) => n !== i) }))}
                            onRemoveExisting={(id) => onUpdate((current) => ({ ...current, remove_media: [...current.remove_media, id] }))}
                            onRestore={() => onUpdate((current) => ({ ...current, remove_media: current.remove_media.filter((id) => !current.designs.some((d) => d.id === id)) }))}
                            error={firstError(errors, 'new_designs')}
                        />
                    </div>

                    <div className="sm:col-span-2">
                        <button type="button" onClick={() => setMoreOpen(!moreOpen)} className="text-muted-foreground flex items-center gap-1 text-sm">
                            <ChevronDown className={cn('h-4 w-4 transition-transform', moreOpen && 'rotate-180')} />
                            আরও তথ্য (বিবরণ, ডিজাইন নং, মন্তব্য)
                        </button>
                    </div>
                    {moreOpen && (
                        <>
                            <TextField id={`design_no_${item.client_key}`} label="ডিজাইন নম্বর" value={item.design_no} onChange={(value) => patch({ design_no: value })} error={errors.design_no} />
                            <TextField id={`description_${item.client_key}`} label="বিবরণ" value={item.description} onChange={(value) => patch({ description: value })} error={errors.description} />
                            <div className="sm:col-span-2">
                                <TextField id={`remarks_${item.client_key}`} label="মন্তব্য" value={item.remarks} onChange={(value) => patch({ remarks: value })} error={errors.remarks} />
                            </div>
                        </>
                    )}

                    {onRemove && (
                        <div className="sm:col-span-2">
                            <Button type="button" variant="outline" className="text-destructive h-11 w-full" onClick={onRemove}>
                                <Trash2 className="h-4 w-4" />
                                এই পণ্য বাদ দিন
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
```

- [ ] **Step 2: Rewire create.tsx**

In `resources/js/pages/orders/create.tsx`:

1. Imports: remove `ChevronDown`, `Trash2` from lucide and `cn` if unused; add `import OrderItemCard, { emptyItem, type ItemForm } from './order-item-card';` and `import { type OrderImage } from '@/components/image-picker';` and `useEffect` from react.
2. Delete the local `ItemForm` interface and `emptyItem` function.
3. `OrderForm` index signature becomes `[key: string]: string | number | null | ItemForm[];` (unchanged) — `items: ItemForm[]` now uses the imported type.
4. `Props.order.items` type: `(Partial<ItemForm> & { id: number; photos: OrderImage[]; designs: OrderImage[] })[]`; add `woodTypes: string[]; polishTypes: string[];` to `Props` and destructure them.
5. Initial items:
   ```ts
        items: order
            ? order.items.map((item) => ({ ...emptyItem(), ...item, client_key: `item-${item.id}` }) as ItemForm)
            : [emptyItem()],
   ```
   Null values from the server (`description: null` etc.) must become `''`: map with
   `Object.fromEntries(Object.entries(item).map(([k, v]) => [k, v ?? '']))` before spreading, i.e. `{ ...emptyItem(), ...stripNulls(item), client_key: ... }` with
   ```ts
   const stripNulls = <T extends object>(row: T) => Object.fromEntries(Object.entries(row).map(([k, v]) => [k, v ?? ''])) as T;
   ```
   (`category_id` arrives as a number; also coerce: `category_id: item.category_id ? String(item.category_id) : ''`.)
6. Replace `openItem` state and `setItem/addItem/removeItem` with:
   ```ts
    const [openKeys, setOpenKeys] = useState<string[]>(order ? [] : [data.items[0].client_key]);
    const toggle = (key: string) => setOpenKeys((keys) => (keys.includes(key) ? keys.filter((k) => k !== key) : [...keys, key]));

    const updateItem = (key: string, update: (item: ItemForm) => ItemForm) =>
        setData((current) => ({ ...current, items: current.items.map((item) => (item.client_key === key ? update(item) : item)) }));

    const addItem = () => {
        const item = emptyItem();
        setData((current) => ({ ...current, items: [...current.items, item] }));
        setOpenKeys((keys) => [...keys, item.client_key]);
    };

    const removeItem = (key: string) => setData((current) => ({ ...current, items: current.items.filter((item) => item.client_key !== key) }));

    /** Errors for item `index`, with the "items.N." prefix stripped. */
    const itemErrors = (index: number) =>
        Object.fromEntries(
            Object.entries(errors as Record<string, string>)
                .filter(([key]) => key.startsWith(`items.${index}.`))
                .map(([key, message]) => [key.slice(`items.${index}.`.length), message]),
        );

    // A card the server complained about opens itself.
    useEffect(() => {
        const failing = data.items.filter((_, index) => Object.keys(itemErrors(index)).length > 0).map((item) => item.client_key);
        if (failing.length > 0) setOpenKeys((keys) => Array.from(new Set([...keys, ...failing])));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [errors]);
   ```
7. Delivery date chips — under the `expected_delivery_date` TextField, wrap it:
   ```tsx
                    <div className="grid gap-2">
                        <TextField ... existing expected_delivery_date props ... />
                        <div className="flex gap-2">
                            {[7, 15, 30].map((days) => (
                                <Button
                                    key={days}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-10"
                                    onClick={() => setData('expected_delivery_date', addDays(data.order_date || today, days))}
                                >
                                    +{toBengaliDigits(days)} দিন
                                </Button>
                            ))}
                        </div>
                    </div>
   ```
   with, above the component:
   ```ts
   /** yyyy-mm-dd plus n days, in UTC so no timezone shifts the date. */
   const addDays = (iso: string, days: number) => {
       const [y, m, d] = iso.split('-').map(Number);
       return new Date(Date.UTC(y, m - 1, d + days)).toISOString().slice(0, 10);
   };
   ```
8. Replace the whole `{/* Items */}` section body's `data.items.map(...)` block with:
   ```tsx
                    {data.items.map((item, index) => (
                        <OrderItemCard
                            key={item.client_key}
                            index={index}
                            item={item}
                            open={openKeys.includes(item.client_key)}
                            onToggle={() => toggle(item.client_key)}
                            onUpdate={(update) => updateItem(item.client_key, update)}
                            onRemove={data.items.length > 1 ? () => removeItem(item.client_key) : undefined}
                            errors={itemErrors(index)}
                            categories={categories}
                            dimensionUnits={dimensionUnits}
                            woodTypes={woodTypes}
                            polishTypes={polishTypes}
                        />
                    ))}
   ```
   Change the heading `আইটেম` to `পণ্য` and the add button text to `পণ্য যোগ করুন`.
9. Submit: destructure `transform` from `useForm` and replace `submit` with:
   ```ts
    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Files only travel in multipart, and PHP only parses multipart on POST,
        // so an edit is a POST that Laravel reads as PUT. client_key and the
        // existing image lists are browser-only and never sent.
        transform((current) => ({
            ...current,
            ...(isEdit ? { _method: 'put' } : {}),
            items: current.items.map(({ client_key, photos, designs, ...rest }) => rest),
        }));

        post(isEdit ? route('orders.update', order!.id) : route('orders.store'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };
   ```
   (Silence the unused-variable lint on the destructured names with `// eslint-disable-next-line @typescript-eslint/no-unused-vars` above the `items:` line if eslint flags them.)

- [ ] **Step 3: Type-check, lint, format, build**

Run: `npx tsc --noEmit && npx eslint resources/js/pages/orders && npx prettier --write resources/js/pages/orders && npm run build`
Expected: no type or lint errors; build succeeds. Fix any `useForm` generic complaints by widening the `ItemForm` index signature, not by `any`.

- [ ] **Step 4: Re-run the PHP order tests**

Run: `php artisan test tests/Feature/Orders tests/Feature/Actions/SaveOrderTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/orders/order-item-card.tsx resources/js/pages/orders/create.tsx
git commit -m "feat: order form with product cards, per-item images and chips

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Order page galleries, legacy section, README, and browser check

**Files:**
- Modify: `resources/js/pages/orders/show.tsx`
- Modify: `resources/js/pages/orders/order-photos.tsx`
- Modify: `README.md`

**Interfaces:**
- Consumes: `ImagePicker`, `ImageViewer`, `OrderImage` (Task 6); props `order.items[n].photos|designs`, `legacyPhotos` (Task 4).

- [ ] **Step 1: show.tsx**

1. `Item` interface: add `photos: OrderImage[]; designs: OrderImage[];` and `import { ImagePicker, type OrderImage } from '@/components/image-picker';`.
2. `Props.order`: remove `photos: Photo[];`. Add top-level `legacyPhotos: Photo[];` to `Props` and destructure it.
3. Replace `<OrderPhotos orderId={order.id} photos={order.photos} canManage={canManage} />` with:
   ```tsx
                {legacyPhotos.length > 0 && <OrderPhotos orderId={order.id} photos={legacyPhotos} canManage={canManage} />}
   ```
4. In the item card, after `{item.remarks && ...}` add:
   ```tsx
                            {(item.photos.length > 0 || item.designs.length > 0) && (
                                <div className="mt-3 grid gap-3">
                                    <ImagePicker label="পণ্যের ছবি" existing={item.photos} />
                                    <ImagePicker label="নকশা / ডিজাইনের ছবি" existing={item.designs} />
                                </div>
                            )}
   ```
   (`ImagePicker` renders nothing for an empty read-only zone.)

- [ ] **Step 2: order-photos.tsx becomes the legacy gallery**

Rewrite to:

```tsx
import { ImageViewer } from '@/components/image-picker';
import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

export interface Photo {
    id: number;
    collection: 'photos' | 'designs';
    name: string;
    url: string;
    thumb: string;
}

interface Props {
    orderId: number;
    photos: Photo[];
    canManage: boolean;
}

/**
 * Photos attached to the whole order before images moved onto items. Shown so
 * nothing is lost, deletable, never added to: new images go on an item.
 */
export function OrderPhotos({ orderId, photos, canManage }: Props) {
    const [viewing, setViewing] = useState<number | null>(null);

    const remove = (photo: Photo) => {
        if (!window.confirm('এই ছবি মুছে ফেলতে চান?')) return;

        router.delete(route('orders.photos.destroy', [orderId, photo.id]), { preserveScroll: true });
    };

    return (
        <section className="flex flex-col gap-3 rounded-lg border p-4">
            <div>
                <h2 className="font-medium">অর্ডারের পুরনো ছবি</h2>
                <p className="text-muted-foreground text-sm">নতুন ছবি এখন প্রতিটি পণ্যের সাথে যোগ হয়।</p>
            </div>

            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                {photos.map((photo, index) => (
                    <div key={photo.id} className="relative aspect-square overflow-hidden rounded-lg border">
                        <button type="button" onClick={() => setViewing(index)} className="h-full w-full">
                            <img src={photo.thumb} alt={photo.name} loading="lazy" className="h-full w-full object-cover" />
                        </button>

                        {photo.collection === 'designs' && (
                            <span className="bg-background/90 absolute top-1 left-1 rounded px-1.5 py-0.5 text-xs">নকশা</span>
                        )}

                        {canManage && (
                            <button
                                type="button"
                                onClick={() => remove(photo)}
                                title="মুছে ফেলুন"
                                className="bg-background/90 absolute top-1 right-1 rounded p-1.5"
                            >
                                <Trash2 className="text-destructive h-4 w-4" />
                            </button>
                        )}
                    </div>
                ))}
            </div>

            {viewing !== null && <ImageViewer images={photos} index={viewing} onClose={() => setViewing(null)} />}
        </section>
    );
}
```

- [ ] **Step 3: Type-check, build, and drive it in the browser**

Run: `npx tsc --noEmit && npx eslint resources/js/pages/orders && npx prettier --write resources/js/pages/orders && npm run build`
Expected: clean.

Then, with the dev stack running (`composer run dev`) and logged in as the owner (`01700000000` / `password`), check by hand or with the Chrome tools, at a 390px-wide viewport:
1. New order: add 3 products; give product 1 two photos, product 2 one design, product 3 one photo and one design. Save. On the order page each product shows exactly its own images.
2. Edit: on product 2, remove its design (see "পূর্বাবস্থায় ফেরান"), restore it, remove it again; add a photo to product 1. Save. Check counts.
3. Edit: delete product 2 (the middle one) *before* saving, with new unsaved images still on product 3. Save. Product 3's images must still be on product 3.
4. Wood "অন্যান্য" with custom text saves and reopens as অন্যান্য with that text.
5. Submit with an empty product name on product 3: that card opens with a red border and the error.
6. The order from before this change (1 order, 1 item, 1 photo locally) shows its photo inside the item, and no "অর্ডারের পুরনো ছবি" section.

- [ ] **Step 4: README PHP limits**

Append to `README.md`:

```markdown
## PHP upload limits

Order item photos and design images are saved with the order in one request.
The browser shrinks each image to 1600px first, but PHP still has to accept the
batch. Set at least:

    upload_max_filesize = 10M
    post_max_size = 64M
    max_file_uploads = 100

in the `php.ini` used by the web server (check with `php --ini`), then restart
PHP-FPM or `php artisan serve`.
```

- [ ] **Step 5: Full test run**

Run: `php artisan test`
Expected: all PASS (deprecation notices only).

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/orders/show.tsx resources/js/pages/orders/order-photos.tsx README.md
git commit -m "feat: show per-item images on the order page; legacy photos read-only

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
