<?php

use App\Enums\BaseRole;
use App\Enums\Permission;
use App\Enums\VehicleClass;
use App\Enums\VehicleDocumentType;
use App\Models\Ally;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehiclePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExportedSpreadsheet;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole(BaseRole::Administrator)->create());
});

/**
 * Usuario de un aliado con todos los permisos de vehículos.
 */
function vehicleAllyUser(Ally $ally): User
{
    Role::query()->where('base_key', BaseRole::Ally->value)->sole()
        ->syncPermissions([Permission::VehiclesView->value, Permission::VehiclesCreate->value, Permission::VehiclesUpdate->value, Permission::VehiclesDeactivate->value]);

    return User::factory()->withRole(BaseRole::Ally)->create(['ally_id' => $ally->id]);
}

/**
 * @return array<string, mixed>
 */
function vehiclePayload(array $overrides = []): array
{
    return [
        'plate' => ' tsx 123 ',
        'internal_number' => '45',
        'current_mileage' => 125000,
        'registration_date' => '2019-03-15',
        'registration_place' => 'Cartagena',
        'model_year' => 2019,
        'brand' => 'TOYOTA',
        'line' => 'HIACE',
        'vehicle_class' => 'closed_van',
        'engine_number' => '2KD1234567',
        'chassis_number' => 'JTFSS22P0K0123456',
        'color' => 'BLANCO',
        'displacement' => 2700,
        'fuel_type' => 'diesel',
        'owner_type' => 'company',
        ...$overrides,
    ];
}

describe('list and visibility', function () {
    test('lists active vehicles apart from the inactive ones, with their owner and document alerts', function () {
        $supplier = Supplier::factory()->create(['name' => 'TRANSPORTES ALEFT']);
        $vehicle = Vehicle::factory()->ownedBy($supplier)->create(['plate' => 'AAA111']);
        VehicleDocument::factory()->for($vehicle)->create(['expires_on' => today()->subDay()]);
        VehicleDocument::factory()->for($vehicle)->expiringIn(10)->create(['type' => VehicleDocumentType::TechnicalInspection]);
        VehicleDocument::factory()->for($vehicle)->replaced()->create(['type' => VehicleDocumentType::OperationCard, 'expires_on' => today()->subYear()]);
        $inactive = Vehicle::factory()->inactive()->create();

        $this->get(route('vehicles.index'))->assertInertia(fn (Assert $page) => $page
            ->component('vehicles/Index')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.owner_type', 'supplier')
            ->where('vehicles.data.0.owner_name', 'TRANSPORTES ALEFT')
            ->where('vehicles.data.0.expired_documents_count', 1)
            ->where('vehicles.data.0.expiring_documents_count', 1)
        );

        $this->get(route('vehicles.index', ['status' => 'inactive']))->assertInertia(fn (Assert $page) => $page
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.id', $inactive->id)
        );
    });

    test('filters by owner type, mixed being a vehicle of both twins', function () {
        $mixed = Supplier::factory()->mixed()->create();
        $vehicle = Vehicle::factory()->ownedBy($mixed)->create();
        Vehicle::factory()->ownedBy(Supplier::factory()->create())->create();
        Vehicle::factory()->create();

        $this->get(route('vehicles.index', ['owner_type' => 'mixed']))->assertInertia(fn (Assert $page) => $page
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.id', $vehicle->id)
        );
    });

    test('a user of an ally only sees and works with the vehicles of that ally', function () {
        $ally = Ally::factory()->create();
        $mine = Vehicle::factory()->ownedBy($ally)->create();
        $foreign = Vehicle::factory()->create();

        $this->actingAs(vehicleAllyUser($ally));

        $this->get(route('vehicles.index'))->assertInertia(fn (Assert $page) => $page
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.id', $mine->id)
        );
        $this->get(route('vehicles.show', $foreign))->assertForbidden();
        $this->put(route('vehicles.update', $foreign), vehiclePayload())->assertForbidden();
        $this->post(route('vehicles.deactivate', $foreign))->assertForbidden();
    });

    test('exports only the vehicles the user can see', function () {
        $ally = Ally::factory()->create();
        Vehicle::factory()->ownedBy($ally)->create(['plate' => 'MIA123']);
        Vehicle::factory()->create();

        $this->actingAs(vehicleAllyUser($ally));
        $rows = ExportedSpreadsheet::rows($this->get(route('vehicles.export', ['format' => 'xlsx']))->assertOk());

        expect($rows)->toHaveCount(2)
            ->and($rows[1][0])->toBe('MIA123');
    });
});

