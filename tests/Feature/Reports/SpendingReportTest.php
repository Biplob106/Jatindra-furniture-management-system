<?php

use App\Enums\MaterialCategory;
use App\Enums\PurchaseItemType;
use App\Enums\PurchaseStatus;
use App\Enums\Role;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Material;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Queries\SpendingReport;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);

    $this->report = app(SpendingReport::class);

    $this->supplier = Supplier::factory()->create(['name' => 'করিম টিম্বার']);
    $this->wood = Material::factory()->create(['name' => 'সেগুন কাঠ', 'category' => MaterialCategory::Wood]);
    $this->hinge = Material::factory()->create(['name' => 'কব্জা', 'category' => MaterialCategory::Hardware]);

    $this->rent = ExpenseCategory::factory()->create(['name' => 'দোকান ভাড়া']);
    $this->bill = ExpenseCategory::factory()->create(['name' => 'কারেন্ট বিল']);
});

/**
 * A challan whose header adds up from its lines, the way RecordPurchase
 * writes one: total = lines + transport - discount.
 *
 * @param  list<array{0: Material|Product, 1: string}>  $lines  item and line total
 */
function spendingChallan(array $lines, array $attributes = [], string $transport = '0.00', string $discount = '0.00', string $paid = '0.00'): Purchase
{
    $subtotal = array_reduce($lines, fn (string $sum, array $line) => bcadd($sum, $line[1], 2), '0.00');
    $total = bcsub(bcadd($subtotal, $transport, 2), $discount, 2);

    $purchase = Purchase::factory()->withTotals($total, $paid)->create([
        'supplier_id' => test()->supplier->id,
        'purchase_date' => '2026-07-10',
        'subtotal' => $subtotal,
        'transport_cost' => $transport,
        'discount' => $discount,
        ...$attributes,
    ]);

    foreach ($lines as [$item, $lineTotal]) {
        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'item_type' => $item instanceof Product ? PurchaseItemType::Product : PurchaseItemType::Material,
            'item_id' => $item->id,
            'line_total' => $lineTotal,
        ]);
    }

    return $purchase;
}

function spendingExpense(ExpenseCategory $category, string $amount, string $date = '2026-07-15', array $attributes = []): Expense
{
    return Expense::factory()->create([
        'category_id' => $category->id,
        'amount' => $amount,
        'expense_date' => $date,
        ...$attributes,
    ]);
}

it('puts the month\'s purchases and expenses under one total', function () {
    spendingChallan([[$this->wood, '20000.00']], paid: '20000.00');
    spendingExpense($this->rent, '12000.00');
    spendingExpense($this->bill, '2500.50');

    expect($this->report->summary('2026-07-01', '2026-07-31')['totals'])->toBe([
        'purchases' => '20000.00',
        'purchases_due' => '0.00',
        'expenses' => '14500.50',
        'total' => '34500.50',
    ]);
});

it('counts a credit purchase as spending and shows what is still owed on it', function () {
    spendingChallan([[$this->wood, '30000.00']]);
    spendingChallan([[$this->hinge, '4000.00']], paid: '1500.00');

    $totals = $this->report->summary('2026-07-01', '2026-07-31')['totals'];

    expect($totals['purchases'])->toBe('34000.00')
        ->and($totals['purchases_due'])->toBe('32500.00')
        ->and($totals['total'])->toBe('34000.00');
});

it('leaves out anything dated outside the period', function () {
    spendingChallan([[$this->wood, '9000.00']], ['purchase_date' => '2026-06-30']);
    spendingChallan([[$this->wood, '7000.00']], ['purchase_date' => '2026-08-01']);
    spendingExpense($this->rent, '12000.00', '2026-06-30');
    spendingExpense($this->bill, '800.00', '2026-07-31');

    expect($this->report->summary('2026-07-01', '2026-07-31')['totals']['total'])->toBe('800.00');
});

it('leaves out a returned challan', function () {
    spendingChallan([[$this->wood, '5000.00']], ['status' => PurchaseStatus::Returned]);
    spendingExpense($this->bill, '1000.00');

    expect($this->report->summary('2026-07-01', '2026-07-31')['totals'])
        ->toMatchArray(['purchases' => '0.00', 'total' => '1000.00']);
});

it('breaks purchases down by material category, transport and discount, biggest first', function () {
    $sofa = Product::factory()->create();

    spendingChallan(
        [[$this->wood, '18000.00'], [$this->hinge, '2400.00'], [$sofa, '9000.00']],
        transport: '1200.00',
        discount: '400.00',
    );
    spendingExpense($this->rent, '12000.00');

    expect($this->report->summary('2026-07-01', '2026-07-31')['byHeading'])->toBe([
        ['kind' => 'purchase', 'name' => 'কাঠ', 'total' => '18000.00'],
        ['kind' => 'expense', 'name' => 'দোকান ভাড়া', 'total' => '12000.00'],
        ['kind' => 'purchase', 'name' => 'রেডিমেড পণ্য', 'total' => '9000.00'],
        ['kind' => 'purchase', 'name' => 'হার্ডওয়্যার', 'total' => '2400.00'],
        ['kind' => 'purchase', 'name' => 'ক্রয়ের পরিবহন', 'total' => '1200.00'],
        ['kind' => 'purchase', 'name' => 'ক্রয়ে ছাড়', 'total' => '-400.00'],
    ]);
});

