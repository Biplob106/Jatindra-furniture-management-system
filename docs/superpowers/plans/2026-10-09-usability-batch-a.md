# Usability Batch A Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the app easy to find your way around for every role. That means
a grouped sidebar, a dashboard that says what to do today, Bengali on every
screen, and fixes for the phone keyboard and login bugs.

**Architecture:**
- **Server:** one new read-only query class (`App\Queries\DashboardToday`),
  with blocks gated by permission in the existing `DashboardController`, and
  removal of the self-delete route.
- **Frontend:** the sidebar becomes data-driven groups, the dashboard gets two
  new components, and the rest is string and prop edits.
- No schema changes.

**Tech Stack:** Laravel 12, Pest, Inertia 2, React 19, TypeScript, shadcn/ui,
Tailwind 4, MySQL 8.

**Spec:** `docs/superpowers/specs/2026-10-09-usability-batch-a-design.md`

## Before you start

- **Branch:** the working tree has uncommitted, unrelated work on
  `feature/order-item-images` (the spending report, seeders and others). Do
  not commit any of it as part of this plan.
  - Before Task 1, ask the human partner whether to branch from the current
    HEAD (`git switch -c feature/usability-batch-a`) and leave their changes
    in the working tree, or wait until they have committed.
  - Every commit in this plan `git add`s only the files it names.
- **Run PHP tests** with `php artisan test <path>`. The test database is MySQL
  `jatindra_furniture_test`, see `phpunit.xml`.
- **Frontend checks:** there is no JS test runner. Use
  `npx tsc --noEmit -p .`, `npx eslint <files>` (without `--fix` while
  checking) and `npm run build`.

## Global Constraints

- All user-facing strings are Bengali. Code, comments, names and commit
  messages are English. (CLAUDE.md)
- Validation lives in FormRequests; React renders the errors Laravel returns.
  (CLAUDE.md)
- `docs/schema.md` is the source of truth. `users.email` is
  `VARCHAR(150) UNIQUE NULL`.
- Permission gating is enforced server-side. A dashboard block the reader may
  not see is not computed and is sent as `null`. (Existing
  `DashboardController` pattern)
- "Today" is `CarbonImmutable::today()` in `Asia/Dhaka`, the app timezone.
- The shop name is exactly **যতীন্দ্র ফার্নিচার**.
- Tap targets on mobile are at least 44px. Quick-action buttons are at least
  48px tall.
- Every `localStorage` read and write is wrapped in try/catch.

### Deviations from the spec, decided while planning

- **No `can` map:** the spec's server-sent map for the quick actions is
  dropped. `auth.permissions` is already shared on every page and read
  through `usePermission()`, so a second copy would be redundant.
- **Numeric keyboards:** the spec says phone fields use `type="tel"`, but
  every phone field already uses `TextField numeric` without `type="number"`,
  which keeps the digit keypad. So `TextField` maps `numeric` to `decimal`
  only when `type="number"`, and phone, NID and account-number fields keep the
  digit pad. This is one line instead of about 15 edits, with the same result
  on the phone.

## Review Focus

1. **A user with a `shop_id` whose shop is inactive or soft-deleted** should
   still see their own shop's closing line, not a blank list. This is pinned
   in Task 3.
2. **A draft order** (with `order_no` null) that is past its delivery date
   should appear in late orders labelled "খসড়া" and not crash the row. This
   is pinned in Task 3 (server) and Task 4 (render).
3. **An attendance row for a worker who has since been turned off** must not
   push "marked" above "total", which would read "১৫/১৪". This is pinned in
   Task 3.
4. **Opening a setup page directly by URL** should show the folded সেটআপ
   group expanded with the item highlighted, even if `localStorage` says it
   was closed. This is checked manually in Task 1.
5. **Saving the profile with the email field cleared** should store `null`,
   not `''`. A second user doing the same must not hit a unique-index error.
   This is pinned in Task 5.

---

### Task 1: Grouped sidebar with prefix-matched active item

**Files:**
- Modify: `resources/js/types/index.ts`, the `NavGroup` interface
- Modify: `resources/js/components/nav-main.tsx`, a full rewrite
- Modify: `resources/js/components/app-sidebar.tsx:31-191`
- Modify: `resources/js/components/app-sidebar-header.tsx:9`
- Delete: `resources/js/components/app-header.tsx`,
  `resources/js/layouts/app/app-header-layout.tsx`. Both are unused; the only
  importer of `app-header` is `app-header-layout`, which nothing imports.

**Interfaces:**
- Produces: `NavGroup { title: string; items: NavItem[]; collapsible?: boolean }`,
  `NavMain({ groups }: { groups: NavGroup[] })`, and
  `isActiveUrl(itemUrl: string, pageUrl: string): boolean`, exported from
  `nav-main.tsx`.

- [ ] **Step 1: Extend `NavGroup` in `resources/js/types/index.ts`**

```ts
export interface NavGroup {
    title: string;
    items: NavItem[];
    /** Folded by default; opens on its own when it holds the current page. */
    collapsible?: boolean;
}
```

- [ ] **Step 2: Rewrite `resources/js/components/nav-main.tsx`**

```tsx
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type NavGroup, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';

/**
 * An item is active on its own page and on every page beneath it, so
 * /orders/5/edit lights up অর্ডার. The query string is ignored; /orders-x is not
 * beneath /orders.
 */
export function isActiveUrl(itemUrl: string, pageUrl: string): boolean {
    const path = pageUrl.split('?')[0];

    return path === itemUrl || path.startsWith(`${itemUrl}/`);
}

// Folded state is a per-browser convenience. Storage can be blocked or
// cleared, so every access is guarded and the menu works without it.
function readOpen(key: string): boolean | null {
    try {
        const value = window.localStorage.getItem(key);

        return value === null ? null : value === '1';
    } catch {
        return null;
    }
}

function writeOpen(key: string, open: boolean): void {
    try {
        window.localStorage.setItem(key, open ? '1' : '0');
    } catch {
        // Storage blocked; the group still toggles for this page view.
    }
}

function Items({ items, pageUrl }: { items: NavItem[]; pageUrl: string }) {
    return (
        <SidebarMenu>
            {items.map((item) => (
                <SidebarMenuItem key={item.url}>
                    <SidebarMenuButton asChild isActive={isActiveUrl(item.url, pageUrl)} className="h-10">
                        <Link href={item.url} prefetch>
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            ))}
        </SidebarMenu>
    );
}

function FoldedGroup({ group, pageUrl }: { group: NavGroup; pageUrl: string }) {
    const storageKey = `sidebar-group:${group.title}`;
    const holdsCurrentPage = group.items.some((item) => isActiveUrl(item.url, pageUrl));
    const [open, setOpen] = useState(() => holdsCurrentPage || (readOpen(storageKey) ?? false));

    const toggle = (next: boolean) => {
        setOpen(next);
        writeOpen(storageKey, next);
    };

    return (
        <Collapsible open={open} onOpenChange={toggle} asChild>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel asChild>
                    <CollapsibleTrigger className="flex h-10 w-full items-center">
                        {group.title}
                        <ChevronRight className={cn('ml-auto size-4 transition-transform', open && 'rotate-90')} />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent>
                    <Items items={group.items} pageUrl={pageUrl} />
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const { url } = usePage();

    return (
        <>
            {groups.map((group) =>
                group.collapsible ? (
                    <FoldedGroup key={group.title} group={group} pageUrl={url} />
                ) : (
                    <SidebarGroup key={group.title} className="px-2 py-0">
                        <SidebarGroupLabel>{group.title}</SidebarGroupLabel>
                        <Items items={group.items} pageUrl={url} />
                    </SidebarGroup>
                ),
            )}
        </>
    );
}
```