describe('create and update', function () {
    test('registers a company vehicle with its plate in capitals and opens its page', function () {
        $response = $this->post(route('vehicles.store'), vehiclePayload());

        $vehicle = Vehicle::query()->sole();
        $response->assertRedirect(route('vehicles.show', $vehicle))
            ->assertSessionHas('success', 'Vehículo registrado. Ahora puedes agregar sus fotos.');
        expect($vehicle->only('plate', 'supplier_id', 'ally_id', 'is_active'))->toBe(['plate' => 'TSX123', 'supplier_id' => null, 'ally_id' => null, 'is_active' => true])
            ->and($vehicle->vehicle_class)->toBe(VehicleClass::ClosedVan);
    });

    test('a vehicle of a mixed third party is registered to both twins', function () {
        $supplier = Supplier::factory()->mixed()->create();

        $this->post(route('vehicles.store'), vehiclePayload(['owner_type' => 'supplier', 'owner_id' => $supplier->id]))->assertSessionHasNoErrors();

        expect(Vehicle::query()->sole()->only('supplier_id', 'ally_id'))->toBe(['supplier_id' => $supplier->id, 'ally_id' => $supplier->ally_id]);
    });

    test('a user of an ally registers the vehicle to that ally whatever owner is sent', function () {
        $ally = Ally::factory()->create();

        $this->actingAs(vehicleAllyUser($ally))
            ->post(route('vehicles.store'), vehiclePayload(['owner_type' => 'supplier', 'owner_id' => Supplier::factory()->create()->id]))
            ->assertSessionHasNoErrors();

        expect(Vehicle::query()->sole()->only('supplier_id', 'ally_id'))->toBe(['supplier_id' => null, 'ally_id' => $ally->id]);
    });

    test('requires the data of the legacy form', function () {
        $this->post(route('vehicles.store'), [])->assertSessionHasErrors([
            'plate', 'current_mileage', 'registration_date', 'registration_place', 'model_year', 'brand', 'line',
            'vehicle_class', 'engine_number', 'chassis_number', 'color', 'owner_type',
        ]);
        $this->post(route('vehicles.store'), vehiclePayload(['owner_type' => 'ally']))
            ->assertSessionHasErrors(['owner_id' => 'Selecciona el aliado dueño del vehículo.']);
    });

    test('the plate has three letters and three numbers and does not repeat', function () {
        $existing = Vehicle::factory()->create(['plate' => 'TSX123']);

        $this->post(route('vehicles.store'), vehiclePayload(['plate' => 'TS1234']))
            ->assertSessionHasErrors(['plate' => 'La placa debe tener 3 letras seguidas de 3 números (ej: ABC123).']);
        $this->post(route('vehicles.store'), vehiclePayload())
            ->assertSessionHasErrors(['plate' => 'La placa ya está registrada en otro vehículo.']);
        $this->put(route('vehicles.update', $existing), vehiclePayload())->assertSessionHasNoErrors();
    });

    test('validates a field live without saving anything', function () {
        Vehicle::factory()->create(['plate' => 'TSX123']);
        $live = ['Precognition' => 'true', 'Precognition-Validate-Only' => 'plate'];

        $this->withHeaders($live)->postJson(route('vehicles.store'), ['plate' => 'tsx 123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plate' => 'La placa ya está registrada en otro vehículo.'])
            ->assertJsonMissingValidationErrors('brand');
        $this->withHeaders($live)->postJson(route('vehicles.store'), ['plate' => 'abc987'])
            ->assertNoContent()
            ->assertHeader('Precognition-Success', 'true');
        $this->withHeaders(['Precognition' => 'true', 'Precognition-Validate-Only' => 'documents.soat.expires_on'])
            ->postJson(route('vehicles.store'), ['documents' => ['soat' => ['number' => '13245768']]])
            ->assertJsonValidationErrors(['documents.soat.expires_on' => 'El campo fecha de vencimiento (SOAT) es obligatorio.']);

        expect(Vehicle::query()->count())->toBe(1);
    });

    test('editing can change the owner', function () {
        $vehicle = Vehicle::factory()->ownedBy(Ally::factory()->create())->create();
        $supplier = Supplier::factory()->create();

        $this->put(route('vehicles.update', $vehicle), vehiclePayload(['owner_type' => 'supplier', 'owner_id' => $supplier->id]))
            ->assertRedirect(route('vehicles.show', $vehicle));

        expect($vehicle->refresh()->only('supplier_id', 'ally_id', 'plate'))->toBe(['supplier_id' => $supplier->id, 'ally_id' => null, 'plate' => 'TSX123']);
    });

    test('searches owners by type with their active vehicles; a user of a third party cannot', function () {
        $supplier = Supplier::factory()->create(['name' => 'TRANSPORTES ALEFT', 'nit' => '901024488']);
        Vehicle::factory()->ownedBy($supplier)->count(2)->create();
        Vehicle::factory()->ownedBy($supplier)->inactive()->create();
        Ally::factory()->create(['name' => 'ALEFT ALIADO']);

        $this->getJson(route('vehicles.search-owners', ['type' => 'supplier', 'search' => 'aleft']))
            ->assertExactJson([['value' => $supplier->id, 'label' => 'TRANSPORTES ALEFT', 'description' => 'NIT 901024488 · 2 vehículos activos']]);

        $this->actingAs(vehicleAllyUser(Ally::factory()->create()))
            ->getJson(route('vehicles.search-owners', ['type' => 'supplier']))
            ->assertForbidden();
    });
});

