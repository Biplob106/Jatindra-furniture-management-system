<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DailyClosingController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeLedgerController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemWorkController;
use App\Http\Controllers\OrderPhotoController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierLedgerController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Staff accounts. There is no public registration; this is the only way in.
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Shop floor. Reading the sheet and marking it are separate permissions.
    Route::get('attendance', [AttendanceController::class, 'index'])
        ->middleware('permission:attendance.view')
        ->name('attendance.index');

    Route::post('attendance', [AttendanceController::class, 'store'])
        ->middleware('permission:attendance.mark')
        ->name('attendance.store');

    // Orders. Reading the book and taking an order are separate permissions.
    Route::middleware('permission:orders.view')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    });

    Route::middleware('permission:orders.manage')->group(function () {
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
        Route::put('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
        Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

        Route::post('orders/{order}/photos', [OrderPhotoController::class, 'store'])->name('orders.photos.store');
        Route::delete('orders/{order}/photos/{media}', [OrderPhotoController::class, 'destroy'])->name('orders.photos.destroy');

        // Piece work on an item. Completing one pays a piece worker.
        Route::post('order-items/{item}/works', [OrderItemWorkController::class, 'store'])->name('order-items.works.store');
        Route::put('order-items/{item}/works/{work}', [OrderItemWorkController::class, 'update'])->name('order-items.works.update');
        Route::delete('order-items/{item}/works/{work}', [OrderItemWorkController::class, 'destroy'])->name('order-items.works.destroy');
    });

    // Taking money is its own permission: the person at the counter accepts an
    // advance without being the one who edits what was ordered.
    Route::post('orders/{order}/payments', [OrderController::class, 'storePayment'])
        ->middleware('permission:orders.payment')
        ->name('orders.payments.store');

    // Declared after create so /orders/create is not swallowed by {order}.
    Route::get('orders/{order}', [OrderController::class, 'show'])
        ->middleware('permission:orders.view')
        ->name('orders.show');

    // The store room. Reading the movement log and moving stock are separate
    // permissions: a manager sees what was consumed, a storekeeper moves it.
    Route::get('stock', [StockController::class, 'index'])
        ->middleware('permission:stock.view')
        ->name('stock.index');

    Route::middleware('permission:stock.adjust')->group(function () {
        Route::post('stock/issue', [StockController::class, 'issue'])->name('stock.issue');
        Route::post('stock/count', [StockController::class, 'count'])->name('stock.count');
    });

    // Supplier accounts. Reading what is owed and paying it are separate
    // permissions: a storekeeper never hands money over.
    Route::middleware('permission:supplier_ledger.view')->group(function () {
        Route::get('supplier-ledger', [SupplierLedgerController::class, 'index'])->name('supplier-ledger.index');
        Route::get('supplier-ledger/{supplier}', [SupplierLedgerController::class, 'show'])->name('supplier-ledger.show');
    });

    Route::post('supplier-ledger/{supplier}', [SupplierLedgerController::class, 'store'])
        ->middleware('permission:supplier_payment.record')
        ->name('supplier-ledger.store');

    // Buying. Reading the challan book and writing one in are separate
    // permissions: a storekeeper records what arrived, an accountant reads it.
    Route::get('purchases', [PurchaseController::class, 'index'])
        ->middleware('permission:purchases.view')
        ->name('purchases.index');

    // Before the {purchase} route, or "create" is read as an id.
    Route::middleware('permission:purchases.record')->group(function () {
        Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    });

    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])
        ->middleware('permission:purchases.view')
        ->name('purchases.show');

    // The nightly reconciliation.
    Route::get('daily-closing', [DailyClosingController::class, 'index'])
        ->middleware('permission:daily_closing.view')
        ->name('daily-closing.index');

    Route::post('daily-closing', [DailyClosingController::class, 'store'])
        ->middleware('permission:daily_closing.run')
        ->name('daily-closing.store');

    // Shop running costs. No edit or delete: an expense has a cash row behind
    // it, and changing one without the other desyncs the drawer from the books.
    Route::get('expenses', [ExpenseController::class, 'index'])
        ->middleware('permission:expenses.view')
        ->name('expenses.index');

    Route::middleware('permission:expenses.record')->group(function () {
        Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    });

    // Worker accounts. Reading a balance and handing over money are separate
    // permissions: a manager sees what is owed, an accountant pays it.
    Route::middleware('permission:employee_ledger.view')->group(function () {
        Route::get('employee-ledger', [EmployeeLedgerController::class, 'index'])->name('employee-ledger.index');
        Route::get('employee-ledger/{employee}', [EmployeeLedgerController::class, 'show'])->name('employee-ledger.show');
    });

    Route::post('employee-ledger/{employee}', [EmployeeLedgerController::class, 'store'])
        ->middleware('permission:employee_payment.record')
        ->name('employee-ledger.store');

    // Master data. Reading and editing are separate permissions on purpose: an
    // accountant reads the employee list but must not be able to change it.
    $masterData = [
        'shops' => [ShopController::class, 'shops'],
        'customers' => [CustomerController::class, 'customers'],
        'employees' => [EmployeeController::class, 'employees'],
        'trades' => [TradeController::class, 'trades'],
        'accounts' => [AccountController::class, 'accounts'],
        'expense-categories' => [ExpenseCategoryController::class, 'expense_categories'],
        'product-categories' => [ProductCategoryController::class, 'product_categories'],
        'suppliers' => [SupplierController::class, 'suppliers'],
        'materials' => [MaterialController::class, 'materials'],
    ];

    foreach ($masterData as $uri => [$controller, $permission]) {
        Route::resource($uri, $controller)
            ->only(['index'])
            ->middleware("permission:{$permission}.view");

        Route::resource($uri, $controller)
            ->except(['index', 'show'])
            ->middleware("permission:{$permission}.manage");
    }
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
