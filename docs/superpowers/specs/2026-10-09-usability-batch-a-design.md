# Usability batch A: finding your way, Bengali everywhere, phone fixes

Date: 2026-10-09
Status: draft, awaiting review

## Goal

Make the app easy to use for every role (owner, manager, accountant,
storekeeper) without training. Batch A covers getting around the app and
fixing obvious bugs. Batches B (confirm dialogs, dates, unsaved work, empty
states) and C (searchable pickers, phone tables) get their own specs later.

### What was asked

"Make it user friendly so that any user can use it." The scope is general
polish for everyone, not one role. A code-level usability audit of all
screens on 2026-10-09 produced a ranked list of problems, and this batch takes
the top items.

### Decisions taken during brainstorming

- The sidebar uses four groups with renamed labels, and setup is folded by
  default.
- The dashboard gets quick actions and a "today" checklist above the
  existing tiles.
- The shop name is **যতীন্দ্র ফার্নিচার**.
- "Delete account" is removed for everyone, server route included.
- No schema changes.

## 1. Sidebar menu

`resources/js/components/app-sidebar.tsx`, `nav-main.tsx`

| Group | Items (new label, old label if changed) | Permission |
|---|---|---|
| দৈনিক কাজ | ড্যাশবোর্ড | none |
| | অর্ডার | `orders.view` |
| | হাজিরা | `attendance.view` |
| | কেনাকাটা | `purchases.view` |
| | খরচ | `expenses.view` |
| | দিন শেষের হিসাব (দিনের হিসাব) | `daily_closing.view` |
| পাওনা-দেনা | কর্মীর পাওনা (কর্মীর হিসাব) | `employee_ledger.view` |
| | সরবরাহকারীর পাওনা (সরবরাহকারীর হিসাব) | `supplier_ledger.view` |
| | খরচের রিপোর্ট (সব খরচের হিসাব) | `reports.financial` |
| মালামাল ও পণ্য | মালামালের মজুদ (গুদাম) | `stock.view` |
| | মালামালের তালিকা (মালামাল) | `materials.view` |
| | পণ্য | `products.view` |
| সেটআপ (folded) | কাস্টমার, সরবরাহকারী, কর্মী, দোকান, কাজের ধরন | existing |
| | ক্যাশ ও ব্যাংক (হিসাব) | `accounts.view` |
| | খরচের খাত, পণ্যের ক্যাটাগরি, ব্যবহারকারী | existing |

The behaviour:

- `NavMain` takes a list of groups (`{ title, items, collapsible? }`) and drops
  the hard-coded "Platform" label.
  - Items the user has no permission for are filtered out, and a group with
    no visible items is not rendered.
- The সেটআপ group uses the shadcn `Collapsible`.
  - It starts closed, and starts open when the current page is one of its
    items.
  - Its open state is kept in `localStorage`, read and written inside
    try/catch.
- **Active item:** an item is active when the current path equals its URL or
  starts with `url + '/'`, ignoring the query string. `/orders/5/edit` lights
  up অর্ডার, but `/orders-x` does not.
- **When the sidebar collapses to icons,** group labels hide; the shadcn
  sidebar already does this.
- **Phone menu button:** `SidebarTrigger` gets at least a 44px tap target on
  mobile, by passing a className from the app layout header, not by editing
  `ui/sidebar.tsx` internals beyond the size.
- **Matching page titles:** the pages for renamed items use the new labels in
  their `<Head title>`, page heading and breadcrumb. These are the daily
  closing, employee ledger (index and show), supplier ledger (index and show),
  spending report, stock, materials and accounts pages.
- **Cleanup:** delete the unused `components/app-header.tsx`, after a grep
  confirms nothing imports it.

## 2. Dashboard

`app/Http/Controllers/DashboardController.php`, new
`app/Queries/DashboardToday.php`, `resources/js/pages/dashboard.tsx`,
`resources/js/types/`

### Quick actions ("আজ কী করবেন?")

These are large buttons: two columns on a phone, a single row on desktop, and
at least 48px tall. Each one shows only when the user can perform the action.

| Button | Link | Permission |
|---|---|---|
| নতুন অর্ডার | `/orders/create` | `orders.manage` |
| হাজিরা নিন | `/attendance` | `attendance.mark` |
| কেনাকাটা | `/purchases/create` | `purchases.record` |
| খরচ | `/expenses/create` | `expenses.record` |
| দিন শেষের হিসাব | `/daily-closing` | `daily_closing.run` |

The server sends one `can` map, permission name to boolean, for just these
five permissions, and the page reads it. The routes are checked against
`routes/web.php` during planning, and any that differ are corrected there.

### Today checklist ("আজকের কাজ")

`DashboardToday` has one method per block. The controller computes a block
only when the user has its permission and otherwise sends `null`, the same
pattern as `DashboardSummary`. "Today" is the date in `Asia/Dhaka`.

| Block | Permission | Data | Display |
|---|---|---|---|
| `attendance()` | `attendance.view` | `marked`: count of attendance rows for today whose employee is active and not soft-deleted. `total`: count of those employees. | ✓ হাজিরা নেওয়া হয়েছে (১৪/১৪) when marked = total, otherwise ✗ হাজিরা বাকি (৯/১৪) → হাজিরা নিন. Hidden when total = 0. |
| `closing()` | `daily_closing.view` | The shops in scope: the user's `shop_id` if set, otherwise all active shops. For each, `{shop_id, name, closed}`, where closed means a `daily_closings` row exists for today with `closed_at` not null. | One line per shop: ✓ বন্ধ হয়েছে or ✗ দিন শেষের হিসাব বাকি → এখন করুন. The shop name shows only when there is more than one shop. |
| `lateOrders()` | `orders.view` | Up to 5 open orders with `expected_delivery_date` before today, oldest first. Each has `id, order_no, customer_name, days_late`. The total late count is already in `orders.late_delivery`. | ⚠ ৩টি অর্ডারের ডেলিভারি পেছিয়েছে → সব দেখুন, then up to five rows linking to `/orders/{id}`. Hidden when there are none. |

