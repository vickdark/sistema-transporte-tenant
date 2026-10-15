<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Vehicles\SaveVehicleDocument;
use App\Enums\FuelType;
use App\Enums\Permission;
use App\Enums\VehicleClass;
use App\Enums\VehicleDocumentType;
use App\Enums\VehicleOwnerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\VehicleDocumentRequest;
use App\Http\Requests\Tenant\VehicleRequest;
use App\Http\Tables\Tenant\VehiclesTable;
use App\Models\Ally;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehiclePhoto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Vehículos (legacy: módulo `vehiculos` y `vehiculos/inactivos`). No se eliminan: se inhabilitan y
 * se habilitan. Los documentos y las fotos se gestionan desde la ficha del vehículo.
 */
class VehicleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Vehicle::class);

        $table = new VehiclesTable($request);

        return Inertia::render('vehicles/Index', [
            'vehicles' => $table->rows(),
            'query' => $table->state(),
            'ownerTypes' => $this->options(VehicleOwnerType::cases()),
            'classes' => $this->options(VehicleClass::cases()),
            'can' => ['create' => $request->user()->can('create', Vehicle::class)],
        ]);
    }

    public function export(Request $request, string $format): SymfonyResponse
    {
        Gate::authorize('viewAny', Vehicle::class);

        return (new VehiclesTable($request))->export($format);
    }

    public function show(Request $request, Vehicle $vehicle): Response
    {
        Gate::authorize('view', $vehicle);

        $vehicle->load(['supplier', 'ally', 'agreement', 'photos', 'vehicleParts.part', 'documents' => fn ($query) => $query->latest('replaced_at')]);
        $user = $request->user();

        return Inertia::render('vehicles/Show', [
            'item' => [
                ...$this->formData($vehicle),
                'id' => $vehicle->id,
                'class_label' => $vehicle->vehicle_class->label(),
                'fuel_label' => $vehicle->fuel_type?->label(),
                'owner_type' => $vehicle->ownerType(),
                'owner_type_label' => $vehicle->ownerType()->label(),
                'owners' => $this->ownerLinks($vehicle, $request),
                'agreement' => $vehicle->agreement ? [
                    'name' => $vehicle->agreement->business_name,
                    'nit' => $vehicle->agreement->nit,
                    'url' => $user->can('view', $vehicle->agreement) ? route('agreements.show', $vehicle->agreement) : null,
                ] : null,
                'is_active' => $vehicle->is_active,
            ],
            'documents' => $this->documents($vehicle),
            'photos' => $vehicle->photos->map(fn (VehiclePhoto $photo): array => [
                'id' => $photo->id,
                'url' => route('vehicles.photos.show', [$vehicle, $photo]),
            ]),
            'parts' => $vehicle->vehicleParts->sortBy('part.name')->values()->map(fn ($vehiclePart): array => [
                'name' => $vehiclePart->part->name,
                'maintenance_interval_km' => $vehiclePart->maintenance_interval_km,
                'url' => $user->can('view', $vehiclePart->part) ? route('parts.show', $vehiclePart->part) : null,
            ]),
            'limits' => [
                'photos' => VehiclePhoto::MAX_PER_VEHICLE,
                'photo_mb' => VehiclePhotoController::MAX_FILE_KB / 1024,
                'document_mb' => VehicleDocumentRequest::MAX_FILE_KB / 1024,
            ],
            'can' => [
                'update' => $user->can('update', $vehicle),
                'toggle_active' => $user->can('toggleActive', $vehicle),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Vehicle::class);

        return $this->renderForm($request, null);
    }

    /**
     * Registra el vehículo con los documentos que se llenaron; después se abre su ficha (fotos).
     */
    public function store(VehicleRequest $request, SaveVehicleDocument $documents): RedirectResponse
    {
        Gate::authorize('create', Vehicle::class);

        $vehicle = DB::transaction(function () use ($request, $documents): Vehicle {
            $vehicle = new Vehicle($request->vehicleData());
            $vehicle->assignOwner($request->owner());
            $vehicle->save();
            $this->saveDocuments($request, $vehicle, $documents);

            return $vehicle;
        });

        return to_route('vehicles.show', $vehicle)->with('success', 'Vehículo registrado. Ahora puedes agregar sus fotos.');
    }

    public function edit(Request $request, Vehicle $vehicle): Response
    {
        Gate::authorize('update', $vehicle);

        return $this->renderForm($request, $vehicle);
    }

    /**
     * Los documentos que se llenan se corrigen (o se registran); renovar se hace desde la ficha.
     */
    public function update(VehicleRequest $request, Vehicle $vehicle, SaveVehicleDocument $documents): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        DB::transaction(function () use ($request, $vehicle, $documents): void {
            $vehicle->fill($request->vehicleData());
            $vehicle->assignOwner($request->owner());
            $vehicle->save();
            $this->saveDocuments($request, $vehicle, $documents);
        });

        return to_route('vehicles.show', $vehicle)->with('success', 'Vehículo actualizado correctamente.');
    }

    public function deactivate(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('toggleActive', $vehicle);

        $vehicle->forceFill(['is_active' => false])->save();

        return back()->with('success', 'Vehículo inhabilitado: pasó a Inactivos.');
    }

    public function activate(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('toggleActive', $vehicle);

        $vehicle->forceFill(['is_active' => true])->save();

        return back()->with('success', 'Vehículo habilitado.');
    }

    /**
     * Proveedores o aliados para elegir el dueño (`type` = supplier | ally), con su cantidad de
     * vehículos activos como en el legacy. Un usuario de un tercero no elige dueño.
     */
    public function searchOwners(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('create', Vehicle::class) || $user->can(Permission::VehiclesUpdate->value), 403);
        abort_if($user->ally_id !== null || $user->supplier_id !== null, 403);

        $search = $request->string('search')->trim()->value();
        $model = $request->string('type')->value() === 'ally' ? Ally::class : Supplier::class;

        $owners = $model::query()
            ->when($model === Ally::class, fn ($query) => $query->with('supplier:id,ally_id'))
            ->withCount(['vehicles' => fn (Builder $query) => $query->where('is_active', true)])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->whereLike('name', "%{$search}%")->orWhereLike('nit', "%{$search}%")))
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json($owners->map(fn (Supplier|Ally $owner): array => $this->ownerOption($owner)));
    }

    private function renderForm(Request $request, ?Vehicle $vehicle): Response
    {
        $user = $request->user();
        $fixedOwner = $user->ally ?? $user->supplier;
        $owner = $vehicle?->supplier ?? $vehicle?->ally;

        return Inertia::render('vehicles/Form', [
            'item' => $vehicle ? [
                ...$this->formData($vehicle),
                'id' => $vehicle->id,
                'owner_type' => $vehicle->supplier_id !== null ? 'supplier' : ($vehicle->ally_id !== null ? 'ally' : 'company'),
                'owner' => $owner ? $this->ownerOption($owner) : null,
                'documents' => $vehicle->currentDocuments()->get()->mapWithKeys(fn (VehicleDocument $document): array => [
                    $document->type->value => [
                        'number' => $document->number,
                        'issued_on' => $document->issued_on?->toDateString(),
                        'expires_on' => $document->expires_on?->toDateString(),
                        'file_url' => $document->file_path ? route('vehicles.documents.file', [$vehicle, $document]) : null,
                    ],
                ]),
            ] : null,
            'documentTypes' => array_map(fn (VehicleDocumentType $type): array => [
                'type' => $type->value,
                'label' => $type->label(),
                'group' => $type->group(),
                'expires' => $type->expires(),
                'field_labels' => $type->fieldLabels(),
            ], VehicleDocumentType::cases()),
            'maxDocumentMb' => VehicleDocumentRequest::MAX_FILE_KB / 1024,
            'classes' => $this->options(VehicleClass::cases()),
            'fuelTypes' => $this->options(FuelType::cases()),
            'fixedOwner' => $fixedOwner?->name,
            'companyName' => tenant('name'),
        ]);
    }

    private function saveDocuments(VehicleRequest $request, Vehicle $vehicle, SaveVehicleDocument $documents): void
    {
        foreach ($request->documentsToSave() as $document) {
            $documents->save($vehicle, $document['type'], $document['data'], $document['file']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Vehicle $vehicle): array
    {
        return [
            ...$vehicle->only([
                'plate', 'internal_number', 'registration_place', 'model_year', 'brand', 'line', 'vehicle_class',
                'engine_number', 'chassis_number', 'color', 'displacement', 'fuel_type',
            ]),
            'current_mileage' => (float) $vehicle->current_mileage,
            'registration_date' => $vehicle->registration_date->toDateString(),
        ];
    }

    /**
     * Proveedor y aliado a cuyo nombre está el vehículo, con enlace a su ficha si el usuario la puede ver.
     *
     * @return list<array{kind: string, name: string, url: string|null}>
     */
    private function ownerLinks(Vehicle $vehicle, Request $request): array
    {
        $links = [];

        if ($vehicle->supplier) {
            $links[] = ['kind' => 'Proveedor', 'name' => $vehicle->supplier->name, 'url' => $request->user()->can('view', $vehicle->supplier) ? route('suppliers.show', $vehicle->supplier) : null];
        }

        if ($vehicle->ally) {
            $links[] = ['kind' => 'Aliado', 'name' => $vehicle->ally->name, 'url' => $request->user()->can('view', $vehicle->ally) ? route('allies.show', $vehicle->ally) : null];
        }

        return $links;
    }

    /**
     * Los ocho tipos de documento, cada uno con su vigente (si lo hay) y su historial.
     *
     * @return list<array<string, mixed>>
     */
    private function documents(Vehicle $vehicle): array
    {
        $byType = $vehicle->documents->groupBy(fn (VehicleDocument $document): string => $document->type->value);

        return array_map(function (VehicleDocumentType $type) use ($vehicle, $byType): array {
            $documents = $byType->get($type->value, collect());
            $current = $documents->first(fn (VehicleDocument $document): bool => $document->replaced_at === null);

            return [
                'type' => $type->value,
                'label' => $type->label(),
                'expires' => $type->expires(),
                'field_labels' => $type->fieldLabels(),
                'current' => $current ? $this->documentData($vehicle, $current) : null,
                'history' => $documents
                    ->filter(fn (VehicleDocument $document): bool => $document->replaced_at !== null)
                    ->values()
                    ->map(fn (VehicleDocument $document): array => $this->documentData($vehicle, $document)),
            ];
        }, VehicleDocumentType::cases());
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(Vehicle $vehicle, VehicleDocument $document): array
    {
        return [
            'id' => $document->id,
            'number' => $document->number,
            'issued_on' => $document->issued_on?->toDateString(),
            'expires_on' => $document->expires_on?->toDateString(),
            'status' => $document->status(),
            'file_url' => $document->file_path ? route('vehicles.documents.file', [$vehicle, $document]) : null,
            'replaced_at' => $document->replaced_at?->toDateString(),
        ];
    }

    /**
     * @return array{value: int, label: string, description: string}
     */
    private function ownerOption(Supplier|Ally $owner): array
    {
        $count = $owner->vehicles_count ?? $owner->vehicles()->where('is_active', true)->count();
        $mixed = $owner->isMixed() ? ' · mixto' : '';

        return [
            'value' => $owner->id,
            'label' => $owner->name,
            'description' => trim(($owner->nit ? "NIT {$owner->nit} · " : '')."{$count} vehículos activos{$mixed}"),
        ];
    }

    /**
     * @param  list<VehicleClass|FuelType|VehicleOwnerType>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn (VehicleClass|FuelType|VehicleOwnerType $case): array => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
