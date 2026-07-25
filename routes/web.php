<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ExpenseCategoryController;
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

    // Master data. Reading and editing are separate permissions on purpose: an
    // accountant reads the employee list but must not be able to change it.
    $masterData = [
        'shops' => [ShopController::class, 'shops'],
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