- [ ] **Step 3: Replace the flat list in `app-sidebar.tsx` with groups**

Replace the doc comment, the `navItems` array and the body of `AppSidebar`
(lines 31-191). Keep the existing lucide imports and add no new icons.

```tsx
type GuardedItem = NavItem & { permission?: string };

/**
 * Nav entries carry the permission that guards their route. Hiding an item is
 * a convenience; the route enforces the same permission server-side.
 *
 * Daily work comes first and is always open. Setup is touched rarely, so it
 * folds away.
 */
const navGroups: { title: string; collapsible?: boolean; items: GuardedItem[] }[] = [
    {
        title: 'দৈনিক কাজ',
        items: [
            { title: 'ড্যাশবোর্ড', url: '/dashboard', icon: LayoutGrid },
            { title: 'অর্ডার', url: '/orders', icon: ClipboardList, permission: 'orders.view' },
            { title: 'হাজিরা', url: '/attendance', icon: CalendarCheck, permission: 'attendance.view' },
            { title: 'কেনাকাটা', url: '/purchases', icon: PackagePlus, permission: 'purchases.view' },
            { title: 'খরচ', url: '/expenses', icon: Banknote, permission: 'expenses.view' },
            { title: 'দিন শেষের হিসাব', url: '/daily-closing', icon: CalendarClock, permission: 'daily_closing.view' },
        ],
    },
    {
        title: 'পাওনা-দেনা',
        items: [
            { title: 'কর্মীর পাওনা', url: '/employee-ledger', icon: HandCoins, permission: 'employee_ledger.view' },
            { title: 'সরবরাহকারীর পাওনা', url: '/supplier-ledger', icon: Coins, permission: 'supplier_ledger.view' },
            { title: 'খরচের রিপোর্ট', url: '/reports/spending', icon: PieChart, permission: 'reports.financial' },
        ],
    },
    {
        title: 'মালামাল ও পণ্য',
        items: [
            { title: 'মালামালের মজুদ', url: '/stock', icon: Warehouse, permission: 'stock.view' },
            { title: 'মালামালের তালিকা', url: '/materials', icon: Boxes, permission: 'materials.view' },
            { title: 'পণ্য', url: '/products', icon: Armchair, permission: 'products.view' },
        ],
    },
    {
        title: 'সেটআপ',
        collapsible: true,
        items: [
            { title: 'কাস্টমার', url: '/customers', icon: UserRound, permission: 'customers.view' },
            { title: 'সরবরাহকারী', url: '/suppliers', icon: Truck, permission: 'suppliers.view' },
            { title: 'কর্মী', url: '/employees', icon: HardHat, permission: 'employees.view' },
            { title: 'দোকান', url: '/shops', icon: Store, permission: 'shops.view' },
            { title: 'কাজের ধরন', url: '/trades', icon: Hammer, permission: 'trades.view' },
            { title: 'ক্যাশ ও ব্যাংক', url: '/accounts', icon: Wallet, permission: 'accounts.view' },
            { title: 'খরচের খাত', url: '/expense-categories', icon: Receipt, permission: 'expense_categories.view' },
            { title: 'পণ্যের ক্যাটাগরি', url: '/product-categories', icon: Shapes, permission: 'product_categories.view' },
            { title: 'ব্যবহারকারী', url: '/users', icon: Users, permission: 'users.manage' },
        ],
    },
];

export function AppSidebar() {
    const { can } = usePermission();

    // A group whose every item is hidden is not rendered at all.
    const groups: NavGroup[] = navGroups
        .map((group) => ({ ...group, items: group.items.filter((item) => !item.permission || can(item.permission)) }))
        .filter((group) => group.items.length > 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={groups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
```

Change the type import line to
`import { type NavGroup, type NavItem } from '@/types';`.

- [ ] **Step 4: Enlarge the phone menu button in `app-sidebar-header.tsx:9`**

```tsx
<SidebarTrigger className="-ml-1 size-11 md:size-7" />
```

The `cn()` in `SidebarTrigger` merges this class over the default `h-7 w-7`.

- [ ] **Step 5: Delete the unused starter header**

```bash
git rm resources/js/components/app-header.tsx resources/js/layouts/app/app-header-layout.tsx
grep -rn "app-header'\|app-header-layout" resources/js   # expect no output
```

- [ ] **Step 6: Typecheck, lint, build**

```bash
npx tsc --noEmit -p .
npx eslint resources/js/components/nav-main.tsx resources/js/components/app-sidebar.tsx resources/js/components/app-sidebar-header.tsx resources/js/types/index.ts
npm run build
```

Expected: no errors. If `tsc` reports `NavGroup`'s use elsewhere, there is
none today; fix whatever it names.

- [ ] **Step 7: Manual check**

With `php artisan serve` and `npm run dev` running, log in as the owner and
check:
- four group headings appear
- সেটআপ is folded
- opening `/customers/create` directly shows সেটআপ expanded with কাস্টমার
  highlighted
- `/orders/1` highlights অর্ডার
- with the window narrowed to 390px, the menu button is large

If the Chrome extension is not connected, say so and fall back to checking the
built output with `curl` for HTTP 200 on `/dashboard`.

- [ ] **Step 8: Commit**

```bash
git add resources/js/types/index.ts resources/js/components/nav-main.tsx resources/js/components/app-sidebar.tsx resources/js/components/app-sidebar-header.tsx
git commit -m "feat: group the sidebar by daily work, dues, stock and setup"
```

(The `git rm` from Step 5 is already staged.)

---

### Task 2: Renamed labels on page titles, headings and breadcrumbs

