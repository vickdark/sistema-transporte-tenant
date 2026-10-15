<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\RouteStatus;
use App\Enums\RouteType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RouteRequest;
use App\Http\Tables\Tenant\RoutesTable;
use App\Models\TransportRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Rutas (legacy: módulo `rutas`). No se eliminan, porque las órdenes las usan: inhabilitar las
 * pasa a inactivas (listado aparte) y habilitar las devuelve.
 */
class RouteController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TransportRoute::class);

        $table = new RoutesTable($request);

        return Inertia::render('routes/Index', [
            'routes' => $table->rows(),
            'query' => $table->state(),
            'types' => $this->options(RouteType::cases()),
            'can' => ['create' => $request->user()->can('create', TransportRoute::class)],
        ]);
    }

    public function export(Request $request, string $format): SymfonyResponse
    {
        Gate::authorize('viewAny', TransportRoute::class);

        return (new RoutesTable($request))->export($format);
    }

    public function show(Request $request, TransportRoute $route): Response
    {
        Gate::authorize('view', $route);

        return Inertia::render('routes/Show', [
            'item' => [
                ...$route->only(['id', 'name', 'start_point', 'end_point', 'description']),
                'type_label' => $route->type->label(),
                'status' => $route->status,
                'status_label' => $route->status->label(),
                'created_at' => $route->created_at?->toDateString(),
            ],
            'can' => [
                'update' => $request->user()->can('update', $route),
                'toggle_active' => $request->user()->can('toggleActive', $route),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', TransportRoute::class);

        return $this->renderForm(null);
    }

    public function store(RouteRequest $request): RedirectResponse
    {
        Gate::authorize('create', TransportRoute::class);

        TransportRoute::create($request->validated());

        return to_route('routes.index')->with('success', 'Ruta registrada correctamente.');
    }

    public function edit(TransportRoute $route): Response
    {
        Gate::authorize('update', $route);

        return $this->renderForm($route);
    }

    public function update(RouteRequest $request, TransportRoute $route): RedirectResponse
    {
        Gate::authorize('update', $route);

        $route->update($request->validated());

        return to_route('routes.index')->with('success', 'Ruta actualizada correctamente.');
    }

    public function deactivate(TransportRoute $route): RedirectResponse
    {
        Gate::authorize('toggleActive', $route);

        $route->update(['status' => RouteStatus::Inactive]);

        return back()->with('success', 'Ruta inhabilitada: pasó a Inactivas.');
    }

    public function activate(TransportRoute $route): RedirectResponse
    {
        Gate::authorize('toggleActive', $route);

        $route->update(['status' => RouteStatus::Active]);

        return back()->with('success', 'Ruta habilitada.');
    }

    private function renderForm(?TransportRoute $route): Response
    {
        return Inertia::render('routes/Form', [
            'item' => $route?->only(['id', 'start_point', 'end_point', 'description', 'type', 'status']),
            'types' => $this->options(RouteType::cases()),
            'statuses' => $this->options(RouteStatus::cases()),
        ]);
    }

    /**
     * @param  list<RouteType|RouteStatus>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn (RouteType|RouteStatus $case): array => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