it('has headings that add up to the grand total to the paisa', function () {
    spendingChallan([[$this->wood, '10333.33'], [$this->hinge, '1666.67']], transport: '450.25', discount: '99.99');
    spendingChallan([[$this->wood, '0.01']]);
    spendingExpense($this->rent, '12000.00');
    spendingExpense($this->bill, '2333.17');
    spendingExpense($this->bill, '0.83');

    $summary = $this->report->summary('2026-07-01', '2026-07-31');

    $sum = array_reduce($summary['byHeading'], fn (string $carry, array $row) => bcadd($carry, $row['total'], 2), '0.00');

    expect($summary['totals']['total'])->toBe('26684.27')
        ->and($sum)->toBe($summary['totals']['total']);
});

it('narrows to one shop', function () {
    $main = Shop::factory()->create();
    $branch = Shop::factory()->create();

    spendingChallan([[$this->wood, '8000.00']], ['shop_id' => $main->id]);
    spendingChallan([[$this->wood, '3000.00']], ['shop_id' => $branch->id]);
    spendingExpense($this->rent, '12000.00', attributes: ['shop_id' => $main->id]);
    spendingExpense($this->rent, '9000.00', attributes: ['shop_id' => $branch->id]);

    expect($this->report->summary('2026-07-01', '2026-07-31', shopId: $branch->id)['totals'])
        ->toMatchArray(['purchases' => '3000.00', 'expenses' => '9000.00', 'total' => '12000.00']);
});

it('shows only that supplier\'s challans when narrowed to a supplier, since expenses have none', function () {
    $other = Supplier::factory()->create();

    spendingChallan([[$this->wood, '8000.00']]);
    spendingChallan([[$this->wood, '3000.00']], ['supplier_id' => $other->id]);
    spendingExpense($this->rent, '12000.00');

    $summary = $this->report->summary('2026-07-01', '2026-07-31', supplierId: $other->id);

    expect($summary['totals'])->toMatchArray(['purchases' => '3000.00', 'expenses' => '0.00', 'total' => '3000.00'])
        ->and(collect($summary['byHeading'])->pluck('kind')->unique()->all())->toBe(['purchase']);
});

it('lists purchases and expenses together, newest first', function () {
    $credit = spendingChallan([[$this->wood, '30000.00']], ['purchase_date' => '2026-07-05', 'purchase_no' => 'PO-2607-0001']);
    $rent = spendingExpense($this->rent, '12000.00', '2026-07-08', ['paid_to' => 'রহিম সাহেব']);
    spendingExpense($this->bill, '900.00', '2026-07-01');

    $rows = $this->report->rows('2026-07-01', '2026-07-31')->get();

    expect($rows->pluck('kind')->all())->toBe(['expense', 'purchase', 'expense'])
        ->and((array) $rows[0])->toMatchArray([
            'kind' => 'expense',
            'id' => $rent->id,
            'entry_date' => '2026-07-08',
            'reference' => null,
            'heading' => 'দোকান ভাড়া',
            'party' => 'রহিম সাহেব',
            'amount' => '12000.00',
            'due' => '0.00',
        ])
        ->and((array) $rows[1])->toMatchArray([
            'kind' => 'purchase',
            'id' => $credit->id,
            'reference' => 'PO-2607-0001',
            'heading' => null,
            'party' => 'করিম টিম্বার',
            'amount' => '30000.00',
            'due' => '30000.00',
        ]);
});

it('finds rows by supplier, payee, heading or challan number', function () {
    spendingChallan([[$this->wood, '30000.00']], ['purchase_no' => 'PO-2607-0042']);
    spendingExpense($this->rent, '12000.00', attributes: ['paid_to' => 'রহিম সাহেব']);
    spendingExpense($this->bill, '900.00');

    $count = fn (string $term) => $this->report->rows('2026-07-01', '2026-07-31', search: $term)->count();

    expect($count('করিম'))->toBe(1)
        ->and($count('রহিম'))->toBe(1)
        ->and($count('কারেন্ট'))->toBe(1)
        ->and($count('0042'))->toBe(1)
        ->and($count('নেই'))->toBe(0);
});

it('only reads', function () {
    spendingChallan([[$this->wood, '30000.00']]);
    spendingExpense($this->rent, '12000.00');

    $before = [Purchase::count(), Expense::count(), Transaction::count(), SupplierLedger::count()];

    $this->report->summary('2026-07-01', '2026-07-31');
    $this->report->rows('2026-07-01', '2026-07-31')->get();

    expect([Purchase::count(), Expense::count(), Transaction::count(), SupplierLedger::count()])->toBe($before);
});

it('refuses a period that is not a pair of dates', function () {
    $this->report->summary('2026-07-01; DROP TABLE purchases', '2026-07-31');
})->throws(InvalidArgumentException::class);

describe('screen', function () {
    it('shows the owner the month they asked for', function () {
        spendingChallan([[$this->wood, '20000.00']]);
        spendingExpense($this->rent, '12000.00');

        $this->actingAs($this->owner)
            ->get('/reports/spending?month=2026-07')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/spending')
                ->where('month', '2026-07')
                ->where('totals.total', '32000.00')
                ->has('byHeading', 2)
                ->has('rows.data', 2)
                ->has('shops')
                ->has('suppliers')
            );
    });

    it('carries the filters into the paged list', function () {
        $shop = Shop::factory()->create();

        $this->actingAs($this->owner)
            ->get("/reports/spending?month=2026-07&shop_id={$shop->id}&search=কাঠ")
            ->assertInertia(fn ($page) => $page
                ->where('shopId', $shop->id)
                ->where('supplierId', null)
                ->where('search', 'কাঠ')
            );
    });

    it('falls back to this month on a malformed month', function () {
        $this->actingAs($this->owner)
            ->get('/reports/spending?month=garbage')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('month', now()->format('Y-m')));
    });

    it('is the owner\'s alone', function (Role $role) {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user)->get('/reports/spending')->assertForbidden();
    })->with([Role::Manager, Role::Accountant, Role::Storekeeper]);
});
