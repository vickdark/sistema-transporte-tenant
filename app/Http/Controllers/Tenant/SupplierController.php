<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\ThirdParties\LinkAllyAndSupplier;
use App\Http\Requests\Tenant\SupplierRequest;
use App\Http\Tables\Tenant\SuppliersTable;
use App\Models\Ally;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Proveedores de la empresa (legacy: módulo `proveedores`).
 */
class SupplierController extends ThirdPartyController
{
    public function __construct(private LinkAllyAndSupplier $link) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Supplier::class);

        return $this->renderIndex($request, new SuppliersTable($request), Supplier::class);
    }

    public function export(Request $request, string $format): SymfonyResponse
    {
        Gate::authorize('viewAny', Supplier::class);

        return (new SuppliersTable($request))->export($format);
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        return $this->renderShow($request, $supplier);
    }

    public function create(): Response
    {
        Gate::authorize('create', Supplier::class);

        return $this->renderForm(null);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        Gate::authorize('create', Supplier::class);

        Supplier::create($request->validated());

        return to_route('suppliers.index')->with('success', 'Proveedor registrado correctamente.');
    }

    public function edit(Supplier $supplier): Response
    {
        Gate::authorize('update', $supplier);

        return $this->renderForm($supplier);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('update', $supplier);

        $supplier->update($request->validated());

        return to_route('suppliers.index')->with('success', 'Proveedor actualizado correctamente.');
    }

    /**
     * Convertir: también queda registrado (y vinculado) como aliado.
     */
    public function convert(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('link', $supplier);

        return $this->backWith($this->link->convertSupplierToAlly($supplier));
    }

    /**
     * Revertir: deja de ser mixto; el registro de aliado sigue existiendo por separado.
     */
    public function revert(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('link', $supplier);

        return $this->backWith($this->link->revertSupplier($supplier));
    }

    protected function routePrefix(): string
    {
        return 'suppliers';
    }

    protected function twinRoutePrefix(): string
    {
        return 'allies';
    }

    protected function labels(): array
    {
        return ['singular' => 'proveedor', 'plural' => 'Proveedores', 'nit_label' => 'NIT', 'twin_singular' => 'aliado', 'twin_plural' => 'Aliados'];
    }

    /**
     * @param  Supplier  $model
     */
    protected function twinOf(Ally|Supplier $model): ?Ally
    {
        return $model->ally;
    }
}