test('deactivates and activates a vehicle; vehicles cannot be deleted', function () {
    $vehicle = Vehicle::factory()->create();

    $this->post(route('vehicles.deactivate', $vehicle))->assertSessionHas('success', 'Vehículo inhabilitado: pasó a Inactivos.');
    expect($vehicle->refresh()->is_active)->toBeFalse();

    $this->post(route('vehicles.activate', $vehicle))->assertSessionHas('success', 'Vehículo habilitado.');
    expect($vehicle->refresh()->is_active)->toBeTrue();

    $this->delete("/vehicles/{$vehicle->id}")->assertMethodNotAllowed();
});

test('each action requires its permission', function () {
    $vehicle = Vehicle::factory()->create();
    $this->actingAs(User::factory()->withRole(BaseRole::Driver)->create());

    $this->get(route('vehicles.index'))->assertForbidden();
    $this->post(route('vehicles.store'), vehiclePayload())->assertForbidden();
    $this->put(route('vehicles.update', $vehicle), vehiclePayload())->assertForbidden();
    $this->post(route('vehicles.deactivate', $vehicle))->assertForbidden();
    $this->post(route('vehicles.documents.store', [$vehicle, 'soat']), ['expires_on' => '2030-01-01'])->assertForbidden();
    $this->post(route('vehicles.photos.store', $vehicle))->assertForbidden();
});

