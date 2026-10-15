<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AgreementRequest;
use App\Http\Tables\Tenant\AgreementsTable;
use App\Models\Agreement;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Convenios con otras empresas de transporte (legacy: `vehiculos/convenios`). Como en el legacy se
 * eliminan; sus vehículos quedan sin convenio.
 */
class AgreementController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Agreement::class);

        $table = new AgreementsTable($request);

        return Inertia::render('agreements/Index', [
            'agreements' => $table->rows(),
            'query' => $table->state(),
            'can' => ['create' => $request->user()->can('create', Agreement::class)],
        ]);
    }

    public function export(Request $request, string $format): SymfonyResponse
    {
        Gate::authorize('viewAny', Agreement::class);

        return (new AgreementsTable($request))->export($format);
    }

    public function show(Request $request, Agreement $agreement): Response
    {
        Gate::authorize('view', $agreement);

        return Inertia::render('agreements/Show', [
            'item' => [
                ...$agreement->only(['id', 'nit', 'business_name']),
                'vehicles' => $this->vehicleOptions($agreement),
            ],
            'can' => [
                'update' => $request->user()->can('update', $agreement),
                'delete' => $request->user()->can('delete', $agreement),
                'view_vehicles' => $request->user()->can('viewAny', Vehicle::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Agreement::class);

        return Inertia::render('agreements/Form', ['item' => null]);
    }

    public function store(AgreementRequest $request): RedirectResponse
    {
        Gate::authorize('create', Agreement::class);

        DB::transaction(function () use ($request): void {
            $agreement = Agreement::create($request->safe()->only(['nit', 'business_name']));
            Vehicle::query()->whereKey($request->validated('vehicles'))->update(['agreement_id' => $agreement->id]);
        });

        return to_route('agreements.index')->with('success', 'Convenio registrado correctamente.');
    }

    public function edit(Agreement $agreement): Response
    {
        Gate::authorize('update', $agreement);

        return Inertia::render('agreements/Form', [
            'item' => [...$agreement->only(['id', 'nit', 'business_name']), 'vehicles' => $this->vehicleOptions($agreement)],
        ]);
    }

    public function update(AgreementRequest $request, Agreement $agreement): RedirectResponse
    {
        Gate::authorize('update', $agreement);

        DB::transaction(function () use ($request, $agreement): void {
            $agreement->update($request->safe()->only(['nit', 'business_name']));
            $agreement->vehicles()->whereKeyNot($request->validated('vehicles'))->update(['agreement_id' => null]);
            Vehicle::query()->whereKey($request->validated('vehicles'))->update(['agreement_id' => $agreement->id]);
        });

        return to_route('agreements.index')->with('success', 'Convenio actualizado correctamente.');
    }

    public function destroy(Agreement $agreement): RedirectResponse
    {
        Gate::authorize('delete', $agreement);

        $agreement->delete();

        return to_route('agreements.index')->with('success', 'Convenio eliminado: sus vehículos quedaron sin convenio.');
    }

    /**
     * Vehículos activos para agregar a un convenio; los que ya están en otro aparecen indicados.
     */
    public function searchVehicles(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny([Permission::AgreementsCreate->value, Permission::AgreementsUpdate->value]), 403);

        $search = $request->string('search')->trim()->value();

        $vehicles = Vehicle::query()
            ->with('agreement:id,business_name')
            ->where('is_active', true)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('plate', "%{$search}%")
                ->orWhereLike('internal_number', "%{$search}%")))
            ->orderBy('plate')
            ->limit(15)
            ->get();

        return response()->json($vehicles->map(fn (Vehicle $vehicle): array => [
            'value' => $vehicle->id,
            'label' => $vehicle->plate,
            'description' => "{$vehicle->brand} {$vehicle->line} {$vehicle->model_year}"
                .($vehicle->agreement ? " · ya en convenio con {$vehicle->agreement->business_name}" : ''),
        ]));
    }

    /**
     * @return list<array{value: int, label: string, description: string}>
     */
    private function vehicleOptions(Agreement $agreement): array
    {
        return $agreement->vehicles()->orderBy('plate')->get()->map(fn (Vehicle $vehicle): array => [
            'value' => $vehicle->id,
            'label' => $vehicle->plate,
            'description' => "{$vehicle->brand} {$vehicle->line} {$vehicle->model_year}".($vehicle->is_active ? '' : ' · inactivo'),
        ])->all();
    }
}
