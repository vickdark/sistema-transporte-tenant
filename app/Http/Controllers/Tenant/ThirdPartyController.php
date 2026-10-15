<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Tables\Tenant\ThirdPartiesTable;
use App\Models\Ally;
use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Base de los módulos gemelos Aliados y Proveedores (mismos campos, pantallas y acciones que en
 * el legacy). Cada módulo indica su modelo, su ruta y sus textos; las pantallas son las mismas.
 */
abstract class ThirdPartyController extends Controller
{
    /**
     * Prefijo de sus rutas: `allies` o `suppliers`.
     */
    abstract protected function routePrefix(): string;

    /**
     * Prefijo de las rutas del módulo gemelo.
     */
    abstract protected function twinRoutePrefix(): string;

    /**
     * Textos del módulo para las pantallas compartidas.
     *
     * @return array{singular: string, plural: string, nit_label: string, twin_singular: string, twin_plural: string}
     */
    abstract protected function labels(): array;

    abstract protected function twinOf(Ally|Supplier $model): Ally|Supplier|null;

    protected function renderIndex(Request $request, ThirdPartiesTable $table, string $modelClass): Response
    {
        return Inertia::render('third-parties/Index', [
            'kind' => $this->labels(),
            'rows' => $table->rows()->through(fn (array $row): array => [...$row, 'urls' => $this->rowUrls($row['id'])]),
            'query' => $table->state(),
            'urls' => [
                'index' => route("{$this->routePrefix()}.index"),
                'create' => route("{$this->routePrefix()}.create"),
                'export_xlsx' => route("{$this->routePrefix()}.export", ['format' => 'xlsx']),
                'export_pdf' => route("{$this->routePrefix()}.export", ['format' => 'pdf']),
            ],
            'can' => ['create' => $request->user()->can('create', $modelClass)],
        ]);
    }

    protected function renderShow(Request $request, Ally|Supplier $model): Response
    {
        Gate::authorize('view', $model);
        $twin = $this->twinOf($model);

        return Inertia::render('third-parties/Show', [
            'kind' => $this->labels(),
            'item' => [
                ...$model->only(['id', 'name', 'nit', 'phone', 'address', 'email']),
                'is_mixed' => $twin !== null,
                'twin' => $twin ? [
                    'name' => $twin->name,
                    'url' => $request->user()->can('view', $twin) ? route("{$this->twinRoutePrefix()}.show", $twin) : null,
                ] : null,
                'users_count' => $model->users()->count(),
            ],
            'vehicles' => $request->user()->can('viewAny', Vehicle::class)
                ? $model->vehicles()->visibleTo($request->user())->orderByDesc('is_active')->orderBy('plate')->get()->map(fn (Vehicle $vehicle): array => [
                    'id' => $vehicle->id,
                    'plate' => $vehicle->plate,
                    'description' => "{$vehicle->vehicle_class->label()} · {$vehicle->brand} {$vehicle->line} {$vehicle->model_year}",
                    'is_active' => $vehicle->is_active,
                ])
                : null,
            'urls' => ['index' => route("{$this->routePrefix()}.index"), ...$this->rowUrls($model->id)],
            'can' => [
                'update' => $request->user()->can('update', $model),
                'link' => $request->user()->can('link', $model),
            ],
        ]);
    }

    protected function renderForm(Ally|Supplier|null $model): Response
    {
        return Inertia::render('third-parties/Form', [
            'kind' => $this->labels(),
            'item' => $model?->only(['id', 'name', 'nit', 'phone', 'address', 'email']),
            'urls' => [
                'index' => route("{$this->routePrefix()}.index"),
                'submit' => $model ? route("{$this->routePrefix()}.update", $model) : route("{$this->routePrefix()}.store"),
            ],
        ]);
    }

    /**
     * @param  array{level: 'success'|'warning', message: string}  $result
     */
    protected function backWith(array $result): RedirectResponse
    {
        return back()->with($result['level'], $result['message']);
    }

    /**
     * @return array<string, string>
     */
    private function rowUrls(int $id): array
    {
        return [
            'show' => route("{$this->routePrefix()}.show", $id),
            'edit' => route("{$this->routePrefix()}.edit", $id),
            'convert' => route("{$this->routePrefix()}.convert", $id),
            'revert' => route("{$this->routePrefix()}.revert", $id),
        ];
    }
}