describe('documents in the vehicle form', function () {
    beforeEach(fn () => Storage::fake(Vehicle::FILES_DISK));

    test('registers the vehicle with the documents that were filled and skips the empty ones', function () {
        $this->post(route('vehicles.store'), vehiclePayload(['documents' => [
            'soat' => ['number' => ' 13245768 ', 'issued_on' => '2026-01-10', 'expires_on' => '2027-01-09', 'file' => UploadedFile::fake()->create('soat.pdf', 50, 'application/pdf')],
            'property_card' => ['number' => '10024567890'],
            'technical_inspection' => ['number' => '', 'issued_on' => '', 'expires_on' => ''],
        ]]))->assertSessionHasNoErrors();

        $documents = Vehicle::query()->sole()->currentDocuments()->get()->keyBy(fn (VehicleDocument $document) => $document->type->value);
        expect($documents->keys()->sort()->values()->all())->toBe(['property_card', 'soat'])
            ->and($documents['soat']->number)->toBe('13245768')
            ->and($documents['property_card']->expires_on)->toBeNull();
        Storage::disk(Vehicle::FILES_DISK)->assertExists($documents['soat']->file_path);
    });

    test('a filled document follows the same rules as in the vehicle page', function () {
        $this->post(route('vehicles.store'), vehiclePayload(['documents' => [
            'soat' => ['number' => '13245768'],
            'property_card' => ['issued_on' => '2026-01-10'],
            'contractual_policy' => ['issued_on' => '2026-05-01', 'expires_on' => '2026-04-01'],
        ]]))->assertSessionHasErrors([
            'documents.soat.expires_on' => 'El campo fecha de vencimiento (SOAT) es obligatorio.',
            'documents.property_card.number' => 'Escribe el número o adjunta el archivo de la tarjeta de propiedad.',
            'documents.contractual_policy.expires_on',
        ]);

        expect(Vehicle::query()->exists())->toBeFalse();
    });

    test('editing corrects the current documents, keeps their file and leaves the empty ones alone', function () {
        $vehicle = Vehicle::factory()->create(['plate' => 'TSX123']);
        $soat = VehicleDocument::factory()->for($vehicle)->create(['number' => 'VIEJO', 'file_path' => 'vehicles/x/soat.pdf']);
        $policy = VehicleDocument::factory()->for($vehicle)->create(['type' => VehicleDocumentType::AllRiskPolicy, 'number' => 'POL-1']);

        $this->get(route('vehicles.edit', $vehicle))->assertInertia(fn (Assert $page) => $page
            ->where('item.documents.soat.number', 'VIEJO')
            ->where('item.documents.soat.file_url', route('vehicles.documents.file', [$vehicle, $soat]))
        );

        $this->post(route('vehicles.update', $vehicle), vehiclePayload(['_method' => 'put', 'documents' => [
            'soat' => ['number' => 'NUEVO', 'expires_on' => '2027-03-01'],
        ]]))->assertRedirect(route('vehicles.show', $vehicle));

        expect($soat->refresh()->only('number', 'file_path', 'replaced_at'))->toBe(['number' => 'NUEVO', 'file_path' => 'vehicles/x/soat.pdf', 'replaced_at' => null])
            ->and($policy->refresh()->number)->toBe('POL-1')
            ->and($vehicle->documents()->count())->toBe(2);
    });
});

