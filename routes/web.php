<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeLedgerController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ShopController;
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
    });

    // Declared after create so /orders/create is not swallowed by {order}.
    Route::get('orders/{order}', [OrderController::class, 'show'])
        ->middleware('permission:orders.view')
        ->name('orders.show');

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
