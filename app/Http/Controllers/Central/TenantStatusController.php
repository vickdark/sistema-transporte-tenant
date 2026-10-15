<?php

namespace App\Http\Controllers\Central;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

/**
 * Suspender un tenant bloquea el acceso a su aplicación sin borrar ningún dato.
 */
class TenantStatusController extends Controller
{
    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => TenantStatus::Suspended, 'suspended_at' => now()]);

        return back()->with('success', 'Empresa suspendida. Sus usuarios ya no pueden ingresar.');
    }

    public function reactivate(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => TenantStatus::Active, 'suspended_at' => null]);

        return back()->with('success', 'Empresa reactivada.');
    }
}
