<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\PartRequest;
use App\Http\Tables\Tenant\PartsTable;
use App\Models\Part;
use App\Models\Vehicle;
use App\Models\VehiclePart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Partes de vehículos (legacy: `vehiculos/partes`): catálogo de partes y, en la ficha de cada
 * parte, qué vehículos activos la tienen y cada cuántos kilómetros se les hace mantenimiento.
 * Se agrega o se quita a un vehículo o a todos a la vez; eliminar una parte la quita del catálogo
 * y de todos los vehículos.
 */
class PartController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Part::class);

        $table = new PartsTable($request);

        return Inertia::render('parts/Index', [
            'parts' => $table->rows(),
            'query' => $table->state(),
            'can' => ['manage' => $request->user()->can('manage', Part::class)],
        ]);
    }

    public function export(Request $request, string $format): SymfonyResponse
    {
        Gate::authorize('viewAny', Part::class);

        return (new PartsTable($request))->export($format);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('manage', Part::class);

        return Inertia::render('parts/Create', [
            'vehicles' => $this->activeVehicles($request)->get(['id', 'plate', 'brand', 'line', 'model_year'])->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'description' => "{$vehicle->brand} {$vehicle->line} {$vehicle->model_year}",
            ]),
        ]);
    }

    public function store(PartRequest $request): RedirectResponse
    {
        Gate::authorize('manage', Part::class);

        $part = DB::transaction(function () use ($request): Part {
            $part = Part::create(['name' => $request->validated('name')]);

            foreach ($request->vehicleIds() as $vehicleId) {
                $part->vehicleParts()->create(['vehicle_id' => $vehicleId, 'maintenance_interval_km' => $request->validated('maintenance_interval_km')]);
            }

            return $part;
        });

        return to_route('parts.show', $part)->with('success', "Parte \"{$part->name}\" creada en ".$part->vehicleParts()->count().' vehículo(s).');
    }

    /**
     * Ficha de la parte: todos los vehículos activos, con o sin la parte, para gestionarla en cada uno.
     */
    public function show(Request $request, Part $part): Response
    {
        Gate::authorize('view', $part);

        $assignments = $part->vehicleParts()->get()->keyBy('vehicle_id');
        $vehicles = $this->activeVehicles($request)->get(['id', 'plate', 'brand', 'line', 'model_year']);

        return Inertia::render('parts/Show', [
            'part' => ['id' => $part->id, 'name' => $part->name],
            'vehicles' => $vehicles->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'description' => "{$vehicle->brand} {$vehicle->line} {$vehicle->model_year}",
                'assignment' => ($assignment = $assignments->get($vehicle->id)) ? [
                    'id' => $assignment->id,
                    'maintenance_interval_km' => $assignment->maintenance_interval_km,
                ] : null,
            ]),
            'can' => [
                'manage' => $request->user()->can('manage', Part::class),
                'view_vehicles' => $request->user()->can('viewAny', Vehicle::class),
            ],
        ]);
    }

    /**
     * Agrega la parte a un vehículo activo que no la tiene.
     */
    public function assign(Request $request, Part $part): RedirectResponse
    {
        Gate::authorize('manage', Part::class);

        $data = $request->validate(
            [
                'vehicle_id' => ['required', 'integer'],
                'maintenance_interval_km' => ['required', 'integer', 'min:1', 'max:9999999'],
            ],
            attributes: ['maintenance_interval_km' => 'kilometraje entre mantenimientos'],
        );
        $vehicle = $this->activeVehicles($request)->findOrFail($data['vehicle_id']);

        $part->vehicleParts()->firstOrCreate(['vehicle_id' => $vehicle->id], ['maintenance_interval_km' => $data['maintenance_interval_km']]);

        return back()->with('success', "Parte \"{$part->name}\" agregada al vehículo {$vehicle->plate}.");
    }

    /**
     * Cambia cada cuántos kilómetros se le hace mantenimiento a la parte en un vehículo.
     */
    public function updateAssignment(Request $request, Part $part, VehiclePart $vehiclePart): RedirectResponse
    {
        Gate::authorize('manage', Part::class);
        abort_unless($vehiclePart->vehicle->isVisibleTo($request->user()), 404);

        $data = $request->validate(
            ['maintenance_interval_km' => ['required', 'integer', 'min:1', 'max:9999999']],
            attributes: ['maintenance_interval_km' => 'kilometraje entre mantenimientos'],
        );

        $vehiclePart->update($data);

        return back()->with('success', "Kilometraje de \"{$part->name}\" actualizado a ".number_format($data['maintenance_interval_km'], 0, ',', '.')." km en {$vehiclePart->vehicle->plate}.");
    }

    public function unassign(Request $request, Part $part, VehiclePart $vehiclePart): RedirectResponse
    {
        Gate::authorize('manage', Part::class);
        abort_unless($vehiclePart->vehicle->isVisibleTo($request->user()), 404);

        $vehiclePart->delete();

        return back()->with('success', "Parte \"{$part->name}\" quitada del vehículo {$vehiclePart->vehicle->plate}.");
    }

    /**
     * Agrega la parte a todos los vehículos activos que todavía no la tienen.
     */
    public function assignAll(Request $request, Part $part): RedirectResponse
    {
        Gate::authorize('manage', Part::class);

        $data = $request->validate(
            ['maintenance_interval_km' => ['required', 'integer', 'min:1', 'max:9999999']],
            attributes: ['maintenance_interval_km' => 'kilometraje entre mantenimientos'],
        );

        $vehicleIds = $this->activeVehicles($request)->whereDoesntHave('vehicleParts', fn ($query) => $query->where('part_id', $part->id))->pluck('id');

        if ($vehicleIds->isEmpty()) {
            return back()->with('warning', 'Todos los vehículos activos ya tienen esta parte.');
        }

        DB::transaction(fn () => $vehicleIds->each(fn (int $vehicleId) => $part->vehicleParts()->create([
            'vehicle_id' => $vehicleId,
            'maintenance_interval_km' => $data['maintenance_interval_km'],
        ])));

        return back()->with('success', "Parte \"{$part->name}\" agregada a {$vehicleIds->count()} vehículo(s).");
    }

    /**
     * Quita la parte de todos los vehículos que ve el usuario (también de los inactivos); la parte
     * sigue en el catálogo.
     */
    public function unassignAll(Request $request, Part $part): RedirectResponse
    {
        Gate::authorize('manage', Part::class);

        $removed = $part->vehicleParts()->whereHas('vehicle', fn ($query) => $query->visibleTo($request->user()))->delete();

        return back()->with($removed > 0 ? 'success' : 'warning', $removed > 0 ? "Parte \"{$part->name}\" quitada de {$removed} vehículo(s)." : 'Ningún vehículo tiene esta parte.');
    }

    /**
     * Elimina la parte del catálogo y de todos los vehículos.
     */
    public function destroy(Part $part): RedirectResponse
    {
        Gate::authorize('manage', Part::class);

        $vehicles = $part->vehicleParts()->count();
        $part->delete();

        return to_route('parts.index')->with('success', "Parte \"{$part->name}\" eliminada".($vehicles > 0 ? " (estaba en {$vehicles} vehículo(s))." : '.'));
    }

    /**
     * @return Builder<Vehicle>
     */
    private function activeVehicles(Request $request): Builder
    {
        return Vehicle::query()->visibleTo($request->user())->where('is_active', true)->orderBy('plate');
    }
}