describe('documents', function () {
    beforeEach(fn () => Storage::fake(Vehicle::FILES_DISK));

    test('registers a document with its file and shows its status on the vehicle page', function () {
        $vehicle = Vehicle::factory()->create();

        $this->post(route('vehicles.documents.store', [$vehicle, 'soat']), [
            'number' => '13245768',
            'issued_on' => today()->subMonth()->toDateString(),
            'expires_on' => today()->addDays(20)->toDateString(),
            'file' => UploadedFile::fake()->create('soat.pdf', 100, 'application/pdf'),
        ])->assertSessionHas('success', 'SOAT guardado.');

        $document = $vehicle->documents()->sole();
        Storage::disk(Vehicle::FILES_DISK)->assertExists($document->file_path);
        $this->get(route('vehicles.show', $vehicle))->assertInertia(fn (Assert $page) => $page
            ->where('documents.0.type', 'soat')
            ->where('documents.0.current.status', 'expiring')
            ->where('documents.0.current.file_url', route('vehicles.documents.file', [$vehicle, $document]))
            ->where('documents.1.current', null)
        );
    });

    test('a document that expires needs its expiry date; the property card, its number or file', function () {
        $vehicle = Vehicle::factory()->create();

        $this->post(route('vehicles.documents.store', [$vehicle, 'technical_inspection']), ['number' => '1'])
            ->assertSessionHasErrors(['expires_on' => 'El campo fecha de vencimiento es obligatorio.']);
        $this->post(route('vehicles.documents.store', [$vehicle, 'property_card']), [])
            ->assertSessionHasErrors(['number' => 'Escribe el número o adjunta el archivo del documento.']);
        $this->post(route('vehicles.documents.store', [$vehicle, 'soat']), ['issued_on' => '2026-05-01', 'expires_on' => '2026-04-01'])
            ->assertSessionHasErrors('expires_on');
    });

    test('correcting replaces the current document and its file without keeping history', function () {
        $vehicle = Vehicle::factory()->create();
        $this->post(route('vehicles.documents.store', [$vehicle, 'soat']), ['expires_on' => '2027-01-01', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $firstFile = $vehicle->documents()->sole()->file_path;

        $this->post(route('vehicles.documents.store', [$vehicle, 'soat']), ['number' => '999', 'expires_on' => '2027-02-01', 'file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')]);

        $document = $vehicle->documents()->sole();
        expect($document->only('number', 'replaced_at'))->toBe(['number' => '999', 'replaced_at' => null])
            ->and($document->expires_on->toDateString())->toBe('2027-02-01');
        Storage::disk(Vehicle::FILES_DISK)->assertMissing($firstFile);
        Storage::disk(Vehicle::FILES_DISK)->assertExists($document->file_path);
    });

    test('renewing keeps the previous document and its file in the history', function () {
        $vehicle = Vehicle::factory()->create();
        $this->post(route('vehicles.documents.store', [$vehicle, 'soat']), ['number' => 'VIEJO', 'expires_on' => '2026-11-01', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $previous = $vehicle->documents()->sole();

        $this->post(route('vehicles.documents.renew', [$vehicle, 'soat']), ['number' => 'NUEVO', 'expires_on' => '2027-11-01'])
            ->assertSessionHas('success', 'SOAT renovado: el anterior quedó en el historial.');

        expect($previous->refresh()->replaced_at)->not->toBeNull()
            ->and($vehicle->currentDocuments()->sole()->only('number', 'file_path'))->toBe(['number' => 'NUEVO', 'file_path' => null]);
        Storage::disk(Vehicle::FILES_DISK)->assertExists($previous->file_path);
        $this->get(route('vehicles.show', $vehicle))->assertInertia(fn (Assert $page) => $page
            ->where('documents.0.current.number', 'NUEVO')
            ->where('documents.0.history.0.number', 'VIEJO')
        );
    });

    test('a renewal must expire after the current document, and needs one to renew', function () {
        $vehicle = Vehicle::factory()->create();

        $this->post(route('vehicles.documents.renew', [$vehicle, 'soat']), ['expires_on' => '2027-01-01'])
            ->assertSessionHasErrors(['expires_on' => 'Este documento todavía no está registrado: regístralo en lugar de renovarlo.']);

        $this->post(route('vehicles.documents.store', [$vehicle, 'soat']), ['expires_on' => '2027-01-01']);
        $this->post(route('vehicles.documents.renew', [$vehicle, 'soat']), ['expires_on' => '2026-12-01'])
            ->assertSessionHasErrors(['expires_on' => 'La renovación debe vencer después del documento actual (2027-01-01).']);
    });

    test('a document file is only served through its own vehicle to whoever can see it', function () {
        $ally = Ally::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $other = Vehicle::factory()->ownedBy($ally)->create();
        $document = VehicleDocument::factory()->for($vehicle)->create(['file_path' => 'vehicles/x/soat.pdf']);
        Storage::disk(Vehicle::FILES_DISK)->put('vehicles/x/soat.pdf', 'pdf');

        $this->get(route('vehicles.documents.file', [$vehicle, $document]))->assertOk();
        $this->get(route('vehicles.documents.file', [$other, $document]))->assertNotFound();
        $this->actingAs(vehicleAllyUser($ally))->get(route('vehicles.documents.file', [$vehicle, $document]))->assertForbidden();
    });
});

describe('photos', function () {
    beforeEach(fn () => Storage::fake(Vehicle::FILES_DISK));

    test('adds up to five photos per vehicle', function () {
        $vehicle = Vehicle::factory()->create();
        VehiclePhoto::factory()->for($vehicle)->count(3)->create();

        $this->post(route('vehicles.photos.store', $vehicle), ['photos' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg'), UploadedFile::fake()->image('3.jpg')]])
            ->assertSessionHasErrors(['photos' => 'Solo puedes agregar 2 foto(s) más: el máximo es 5 por vehículo.']);

        $this->post(route('vehicles.photos.store', $vehicle), ['photos' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.webp')]])
            ->assertSessionHas('success', 'Fotos agregadas.');

        expect($vehicle->photos()->count())->toBe(5);
        Storage::disk(Vehicle::FILES_DISK)->assertExists($vehicle->photos()->latest('id')->first()->path);
    });

    test('only accepts images', function () {
        $vehicle = Vehicle::factory()->create();

        $this->post(route('vehicles.photos.store', $vehicle), ['photos' => [UploadedFile::fake()->create('foto.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('photos.0');
    });

    test('deleting a photo removes its file', function () {
        $vehicle = Vehicle::factory()->create();
        $photo = VehiclePhoto::factory()->for($vehicle)->create();
        Storage::disk(Vehicle::FILES_DISK)->put($photo->path, 'jpg');

        $this->delete(route('vehicles.photos.destroy', [$vehicle, $photo]))->assertSessionHas('success', 'Foto eliminada.');

        expect(VehiclePhoto::query()->exists())->toBeFalse();
        Storage::disk(Vehicle::FILES_DISK)->assertMissing($photo->path);
    });
});
