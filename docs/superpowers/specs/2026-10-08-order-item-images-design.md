# Order item images and low-typing order form

Date: 2026-10-08
Status: approved design, awaiting spec review

## Goal

Make the order form faster to fill on a phone and let every product item in an
order carry its own photos and its own নকশা (design) images, kept separate from
each other and from other items.

### What was asked

- Fewer free-text inputs; use selects, chips, toggles and presets.
- An order holds many product items, each with its own information.
- Each item has many product images and, separately, many নকশা/design images.
- An "Add product" control adds items dynamically.
- Compact item cards with easy upload, preview, remove and management.
- Images never move between items.
- Existing behaviour and data keep working.

### What already exists (not new work)

- Orders already have many `order_items`; `SaveOrder` syncs them by id and the
  form already has an add-item button and collapsible cards.
- Images today hang off `Order` (media collections `photos`, `designs`) and can
  only be uploaded from the order page after the order is saved.

### Decisions taken during brainstorming

| Question | Decision |
|---|---|
| Source of wood / polish options | Fixed list in code plus "অন্যান্য" with a free-text box |
| Existing order-level images | Moved onto the item when the order has exactly one item; otherwise left on the order and shown as legacy |
| Upload mechanism | Files travel with the order save in one multipart request, compressed in the browser first |

## Data

No schema change and no new table. `docs/schema.md` already names `media` as the
polymorphic file store whose `collection_name` separates photos from designs.

- `OrderItem` implements `HasMedia` with collections `photos` and `designs`,
  both accepting `image/jpeg`, `image/png`, `image/webp`.
- The `thumb` (400px, q70) and `web` (1600px, q75) non-queued conversions move
  from `Order` into a trait `App\Models\Concerns\HasOrderImageConversions`, used
  by both `Order` and `OrderItem`, so they cannot drift.
- `Order` keeps its collections so legacy media still resolves.
- `wood_type` and `polish_type` stay `VARCHAR(100)` holding the Bengali text.
  Old free-text values remain valid.
- `App\Support\OrderItemPresets` exposes two string lists, passed to the form as
  props. They are strings, not backed enums, because the column also accepts a
  custom "অন্যান্য" value; the CLAUDE.md enum rule covers closed value sets.
  - wood: সেগুন, মেহগনি, চাম্বল, গর্জন, কড়ই, শাল, বোর্ড
  - polish: বার্নিশ, লেকার, পিইউ, ডুকো, রং, পলিশ ছাড়া

### Migration of existing images

A data migration reassigns media for every order that has exactly one item:
`model_type` `App\Models\Order` → `App\Models\OrderItem`, `model_id` → that item's
id, `collection_name` unchanged. Files are not moved: the default media path
generator stores by media id, not by owner.

- Orders with zero or several items are untouched.
- Rerunnable: a second run finds no order-level media on single-item orders.
- `down()` moves `OrderItem` media back to the item's order.

## Server

### Request (`OrderRequest`)

Added per item:

| Field | Rule |
|---|---|
| `items.*.new_photos` | `nullable`, `array`, `max:10` |
| `items.*.new_photos.*` | `image`, `mimes:jpg,jpeg,png,webp`, `max:10240` |
| `items.*.new_designs` | same as `new_photos` |
| `items.*.new_designs.*` | same as `new_photos.*` |
| `items.*.remove_media` | `nullable`, `array` |
| `items.*.remove_media.*` | `integer` |
| `items.*.wood_type`, `items.*.polish_type` | unchanged: `nullable`, `string`, `max:100` |

Messages in Bengali, matching the ones in `OrderPhotoRequest`. Edits are sent as
`POST` with `_method=PUT` so multipart bodies reach Laravel; the route stays
`orders.update`.

### `SaveOrder`

Signature unchanged. Item rows may now carry `new_photos`, `new_designs` and
`remove_media`. Inside the existing `DB::transaction()`, after each item row is
saved:

1. For each id in `remove_media`: load the media row; if its `model_type` is not
   `OrderItem` or its `model_id` is not this item's id, throw a
   `ReferencedRecordException` with a Bengali message, which rolls back the
   whole save. Otherwise delete it.
2. Add each `new_photos` file to the item's `photos` collection and each
   `new_designs` file to `designs`, with custom property `uploaded_by` = the
   acting user's id (the existing `$userId` argument).

When an item is dropped from the order, its media is deleted along with it. The
existing guard that refuses to drop an item that has `order_item_works` runs
first and is unchanged.

The media writing lives in a private method of `SaveOrder`. It is not a separate
Action because it never runs on its own.

Known limitation: if the transaction rolls back after a file has been written to
disk, that file is orphaned. The database stays correct. No cleanup job is added.

### Controller

- `OrderController::show` sends, per item, `photos` and `designs` arrays of
  `{id, name, url, thumb}`, plus a top-level `legacyPhotos` with any media still
  on the `Order`.
- `OrderController::edit` sends the same per-item arrays so the form can render
  and remove existing images.
- `create` and `edit` send `woodTypes` and `polishTypes` from `OrderItemPresets`.
- `OrderController::store` catches `ReferencedRecordException` and redirects
  back with the message, as `update` already does. On create every
  `remove_media` id fails the ownership check, since new items own no media.
