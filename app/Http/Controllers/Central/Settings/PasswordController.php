<?php

namespace App\Http\Controllers\Central\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the central administrator's password settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('central/settings/Password');
    }

    /**
     * Update the central administrator's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:central'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user('central')->update([
            'password' => $validated['password'],
        ]);

        return back();
    }
}