**Files:** each line below is a modification.

| File | Line(s) | From | To |
|---|---|---|---|
| `resources/js/pages/daily-closing/index.tsx` | 53, 82, 86 | দিনের হিসাব | দিন শেষের হিসাব |
| `resources/js/pages/employee-ledger/index.tsx` | 30, 56, 60 | কর্মীর হিসাব | কর্মীর পাওনা |
| `resources/js/pages/employee-ledger/show.tsx` | 77 | কর্মীর হিসাব | কর্মীর পাওনা |
| `resources/js/pages/supplier-ledger/index.tsx` | 44, 78, 82 | সরবরাহকারীর হিসাব | সরবরাহকারীর পাওনা |
| `resources/js/pages/supplier-ledger/show.tsx` | 92 | সরবরাহকারীর হিসাব | সরবরাহকারীর পাওনা |
| `resources/js/pages/reports/spending.tsx` | 51, 121, 125 | সব খরচের হিসাব | খরচের রিপোর্ট |
| `resources/js/pages/stock/index.tsx` | 65, 130, 134 | গুদাম | মালামালের মজুদ |
| `resources/js/pages/materials/index.tsx` | 67 | `title="মালামাল"` | `title="মালামালের তালিকা"` |
| `resources/js/pages/accounts/index.tsx` | 44 | `title="হিসাব"` | `title="ক্যাশ ও ব্যাংক"` |

Leave the sentences that merely contain the old words alone. These are
`daily-closing/index.tsx:132,219` ("এই দিনের হিসাব আগেই বন্ধ করা হয়েছে",
"দিনের হিসাব বন্ধ করুন"), `employee-ledger/show.tsx:184` and
`orders/item-works.tsx:185`. They describe an action, not the page name.

`reports/spending.tsx` is part of the human partner's uncommitted work. If it
is still untracked when you reach this task, make the edit but leave it out of
the commit, and mention that in your report.

- [ ] **Step 1: Make the edits in the table, nothing else**

Also check whether `materials/index.tsx` and `accounts/index.tsx` pass the
same string as a breadcrumb or create-page title through `master-data-page`.
Run `grep -n "মালামাল\|'হিসাব'" resources/js/pages/materials resources/js/pages/accounts -r`
and rename only the page-name uses.

- [ ] **Step 2: Check no page-name use of an old label remains**

```bash
grep -rnE "title[=:] ?['\"](দিনের হিসাব|কর্মীর হিসাব|সরবরাহকারীর হিসাব|সব খরচের হিসাব|গুদাম|মালামাল|হিসাব)['\"]|>(দিনের হিসাব|কর্মীর হিসাব|সরবরাহকারীর হিসাব|সব খরচের হিসাব|গুদাম)<" resources/js/pages
```

Expected: no output.

- [ ] **Step 3: Typecheck and run the page tests that render these screens**

```bash
npx tsc --noEmit -p .
php artisan test tests/Feature/Cash tests/Feature/Reports tests/Feature/MasterData
```

Expected: all pass. These tests assert Inertia props, not titles, so nothing
should change.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/daily-closing/index.tsx resources/js/pages/employee-ledger/index.tsx resources/js/pages/employee-ledger/show.tsx resources/js/pages/supplier-ledger/index.tsx resources/js/pages/supplier-ledger/show.tsx resources/js/pages/stock/index.tsx resources/js/pages/materials/index.tsx resources/js/pages/accounts/index.tsx
# add resources/js/pages/reports/spending.tsx only if it is already tracked
git commit -m "fix: page titles match the renamed menu labels"
```

---

### Task 3: `DashboardToday` query and controller wiring

**Files:**
- Create: `app/Queries/DashboardToday.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/DashboardTest.php` (append, and extend the existing
  "no role" test)

**Interfaces:**
- Produces the Inertia prop `today`:
  ```
  today: {
    attendance: { marked: int, total: int } | null,
    closing: list<{ shop_id: int, name: string, closed: bool }> | null,
    late_orders: list<{ id: int, order_no: ?string, customer_name: ?string, days_late: int }> | null,
  }
  ```
- PHP methods: `DashboardToday::attendance(?string $onDate = null): array`,
  `closing(User $user, ?string $onDate = null): array`,
  `lateOrders(?string $onDate = null): array`.

- [ ] **Step 1: Write the failing tests**

Add to the imports at the top of `tests/Feature/DashboardTest.php`:

```php
use App\Models\Attendance;
use App\Models\DailyClosing;
use App\Models\Shop;
```

In the existing test `it('shows a user with no role a page rather than a broken screen', ...)`,
add these lines to the assertion chain after `->where('stock', null)`:

```php
            ->where('today.attendance', null)
            ->where('today.closing', null)
            ->where('today.late_orders', null)
```

Append at the end of the file:

```php
it('counts today\'s attendance against active workers only', function () {
    $today = now()->toDateString();

    $marked = Employee::factory()->count(2)->create();
    Employee::factory()->create(); // active, not yet marked
    $turnedOff = Employee::factory()->create(['is_active' => false]);
    $gone = Employee::factory()->create();

    foreach ($marked as $employee) {
        Attendance::factory()->create(['employee_id' => $employee->id, 'work_date' => $today]);
    }
    // Marked earlier, then turned off or removed: neither may push marked past total.
    Attendance::factory()->create(['employee_id' => $turnedOff->id, 'work_date' => $today]);
    Attendance::factory()->create(['employee_id' => $gone->id, 'work_date' => $today]);
    $gone->delete();
    // Yesterday's mark is not today's.
    Attendance::factory()->create(['employee_id' => $marked[0]->id, 'work_date' => now()->subDay()->toDateString()]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('today.attendance.marked', 2)
            ->where('today.attendance.total', 3)
        );
});

it('shows every active shop\'s closing to a reader with no shop of their own', function () {
    $closed = Shop::factory()->create(['name' => 'A Shop']);
    $open = Shop::factory()->create(['name' => 'B Shop']);
    Shop::factory()->create(['name' => 'C Shop', 'is_active' => false]);

    DailyClosing::factory()->create(['shop_id' => $closed->id, 'closing_date' => now()->toDateString(), 'closed_at' => now()]);
    // A row with no closed_at is a started day, not a closed one.
    DailyClosing::factory()->create(['shop_id' => $open->id, 'closing_date' => now()->toDateString(), 'closed_at' => null]);
    // Yesterday's closing does not close today.
    DailyClosing::factory()->create(['shop_id' => $open->id, 'closing_date' => now()->subDay()->toDateString(), 'closed_at' => now()->subDay()]);

    $this->owner->update(['shop_id' => null]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('today.closing', 2)
            ->where('today.closing.0.shop_id', $closed->id)
            ->where('today.closing.0.closed', true)
            ->where('today.closing.1.shop_id', $open->id)
            ->where('today.closing.1.closed', false)
        );
});

