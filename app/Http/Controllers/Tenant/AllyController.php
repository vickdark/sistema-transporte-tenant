<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\ThirdParties\LinkAllyAndSupplier;
use App\Http\Requests\Tenant\AllyRequest;
use App\Http\Tables\Tenant\AlliesTable;
use App\Models\Ally;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Aliados de la empresa (legacy: módulo `proveedores_aliados`).
 */
class AllyController extends ThirdPartyController
{
    public function __construct(private LinkAllyAndSupplier $link) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Ally::class);

        return $this->renderIndex($request, new AlliesTable($request), Ally::class);
    }

    public function export(Request $request, string $format): SymfonyResponse
    {
        Gate::authorize('viewAny', Ally::class);

        return (new AlliesTable($request))->export($format);
    }

    public function show(Request $request, Ally $ally): Response
    {
        return $this->renderShow($request, $ally);
    }

    public function create(): Response
    {
        Gate::authorize('create', Ally::class);

        return $this->renderForm(null);
    }

    public function store(AllyRequest $request): RedirectResponse
    {
        Gate::authorize('create', Ally::class);

        Ally::create($request->validated());

        return to_route('allies.index')->with('success', 'Aliado registrado correctamente.');
    }

    public function edit(Ally $ally): Response
    {
        Gate::authorize('update', $ally);

        return $this->renderForm($ally);
    }

    public function update(AllyRequest $request, Ally $ally): RedirectResponse
    {
        Gate::authorize('update', $ally);

        $ally->update($request->validated());

        return to_route('allies.index')->with('success', 'Aliado actualizado correctamente.');
    }

    /**
     * Convertir: también queda registrado (y vinculado) como proveedor.
     */
    public function convert(Ally $ally): RedirectResponse
    {
        Gate::authorize('link', $ally);

        return $this->backWith($this->link->convertAllyToSupplier($ally));
    }

    /**
     * Revertir: deja de ser mixto; el registro de proveedor sigue existiendo por separado.
     */
    public function revert(Ally $ally): RedirectResponse
    {
        Gate::authorize('link', $ally);

        return $this->backWith($this->link->revertAlly($ally));
    }

    protected function routePrefix(): string
    {
        return 'allies';
    }

    protected function twinRoutePrefix(): string
    {
        return 'suppliers';
    }

    protected function labels(): array
    {
        return ['singular' => 'aliado', 'plural' => 'Aliados', 'nit_label' => 'NIT o cédula', 'twin_singular' => 'proveedor', 'twin_plural' => 'Proveedores'];
    }

    /**
     * @param  Ally  $model
     */
    protected function twinOf(Ally|Supplier $model): ?Supplier
    {
        return $model->supplier;
    }
}