How these queries are built:

- "Open" uses the existing `Order::open()` scope, the same one the late
  delivery tile uses, so the two numbers agree.
- `days_late` is computed in SQL with `DATEDIFF`.
- Late orders are loaded with the customer in the same query, so there is no
  N+1.
- **Fallback:** if every block is null, or the user has no permission for any
  of them, the checklist section is not rendered.

### Existing tiles

The existing tiles are unchanged and sit below the checklist.

## 3. Bengali everywhere

Translate every hard-coded English string to Bengali in these places:

- `pages/settings/profile.tsx`, `password.tsx`, `appearance.tsx`
- `layouts/settings/layout.tsx` (nav: প্রোফাইল, পাসওয়ার্ড, দেখতে কেমন)
- `components/appearance-tabs.tsx` (হালকা, গাঢ়, সিস্টেম অনুযায়ী)
- `components/user-menu-content.tsx` (সেটিংস, লগ আউট)
- `pages/auth/forgot-password.tsx`, `reset-password.tsx`,
  `confirm-password.tsx`, `verify-email.tsx`
- `components/ui/sidebar.tsx` and `components/ui/sheet.tsx`, screen-reader
  text only ("Toggle Sidebar", "Close", "Sidebar Navigation")
- Any server-side flash or status strings these pages show, such as the
  password reset status and the verification link sent message. These go in
  `lang/bn/*.php`, not inline.

App name:

- `APP_NAME="যতীন্দ্র ফার্নিচার"` in `.env` and `.env.example`.
- `components/app-logo.tsx` reads the shared app name instead of a hard-coded
  string.
- `pages/welcome.tsx` uses the same spelling.

Profile page:

- **Email is optional.** In `ProfileUpdateRequest`, `email` becomes
  `nullable`, and the other rules stay, including unique-ignoring-self. An
  empty string is saved as `null`.
- **The phone number stays read-only** on this page. It is the login key, and
  changing it is an admin action on the users page.
- **Delete account is removed.** That means:
  - the `components/delete-user.tsx` component and its use in `profile.tsx`
  - the `profile.destroy` route in `routes/settings.php`
  - `ProfileController::destroy`
  - any test that covers self-deletion

  Turning a user off stays on the users page (`users.manage`).

## 4. Phone keyboard and login fixes

- **Customer search** on `orders/create.tsx` (placeholder "mobile number or
  name") drops `inputMode="numeric"` and uses the default text keyboard.
- **Order search** on `orders/index.tsx` drops `inputMode="numeric"`, because
  order numbers contain letters and a hyphen.
- **Numeric fields:** in `form-field.tsx`, `TextField numeric` maps to
  `inputMode="decimal"`, so money and quantity fields get a decimal point.
  - Fields that must be whole numbers keep `numeric`. Planning lists every
    `numeric` use and marks which ones are integers.
  - Phone fields use `type="tel"`.
- **Login "মনে রাখুন":** the checkbox in `auth/login.tsx` is wired to
  `data.remember` through `onCheckedChange`. `LoginRequest` already passes
  `remember` to `Auth::attempt`.

## Out of scope (later batches)

- **Batch B:** a Bengali confirm dialog replacing `window.confirm`, a readable
  Bengali date format, protecting unsaved work, empty states with action
  buttons, sticky save bars on daily closing and employee payment, and the
  stale supplier-payable text.
- **Batch C:** searchable pickers, a card layout for `data-table.tsx` on
  phones, and fixing the disabled state on paging links.
- The marketing welcome page at `/` stays as it is.

## Testing

These Pest feature tests are added or changed.

`tests/Feature/DashboardTest.php`:

- **Quick actions:** the `can` map reflects each role's permissions. A
  storekeeper gets no `orders.manage`.
- **Attendance:**
  - The counts are correct with active, inactive and soft-deleted employees.
  - The counts are correct with a mix of marked and unmarked employees.
  - The block is `null` without `attendance.view`.
- **Closing:**
  - A user with a `shop_id` sees only that shop.
  - A user without a `shop_id` sees all active shops.
  - A `daily_closings` row with `closed_at` null counts as not closed.
- **Late orders:**
  - At most 5 are returned, oldest first.
  - Delivered and cancelled orders are excluded.
  - `days_late` is correct.
  - The block is `null` without `orders.view`.

`tests/Feature/Settings/ProfileUpdateTest.php`:

- The profile saves with an empty email, and the stored value is `null`.
- A duplicate email is still rejected.
- `DELETE /settings/profile` returns 404 or 405, and the user still exists.

`tests/Feature/Auth/AuthenticationTest.php`:

- Logging in with `remember=1` sets the remember cookie and fills
  `remember_token`.

Frontend checks, since there is no JS test runner:

- `npm run types`, or `tsc --noEmit` if that script does not exist
- `npm run lint`
- `npm run build`
- A curl smoke test confirming every changed page returns 200 when logged in
- A grep for leftover English UI strings in the touched files
- A visual check in Chrome if the extension is connected

## Risks

- **Renamed labels:** people used to the old labels may look for "গুদাম". The
  icons stay the same, which helps.
- **Removing self-delete:** this changes behaviour, but it is safe because no
  shop workflow depends on it.
- **`inputMode="decimal"`:** some Android keyboards show a comma as the
  decimal separator. Server-side validation already rejects bad input, and
  this is checked on a real phone after deploy.