it('shows only the reader\'s own shop, even when that shop is switched off', function () {
    Shop::factory()->create(['name' => 'A Shop']);
    $mine = Shop::factory()->create(['name' => 'B Shop', 'is_active' => false]);

    $this->owner->update(['shop_id' => $mine->id]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('today.closing', 1)
            ->where('today.closing.0.shop_id', $mine->id)
            ->where('today.closing.0.closed', false)
        );
});

it('lists the five most overdue open orders, oldest first', function () {
    foreach ([1, 2, 3, 4, 5, 6] as $days) {
        Order::factory()->confirmed()->create(['expected_delivery_date' => now()->subDays($days)->toDateString()]);
    }
    Order::factory()->confirmed()->create(['status' => OrderStatus::Delivered, 'expected_delivery_date' => now()->subDays(30)->toDateString()]);
    Order::factory()->confirmed()->create(['status' => OrderStatus::Cancelled, 'expected_delivery_date' => now()->subDays(40)->toDateString()]);
    // Due today is not late yet.
    Order::factory()->confirmed()->create(['expected_delivery_date' => now()->toDateString()]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('today.late_orders', 5)
            ->where('today.late_orders.0.days_late', 6)
            ->where('today.late_orders.4.days_late', 2)
            ->has('today.late_orders.0.customer_name')
        );
});

it('lists a late draft order without a number', function () {
    Order::factory()->create(['expected_delivery_date' => now()->subDays(3)->toDateString()]); // draft, order_no null

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('today.late_orders', 1)
            ->where('today.late_orders.0.order_no', null)
            ->where('today.late_orders.0.days_late', 3)
        );
});

it('leaves out the today blocks a storekeeper may not see', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('today.attendance', null)
            ->where('today.closing', null)
            ->where('today.late_orders', [])
        );
});
```

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `php artisan test tests/Feature/DashboardTest.php`

Expected: the new tests and the extended "no role" test FAIL, with a message
like `Property [today] does not exist`. The older tests still PASS.

- [ ] **Step 3: Create `app/Queries/DashboardToday.php`**

```php
<?php

namespace App\Queries;

use App\Models\Attendance;
use App\Models\DailyClosing;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * What still needs doing today, for the checklist at the top of the dashboard.
 *
 * Kept apart from DashboardSummary because these answer "is it done?" rather
 * than "how much?". Each block is its own method so the controller can skip
 * the ones the reader has no permission for.
 */
