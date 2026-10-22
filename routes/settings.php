<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PayrollSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    // Payroll policy, owner only. Guarded server-side; the nav link is also
    // hidden for anyone without the permission.
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings/payroll', [PayrollSettingsController::class, 'edit'])->name('settings.payroll.edit');
        Route::patch('settings/payroll', [PayrollSettingsController::class, 'update'])->name('settings.payroll.update');
    });

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
