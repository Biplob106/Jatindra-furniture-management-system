<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PayrollSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payroll policy settings: the one knob so far is the default night allowance,
 * stored as a fraction of the daily wage. Each setting is a single row in the
 * settings table, upserted by key.
 */
class PayrollSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/payroll', [
            'nightMultiplier' => Setting::read('payroll.night_multiplier', '0.5'),
        ]);
    }

    public function update(PayrollSettingsRequest $request): RedirectResponse
    {
        Setting::updateOrCreate(
            ['key' => 'payroll.night_multiplier'],
            ['value' => $request->validated()['night_multiplier'], 'group' => 'payroll'],
        );

        return back()->with('success', 'সেটিংস সংরক্ষণ করা হয়েছে।');
    }
}