class DashboardToday
{
    /**
     * Workers marked today out of the workers who should be.
     *
     * Both sides count only active, not-deleted employees, so a worker marked
     * this morning and turned off this afternoon cannot make marked exceed total.
     *
     * @return array{marked: int, total: int}
     */
    public function attendance(?string $onDate = null): array
    {
        $date = $this->date($onDate);

        return [
            'marked' => Attendance::query()
                ->where('work_date', $date)
                ->whereHas('employee', fn ($query) => $query->where('is_active', true))
                ->count(),
            'total' => Employee::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * Whether each shop in the reader's scope has closed the day.
     *
     * A reader tied to a shop sees that shop even if it has since been
     * switched off; their own closing must not vanish from the list. A reader
     * with no shop sees every active shop.
     *
     * @return list<array{shop_id: int, name: string, closed: bool}>
     */
    public function closing(User $user, ?string $onDate = null): array
    {
        $date = $this->date($onDate);

        $shops = $user->shop_id
            ? Shop::withTrashed()->whereKey($user->shop_id)->get(['id', 'name'])
            : Shop::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $closed = DailyClosing::query()
            ->whereIn('shop_id', $shops->pluck('id'))
            ->where('closing_date', $date)
            ->whereNotNull('closed_at')
            ->pluck('shop_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $shops
            ->map(fn (Shop $shop) => [
                'shop_id' => $shop->id,
                'name' => $shop->name,
                'closed' => in_array($shop->id, $closed, true),
            ])
            ->values()
            ->all();
    }

    /**
     * The open orders furthest past their promised date, at most five.
     *
     * "Open" is the same scope the late-delivery tile counts, so the list and
     * the tile never disagree about which orders are late.
     *
     * @return list<array{id: int, order_no: ?string, customer_name: ?string, days_late: int}>
     */
    public function lateOrders(?string $onDate = null): array
    {
        $date = $this->date($onDate);

        return Order::query()
            ->open()
            ->whereNotNull('expected_delivery_date')
            ->where('expected_delivery_date', '<', $date)
            ->with('customer:id,name')
            ->select(['id', 'order_no', 'customer_id'])
            ->selectRaw('DATEDIFF(?, expected_delivery_date) AS days_late', [$date])
            ->orderBy('expected_delivery_date')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'customer_name' => $order->customer?->name,
                'days_late' => (int) $order->days_late,
            ])
            ->all();
    }

    private function date(?string $onDate): string
    {
        return $onDate ?? CarbonImmutable::today()->toDateString();
    }
}
```

- [ ] **Step 4: Wire it into `DashboardController`**

Replace the constructor and the `Inertia::render` call:

```php
    public function __construct(
        private readonly DashboardSummary $summary,
        private readonly DashboardToday $today,
    ) {}
```

```php
        return Inertia::render('dashboard', [
            'cash' => $user->can('transactions.view') ? $this->summary->cash() : null,
            'orders' => $user->can('orders.view') ? $this->summary->orders() : null,
            'payable' => $user->can('supplier_ledger.view') ? $this->summary->payable() : null,
            'labour' => $user->can('employee_ledger.view') ? $this->summary->labour() : null,
            'stock' => $user->can('stock.view') ? $this->summary->stock() : null,
            'today' => [
                'attendance' => $user->can('attendance.view') ? $this->today->attendance() : null,
                'closing' => $user->can('daily_closing.view') ? $this->today->closing($user) : null,
                'late_orders' => $user->can('orders.view') ? $this->today->lateOrders() : null,
            ],
        ]);
```

Add `use App\Queries\DashboardToday;` to the imports.

- [ ] **Step 5: Run the tests and confirm they pass**

Run: `php artisan test tests/Feature/DashboardTest.php`

Expected: all PASS.

- If the owner role lacks `attendance.view` or `daily_closing.view`, which
  is unlikely, check `database/seeders/RolePermissionSeeder.php` and report
  it rather than changing the seeder.
- If `Shop::withTrashed()` errors, check that `Shop` uses `SoftDeletes`
  (`app/Models/Shop.php:14` confirms it does).

- [ ] **Step 6: Commit**

```bash
git add app/Queries/DashboardToday.php app/Http/Controllers/DashboardController.php tests/Feature/DashboardTest.php
git commit -m "feat: dashboard knows what is still to do today"
```

---

### Task 4: Dashboard quick actions and today checklist (UI)

**Files:**
- Create: `resources/js/components/dashboard/quick-actions.tsx`
- Create: `resources/js/components/dashboard/today-checklist.tsx`
- Modify: `resources/js/pages/dashboard.tsx`

**Interfaces:**
- Consumes: the `today` prop from Task 3, `usePermission()` from
  `@/hooks/use-permission`, `toBengaliDigits` from `@/components/data-table`,
  and the Ziggy `route()` global.
- Produces: `QuickActions()` (no props) and
  `TodayChecklist({ today, lateTotal }: { today: TodayData; lateTotal: number })`.
  `TodayData` is exported from `today-checklist.tsx`.

- [ ] **Step 1: Create `quick-actions.tsx`**

```tsx
import { usePermission } from '@/hooks/use-permission';
import { Link } from '@inertiajs/react';
import { Banknote, CalendarCheck, CalendarClock, ClipboardList, PackagePlus, type LucideIcon } from 'lucide-react';

interface Action {
    label: string;
    href: string;
    icon: LucideIcon;
    permission: string;
}

/**
 * The jobs people open the app to do, one tap away. A button shows only when
 * the reader may perform that action; the route checks it again.
 */
export function QuickActions() {
    const { can } = usePermission();

    const actions: Action[] = [
        { label: 'নতুন অর্ডার', href: route('orders.create'), icon: ClipboardList, permission: 'orders.manage' },
        { label: 'হাজিরা নিন', href: route('attendance.index'), icon: CalendarCheck, permission: 'attendance.mark' },
        { label: 'কেনাকাটা', href: route('purchases.create'), icon: PackagePlus, permission: 'purchases.record' },
        { label: 'খরচ', href: route('expenses.create'), icon: Banknote, permission: 'expenses.record' },
        { label: 'দিন শেষের হিসাব', href: route('daily-closing.index'), icon: CalendarClock, permission: 'daily_closing.run' },
    ].filter((action) => can(action.permission));

    if (actions.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-2">
            <h2 className="text-lg font-semibold">আজ কী করবেন?</h2>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                {actions.map(({ label, href, icon: Icon }) => (
                    <Link
                        key={href}
                        href={href}
                        className="bg-primary text-primary-foreground hover:bg-primary/90 flex min-h-14 items-center gap-2 rounded-xl px-4 py-3 text-base font-medium transition-colors"
                    >
                        <Icon className="size-5 shrink-0" />
                        {label}
                    </Link>
                ))}
            </div>
        </section>
    );
}
```

- [ ] **Step 2: Create `today-checklist.tsx`**

```tsx
import { toBengaliDigits } from '@/components/data-table';
import { Link } from '@inertiajs/react';
import { ChevronRight, CircleCheck, CircleX, TriangleAlert } from 'lucide-react';
import { ReactNode } from 'react';

export interface TodayData {
    /** Each block is null when the reader holds no permission for it. */
    attendance: { marked: number; total: number } | null;
    closing: { shop_id: number; name: string; closed: boolean }[] | null;
    late_orders: { id: number; order_no: string | null; customer_name: string | null; days_late: number }[] | null;
}

type Tone = 'done' | 'todo' | 'warn';

const toneIcon: Record<Tone, ReactNode> = {
    done: <CircleCheck className="size-5 shrink-0 text-emerald-600" />,
    todo: <CircleX className="text-destructive size-5 shrink-0" />,
    warn: <TriangleAlert className="size-5 shrink-0 text-amber-600" />,
};

function Row({ tone, children, href, action }: { tone: Tone; children: ReactNode; href?: string; action?: string }) {
    return (
        <li className="flex min-h-12 items-center gap-3 px-4 py-2">
            {toneIcon[tone]}
            <span className="flex-1">{children}</span>
            {href && action && (
                <Link href={href} className="text-primary flex min-h-11 items-center gap-1 font-medium">
                    {action}
                    <ChevronRight className="size-4" />
                </Link>
            )}
        </li>
    );
}

/**
 * Today's unfinished jobs, each with a link straight to the screen that
 * finishes it. Renders nothing when there is nothing the reader may see.
 */
export function TodayChecklist({ today, lateTotal }: { today: TodayData; lateTotal: number }) {
    const { attendance, closing, late_orders } = today;

    const showAttendance = attendance !== null && attendance.total > 0;
    const showClosing = closing !== null && closing.length > 0;
    const showLate = late_orders !== null && late_orders.length > 0;

    if (!showAttendance && !showClosing && !showLate) {
        return null;
    }

    return (
        <section className="flex flex-col gap-2">
            <h2 className="text-lg font-semibold">আজকের কাজ</h2>
            <ul className="divide-y rounded-xl border">
                {showAttendance &&
                    (attendance.marked >= attendance.total ? (
                        <Row tone="done">
                            হাজিরা নেওয়া হয়েছে ({toBengaliDigits(attendance.marked)}/{toBengaliDigits(attendance.total)})
                        </Row>
                    ) : (
                        <Row tone="todo" href={route('attendance.index')} action="হাজিরা নিন">
                            হাজিরা বাকি ({toBengaliDigits(attendance.marked)}/{toBengaliDigits(attendance.total)})
                        </Row>
                    ))}

                {showClosing &&
                    closing.map((shop) =>
                        shop.closed ? (
                            <Row key={shop.shop_id} tone="done">
                                দিন শেষের হিসাব বন্ধ হয়েছে{closing.length > 1 && ` — ${shop.name}`}
                            </Row>
                        ) : (
                            <Row key={shop.shop_id} tone="todo" href={route('daily-closing.index', { shop_id: shop.shop_id })} action="এখন করুন">
                                দিন শেষের হিসাব বাকি{closing.length > 1 && ` — ${shop.name}`}
                            </Row>
                        ),
                    )}

                {showLate && (
                    <li className="flex flex-col">
                        <Row tone="warn" href={route('orders.index', { status: 'open' })} action="সব দেখুন">
                            {toBengaliDigits(lateTotal)}টি অর্ডারের ডেলিভারি পেছিয়েছে
                        </Row>
                        <ul className="pb-2">
                            {late_orders.map((order) => (
                                <li key={order.id}>
                                    <Link
                                        href={route('orders.show', order.id)}
                                        className="hover:bg-muted/50 flex min-h-11 items-center gap-3 py-2 pr-4 pl-12 text-sm"
                                    >
                                        <span className="font-medium">{order.order_no ?? 'খসড়া'}</span>
                                        <span className="text-muted-foreground flex-1 truncate">{order.customer_name ?? ''}</span>
                                        <span className="text-destructive">{toBengaliDigits(order.days_late)} দিন দেরি</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </li>
                )}
            </ul>
        </section>
    );
}
```

Check that `route('orders.show', id)` exists with
`php artisan route:list --name=orders.show`. If the name differs, use the
name it prints.

- [ ] **Step 3: Mount both on `pages/dashboard.tsx`**

Add the imports:

```tsx
import { QuickActions } from '@/components/dashboard/quick-actions';
import { TodayChecklist, type TodayData } from '@/components/dashboard/today-checklist';
```

Add `today: TodayData;` to `interface Props`, and add `today` to the
destructured props of `Dashboard`.

Directly after `<FlashMessages />`, insert:

```tsx
                <QuickActions />
                <TodayChecklist today={today} lateTotal={orders?.late_delivery ?? 0} />
```

- [ ] **Step 4: Typecheck, lint, build**

```bash
npx tsc --noEmit -p .
npx eslint resources/js/components/dashboard resources/js/pages/dashboard.tsx
npm run build
```

Expected: no errors.

- [ ] **Step 5: Run the dashboard tests again**

Run: `php artisan test tests/Feature/DashboardTest.php`

Expected: all pass.

- [ ] **Step 6: Manual check**

Log in as the owner and look at `/dashboard`. You should see:
- the buttons row
- the checklist, showing attendance, closing and any late orders
- the six tiles below
- at 390px width, buttons in two columns and nothing overflowing sideways

Use Chrome if connected; otherwise `curl` `/dashboard` with a session and
confirm a 200.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/dashboard resources/js/pages/dashboard.tsx
git commit -m "feat: dashboard quick actions and today checklist"
```

---

### Task 5: Profile email optional and self-delete removed

**Files:**
- Modify: `app/Http/Requests/Settings/ProfileUpdateRequest.php:22-29`
- Modify: `app/Http/Controllers/Settings/ProfileController.php`: delete the
  `destroy` method and the now-unused `Auth` import
- Modify: `routes/settings.php:13`: delete the `profile.destroy` route
- Delete: `resources/js/components/delete-user.tsx`
- Modify: `resources/js/pages/settings/profile.tsx`: remove the `DeleteUser`
  import and usage, and the `required` attribute on the email input
- Test: `tests/Feature/Settings/ProfileUpdateTest.php`

- [ ] **Step 1: Replace the two self-delete tests and add the email tests**

In `tests/Feature/Settings/ProfileUpdateTest.php`, delete the methods
`test_user_can_delete_their_account` and
`test_correct_password_must_be_provided_to_delete_account`. Add:

```php
    public function test_profile_can_be_saved_without_an_email()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/profile', ['name' => 'রহিম', 'email' => ''])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertSame('রহিম', $user->refresh()->name);
        $this->assertNull($user->email);
    }

    public function test_two_users_can_both_leave_email_empty()
    {
        $first = User::factory()->create(['email' => null]);
        $second = User::factory()->create();

        $this->actingAs($second)
            ->patch('/settings/profile', ['name' => $second->name, 'email' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($first->refresh()->email);
        $this->assertNull($second->refresh()->email);
    }

    public function test_an_email_already_taken_is_still_rejected()
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/profile', ['name' => $user->name, 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_staff_cannot_delete_their_own_account()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertNotNull($user->fresh());
    }
```

- [ ] **Step 2: Run them and confirm they fail**

Run: `php artisan test tests/Feature/Settings/ProfileUpdateTest.php`

Expected:
- `..._without_an_email` and `..._both_leave_email_empty` FAIL with a session
  error on `email`.
- `test_staff_cannot_delete_their_own_account` FAILS because it got a
  redirect, not a 405.

- [ ] **Step 3: Make email nullable**

In `ProfileUpdateRequest::rules()`, replace `'required',` in the `email`
array with `'nullable',`. The other rules stay. Laravel's
`ConvertEmptyStringsToNull` middleware turns `''` into `null` before
validation, so `null` is what gets saved.

- [ ] **Step 4: Remove self-delete on the server**

- Delete line 13 of `routes/settings.php`:
  `Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');`
- Delete the `destroy()` method from `ProfileController`, and the
  `use Illuminate\Support\Facades\Auth;` import, which only `destroy` used.

- [ ] **Step 5: Remove self-delete on the client**

```bash
git rm resources/js/components/delete-user.tsx
grep -n "DeleteUser\|delete-user" resources/js/pages/settings/profile.tsx
```

Remove the import line and the `<DeleteUser />` element the grep shows. On
the email `<Input>`, remove the `required` attribute if it has one.

- [ ] **Step 6: Run the tests and confirm they pass**

Run: `php artisan test tests/Feature/Settings`

Expected: all PASS.

- [ ] **Step 7: Typecheck and commit**

```bash
npx tsc --noEmit -p .
git add app/Http/Requests/Settings/ProfileUpdateRequest.php app/Http/Controllers/Settings/ProfileController.php routes/settings.php resources/js/pages/settings/profile.tsx tests/Feature/Settings/ProfileUpdateTest.php
git commit -m "fix: email is optional on the profile; staff cannot delete their own account"
```

---

### Task 6: Bengali on settings, sign-in pages, user menu and screen-reader text; app name

**Files:** each item below is a modification.
- `resources/js/pages/settings/profile.tsx`, `password.tsx`, `appearance.tsx`
- `resources/js/layouts/settings/layout.tsx`
- `resources/js/components/appearance-tabs.tsx`
- `resources/js/components/user-menu-content.tsx`
- `resources/js/pages/auth/forgot-password.tsx`, `reset-password.tsx`,
  `confirm-password.tsx`, `verify-email.tsx`
- `resources/js/components/ui/sidebar.tsx`, lines 175, 246, 260 and 263
- `resources/js/components/ui/sheet.tsx`, line 59
- `resources/js/components/app-logo.tsx`, line 14
- `.env.example`, line 1. Also edit `.env` line 1, but `.env` is not
  committed.

- [ ] **Step 1: Replace strings exactly as listed**

Leave the code around each string unchanged.

`pages/settings/profile.tsx`

| Line | English | Bengali |
|---|---|---|
| 17, 38 | Profile settings | প্রোফাইল |
| 42 title | Profile information | আপনার তথ্য |
| 42 description | Update your name and email address | নাম ও ইমেইল বদলান |
| 46 | Name | নাম |
| 55 | Full name | পুরো নাম |
| 62 | Email address | ইমেইল (না দিলেও চলবে) |
| 72 | Email address | ইমেইল |
| 81 | Your email address is unverified. | আপনার ইমেইল এখনো যাচাই হয়নি। |
| 88 | Click here to re-send the verification email. | যাচাইয়ের ইমেইল আবার পাঠাতে এখানে চাপুন। |
| 94 | A new verification link has been sent to your email address. | আপনার ইমেইলে নতুন যাচাই লিংক পাঠানো হয়েছে। |
| 101 | Save | সংরক্ষণ করুন |
| 110 | Saved | সংরক্ষণ হয়েছে |

`pages/settings/password.tsx`

| Line | English | Bengali |
|---|---|---|
| 16 | Password settings | পাসওয়ার্ড |
| 53 | Profile settings | পাসওয়ার্ড |
| 57 title | Update password | পাসওয়ার্ড বদলান |
| 57 description | Ensure your account is using a long, random password to stay secure | নিরাপদ রাখতে লম্বা ও সহজে আন্দাজ করা যায় না এমন পাসওয়ার্ড দিন |
| 61, 71 | Current password | এখনকার পাসওয়ার্ড |
| 78, 88 | New password | নতুন পাসওয়ার্ড |
| 95, 104 | Confirm password | নতুন পাসওয়ার্ড আবার লিখুন |
| 111 | Save password | পাসওয়ার্ড সংরক্ষণ করুন |
| 120 | Saved | সংরক্ষণ হয়েছে |

`pages/settings/appearance.tsx`

| Line | English | Bengali |
|---|---|---|
| 12, 20, 24 title | Appearance settings | দেখতে কেমন |
| 24 description | Update your account's appearance settings | হালকা না গাঢ় রঙে দেখবেন বেছে নিন |

`layouts/settings/layout.tsx`

| Line | English | Bengali |
|---|---|---|
| 10 | Profile | প্রোফাইল |
| 15 | Password | পাসওয়ার্ড |
| 20 | Appearance | দেখতে কেমন |
| 31 title | Settings | সেটিংস |
| 31 description | Manage your profile and account settings | আপনার নাম, পাসওয়ার্ড ও দেখার ধরন |

`components/appearance-tabs.tsx`

| Line | English | Bengali |
|---|---|---|
| 10 | Light | হালকা |
| 11 | Dark | গাঢ় |
| 12 | System | ফোন/কম্পিউটারের মতো |

`components/user-menu-content.tsx`

| Line | English | Bengali |
|---|---|---|
| 27 | Settings | সেটিংস |
| 35 | Log out | লগ আউট |

`pages/auth/forgot-password.tsx`

| Line | English | Bengali |
|---|---|---|
| 25 title, 26 | Forgot password | পাসওয়ার্ড ভুলে গেছেন |
| 25 description | Enter your email to receive a password reset link | ইমেইল দিন, পাসওয়ার্ড নতুন করার লিংক পাঠানো হবে |
| 33 | Email address | ইমেইল |
| 51 | Email password reset link | লিংক পাঠান |
| 57 | Or, return to | অথবা ফিরে যান |
| 58 | log in | লগইন পাতায় |

`pages/auth/reset-password.tsx`

| Line | English | Bengali |
|---|---|---|
| 40 title, 41, 93 | Reset password | নতুন পাসওয়ার্ড দিন |
| 40 description | Please enter your new password below | নিচে নতুন পাসওয়ার্ড লিখুন |
| 46 | Email | ইমেইল |
| 61, 71 | Password | নতুন পাসওয়ার্ড |
| 77, 86 | Confirm password | নতুন পাসওয়ার্ড আবার লিখুন |

`pages/auth/confirm-password.tsx`

| Line | English | Bengali |
|---|---|---|
| 27 | Confirm your password | পাসওয়ার্ড দিন |
| 28 | This is a secure area of the application. Please confirm your password before continuing. | এই অংশে ঢুকতে আরেকবার পাসওয়ার্ড দিন। |
| 30, 53 | Confirm password | নিশ্চিত করুন |
| 35, 40 | Password | পাসওয়ার্ড |

`pages/auth/verify-email.tsx`

| Line | English | Bengali |
|---|---|---|
| 20 title | Verify email | ইমেইল যাচাই |
| 20 description | Please verify your email address by clicking on the link we just emailed to you. | আপনার ইমেইলে পাঠানো লিংকে চাপ দিয়ে ইমেইল যাচাই করুন। |
| 21 | Email verification | ইমেইল যাচাই |
| 25 | A new verification link has been sent to the email address you provided during registration. | আপনার ইমেইলে নতুন যাচাই লিংক পাঠানো হয়েছে। |
| 32 | Resend verification email | আবার পাঠান |
| 36 | Log out | লগ আউট |

`components/ui/sidebar.tsx` and `components/ui/sheet.tsx`, screen-reader text
only:

| File:line | English | Bengali |
|---|---|---|
| sidebar.tsx:175 | Sidebar Navigation | মেনু |
| sidebar.tsx:246, 260, 263 | Toggle Sidebar | মেনু খুলুন বা বন্ধ করুন |
| sheet.tsx:59 | Close | বন্ধ করুন |

- [ ] **Step 2: Set the app name**

- In `.env.example` line 1 and `.env` line 1, set
  `APP_NAME="যতীন্দ্র ফার্নিচার"`.
- In `components/app-logo.tsx:14`, change the fallback `'জতীন্দ্র ফার্নিচার'`
  to `'যতীন্দ্র ফার্নিচার'`.

Then run `php artisan config:clear`.

`MAIL_FROM_NAME` and `VITE_APP_NAME` already read `${APP_NAME}`, so they pick
up the new value. Restart `npm run dev` so Vite sees the new `VITE_APP_NAME`.

- [ ] **Step 3: Server-side status strings**

Run `grep -n "sent\|reset\|throttled" lang/bn/passwords.php` and check that
every key Laravel uses (`reset`, `sent`, `throttled`, `token`, `user`) has a
Bengali value. These are what `status` shows on forgot-password and login.
Fill in any key that is missing or in English. If all are Bengali, there is
no change.

- [ ] **Step 4: Check no English UI text is left in the touched files**

```bash
grep -nE ">[^<{}]*[A-Za-z]{3,}[^<{}]*<|(title|description|placeholder|label)=\"[^\"]*[A-Za-z]{3,}" resources/js/pages/settings/*.tsx resources/js/layouts/settings/layout.tsx resources/js/components/appearance-tabs.tsx resources/js/components/user-menu-content.tsx resources/js/pages/auth/forgot-password.tsx resources/js/pages/auth/reset-password.tsx resources/js/pages/auth/confirm-password.tsx resources/js/pages/auth/verify-email.tsx
grep -n "sr-only\|aria-label=\|title=\"" resources/js/components/ui/sidebar.tsx resources/js/components/ui/sheet.tsx | grep -E "[A-Za-z]{4,}\"|>[A-Za-z ]{4,}<"
```

Expected: any remaining match is an email placeholder like
`email@example.com`, a code identifier, or a CSS class, not a word a user
reads.

- [ ] **Step 5: Typecheck, run the auth and settings tests, build**

```bash
npx tsc --noEmit -p .
php artisan test tests/Feature/Auth tests/Feature/Settings
npm run build
```

Expected: all pass. If a test asserts an English string in a response, update
the assertion to the Bengali text and mention it in your report.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/settings resources/js/layouts/settings/layout.tsx resources/js/components/appearance-tabs.tsx resources/js/components/user-menu-content.tsx resources/js/pages/auth/forgot-password.tsx resources/js/pages/auth/reset-password.tsx resources/js/pages/auth/confirm-password.tsx resources/js/pages/auth/verify-email.tsx resources/js/components/ui/sidebar.tsx resources/js/components/ui/sheet.tsx resources/js/components/app-logo.tsx .env.example
# add lang/bn/passwords.php only if Step 3 changed it
git commit -m "fix: Bengali on settings, sign-in pages and menus; one spelling of the shop name"
```

`.env.example` is in the human partner's uncommitted changes. Run
`git diff .env.example` before adding it. If it has other changes besides
`APP_NAME`, stage only the `APP_NAME` line with `git add -p .env.example`
(answer `y` only for that hunk). If `-p` is not available, because
interactive staging is not supported, leave `.env.example` out of the commit
and say so in your report.

---

### Task 7: Phone keyboards and the "মনে রাখুন" checkbox

**Files:**
- Modify: `resources/js/components/form-field.tsx:59`
- Modify: `resources/js/pages/orders/create.tsx:217`
- Modify: `resources/js/pages/orders/index.tsx:106`
- Modify: `resources/js/pages/attendance/index.tsx:279`
- Modify: `resources/js/pages/auth/login.tsx:87`
- Test: `tests/Feature/Auth/AuthenticationTest.php`

- [ ] **Step 1: Write the failing remember-me test**

Add to `AuthenticationTest`:

```php
    public function test_ticking_remember_me_keeps_the_user_signed_in()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'phone' => $user->phone,
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertAuthenticated();
        $response->assertCookie(\Illuminate\Support\Facades\Auth::guard()->getRecallerName());
        $this->assertNotNull($user->refresh()->remember_token);
    }
```

- [ ] **Step 2: Run it**

Run: `php artisan test tests/Feature/Auth/AuthenticationTest.php --filter=remember`

Expected: PASS. The server already honours `remember`; the bug is that the
checkbox never sends it. If it fails, stop and report, because the server
side needs fixing first. This test guards the server contract the checkbox
relies on.

- [ ] **Step 3: Wire the checkbox in `pages/auth/login.tsx:87`**

```tsx
<Checkbox
    id="remember"
    name="remember"
    tabIndex={3}
    checked={data.remember}
    onCheckedChange={(checked) => setData('remember', checked === true)}
    className="size-5"
/>
```

`size-5` makes it easier to tap. The label next to it, `htmlFor="remember"`,
already toggles it.

- [ ] **Step 4: Fix the keyboards**

- `form-field.tsx:59`: replace the `inputMode` line with
  ```tsx
                inputMode={numeric ? (type === 'number' ? 'decimal' : 'numeric') : undefined}
  ```
  Add a comment above the `<Input`:
  ```tsx
            {/* Amounts and quantities take a decimal point; phone, NID and account numbers are digits only. */}
  ```
- `orders/create.tsx:217`: delete the `inputMode="numeric"` line. The field
  searches by phone or name.
- `orders/index.tsx:106`: delete the `inputMode="numeric"` line. Order
  numbers contain letters and a hyphen.
- `attendance/index.tsx:279`, the overtime rate, which is money: change
  `inputMode="numeric"` to `inputMode="decimal"`.

- [ ] **Step 5: Typecheck, lint, build**

```bash
npx tsc --noEmit -p .
npx eslint resources/js/components/form-field.tsx resources/js/pages/orders/create.tsx resources/js/pages/orders/index.tsx resources/js/pages/attendance/index.tsx resources/js/pages/auth/login.tsx
npm run build
```

- [ ] **Step 6: Manual check**

- On `/login`, ticking the box and signing in leaves a `remember_web_*`
  cookie in the browser's devtools.
- On a 390px viewport, `/orders/create`'s customer search shows the text
  keyboard. On a desktop browser you can't see the keyboard, so confirm in
  devtools that the `inputmode` attribute is gone.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/form-field.tsx resources/js/pages/orders/create.tsx resources/js/pages/orders/index.tsx resources/js/pages/attendance/index.tsx resources/js/pages/auth/login.tsx tests/Feature/Auth/AuthenticationTest.php
git commit -m "fix: right keyboard for searches and amounts; remember-me actually remembers"
```

---

### Task 8: Whole-batch verification

**Files:** none changed unless a check fails.

- [ ] **Step 1: Full test suite**

Run: `php artisan test`

Expected: all pass. Compare the result with the run before Task 1. Any test
that was already failing on the human partner's uncommitted work is reported,
not fixed here.

- [ ] **Step 2: Frontend checks**

```bash
npx tsc --noEmit -p .
npx eslint resources/js
npm run build
```

- [ ] **Step 3: Smoke test every touched page**

The existing feature tests already `GET` most of these pages as a logged-in
user and assert a 200. Confirm which ones are covered:

```bash
grep -rlnE "get\('/(dashboard|settings/profile|settings/password|orders|orders/create|attendance|daily-closing|employee-ledger|supplier-ledger|stock|materials|accounts)'" tests/Feature
```

Then check the pages visually in Chrome, logged in as the owner, if the
extension is connected. Visit each of these and confirm it renders with no
console errors:
- `/dashboard`
- `/settings/profile`, `/settings/password`, `/settings/appearance`
- `/orders`, `/orders/create`
- `/attendance`, `/daily-closing`
- `/employee-ledger`, `/supplier-ledger`
- `/stock`, `/materials`, `/accounts`

Repeat `/dashboard` and the sidebar at a 390px width. If Chrome is not
connected, say so and list the pages that were checked only by tests.

- [ ] **Step 4: Report**

Write a short summary for the human partner covering:
- what changed per task
- the two spec deviations, from "Global Constraints"
- anything left out of commits because it belonged to their uncommitted work
- any page not checked visually