- `OrderPhotoController::store` and route `orders.photos.store` are removed.
  `destroy` and `orders.photos.destroy` stay, for legacy order-level images.

### Deployment note

Uploads arrive in one request. The README gets a section stating the minimum
PHP settings: `upload_max_filesize = 10M`, `post_max_size = 64M`,
`max_file_uploads = 100`. The local machine is currently at 2M / 8M / 20.

## Form UI (`orders/create.tsx`)

Mobile-first. The sticky bottom save bar is unchanged.

### Less typing

- Delivery date: chips **+৭ দিন · +১৫ দিন · +৩০ দিন** computed from the order
  date; the date input stays.
- Dimension unit: a three-way toggle using `components/ui/toggle-group`, labels
  from `DimensionUnit::label()`.
- Quantity: −/+ stepper with large tap targets; typing still allowed.
- Category: choosing one fills `item_name` with the category name when
  `item_name` is empty.
- Wood and polish: tappable chips from the presets plus **অন্যান্য**, which
  reveals a short text input. A stored value not in the list renders as
  অন্যান্য with the text prefilled.
- Description, design number and remarks sit under a collapsed **আরও তথ্য**.

### Item card

Collapsed header: first product photo as a thumbnail, item number and name, line
total, `quantity × unit_price`, and counts of product and design images.
New orders open the first card; adding a product opens the new card.

Expanded body: the fields above, then two image zones, **পণ্যের ছবি** and
**নকশা / ডিজাইনের ছবি**, then **আরও তথ্য**, then **এই পণ্য বাদ দিন** (hidden when
it is the only item). The add button below the list reads **পণ্য যোগ করুন**.

### Image zones

- Each zone has its own `<input type="file" accept="image/*" multiple>`, which
  offers camera and gallery on phones.
- A picked file is downscaled in the browser to at most 1600px on the long edge
  as JPEG quality 0.8, then shown as a thumbnail with a "নতুন" badge.
- × on a saved image hides it and adds its id to the card's `remove_media`; a
  "পূর্বাবস্থায় ফেরান" link restores it until save. × on a new file drops it.
- Tapping a thumbnail opens a full-screen viewer with previous/next, reusing
  the viewer currently in `order-photos.tsx`.
- More than 10 images in one zone, or a non-image file, is refused in the
  browser with a Bengali message; the server rules are the authority.

### Keeping images on the right item

- Every card has a `client_key` (generated with `crypto.randomUUID()` for new
  items, `item-<id>` for saved ones) used as the React `key`. Images live in
  that card's own state, so removing or reordering cards cannot shift images.
- On submit the form is sent with `forceFormData`; each card's files go under
  its position at submit time (`items[2][new_photos][]`), so files and fields of
  one item always travel together.
- Server errors (`items.2.new_photos.0`) map back to the card at that position.
  A card with an error opens automatically and gets a red border.

### Components

`create.tsx` is 535 lines; it is split:

| File | Purpose |
|---|---|
| `pages/orders/order-item-card.tsx` | one item card |
| `components/image-picker.tsx` | one image zone; also used read-only |
| `components/choice-chips.tsx` | chip select with optional অন্যান্য |
| `lib/compress-image.ts` | canvas downscale to a `File` |

The order pages define their item types locally (there is no shared
`OrderItem` type in `resources/js/types/models.ts`); the local item interfaces
in `create.tsx` and `show.tsx` gain `photos` and `designs`, and the image shape
moves to an exported `OrderImage` type in `image-picker.tsx`.

## Order page (`orders/show.tsx`)

- Each item shows its two galleries read-only through `image-picker`, with a
  link to edit the order for changes.
- The `OrderPhotos` section renders only when `legacyPhotos` is non-empty,
  titled **অর্ডারের পুরনো ছবি**, view and delete only.

## Testing (Pest)

- Creating an order with two items, each with photos and designs, writes the
  exact `media` row count per item per collection and none on the `Order`.
- A file sent under item 2 is attached to item 2 only.
- `remove_media` deletes only the named media of that item.
- `remove_media` naming another item's media, or an order's legacy media, fails
  with the Bengali message and persists nothing: item, order and media counts
  unchanged.
- An exception forced mid-save leaves zero new rows in `orders`, `order_items`
  and `media`.
- Dropping an item deletes its media; dropping an item with works is still
  refused.
- A non-image upload and an 11th file in one zone fail validation.
- Wood/polish accept both preset and custom values.
- The migration moves media only for single-item orders, leaves multi-item
  orders alone, and a second run changes nothing.
- `show` exposes per-item galleries and `legacyPhotos`; `orders.photos.store`
  no longer exists; legacy `destroy` still works.
- Existing order, photo, status, payment, profit and item-works tests still pass
  (`OrderPhotoTest` is rewritten for the new behaviour).

Uploads in tests use `Storage::fake` and `UploadedFile::fake()->image()`.

## Out of scope

Drag-to-reorder images, captions, editing delivered or cancelled orders,
changes to item works or payments, staged/instant upload, orphan file cleanup.
