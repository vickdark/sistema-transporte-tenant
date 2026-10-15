<?php

use App\Enums\BaseRole;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    tenant()->update(['name' => 'Transportes Kemuel SAS', 'nit' => '900980138', 'address' => 'Bosque Cl. 21', 'city' => 'Cartagena', 'phone' => '3008057167', 'email' => 'comercial@kemuel.test']);
    $this->actingAs(User::factory()->withRole(BaseRole::Administrator)->create());
});

/**
 * @return array<string, string>
 */
function companyPayload(array $overrides = []): array
{
    return [
        'address' => 'Bosque diagonal 21 a No 46 - 05, Cartagena',
        'phone' => '3008057167',
        'email' => 'Comercial@Kemuel.test',
        'service_type' => 'Transporte especial de pasajeros',
        'territorial_code' => '213',
        'habilitation_resolution' => '114',
        'habilitation_year' => '16',
        ...$overrides,
    ];
}

test('shows the identity from the central panel and starts with its contact data', function () {
    $this->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('company/Edit')
        ->where('identity', ['name' => 'Transportes Kemuel SAS', 'nit' => '900980138'])
        ->where('profile.address', 'Bosque Cl. 21, Cartagena')
        ->where('profile.phone', '3008057167')
        ->where('profile.email', 'comercial@kemuel.test')
        ->where('profile.service_type', 'Transporte especial de pasajeros')
    );
});

test('saves the company data and pads the habilitation resolution to four digits', function () {
    $this->put(route('company.update'), companyPayload())->assertSessionHas('success', 'Configuración guardada.');

    $profile = CompanyProfile::current();
    expect($profile->only('email', 'habilitation_resolution'))->toBe(['email' => 'comercial@kemuel.test', 'habilitation_resolution' => '0114'])
        ->and($profile->fuecPrefix())->toBe('213 0114 16')
        ->and(CompanyProfile::count())->toBe(1);
});

test('rejects habilitation data that would produce an invalid FUEC number', function () {
    $this->put(route('company.update'), companyPayload(['territorial_code' => '21', 'habilitation_resolution' => '01145', 'habilitation_year' => '2016']))
        ->assertSessionHasErrors(['territorial_code', 'habilitation_resolution', 'habilitation_year']);

    expect(CompanyProfile::current()->territorial_code)->toBeNull();
});

test('the identity cannot be changed from the company', function () {
    $this->put(route('company.update'), companyPayload(['name' => 'Otra SAS', 'nit' => '1']));

    expect(tenant()->refresh()->only('name', 'nit'))->toBe(['name' => 'Transportes Kemuel SAS', 'nit' => '900980138']);
});

test('uploads, replaces and removes the logo', function () {
    Storage::fake(CompanyProfile::FILES_DISK);

    $this->post(route('company.files.store', ['type' => 'logo']), ['file' => UploadedFile::fake()->image('logo.png')]);
    $first = CompanyProfile::current()->logo_path;
    $this->post(route('company.files.store', ['type' => 'logo']), ['file' => UploadedFile::fake()->image('nuevo.jpg')]);
    $second = CompanyProfile::current()->logo_path;

    Storage::disk(CompanyProfile::FILES_DISK)->assertMissing($first);
    Storage::disk(CompanyProfile::FILES_DISK)->assertExists($second);
    $this->get(route('company.files.show', ['type' => 'logo']))->assertOk();

    $this->delete(route('company.files.destroy', ['type' => 'logo']));

    Storage::disk(CompanyProfile::FILES_DISK)->assertMissing($second);
    expect(CompanyProfile::current()->logo_path)->toBeNull();
});

test('only accepts png or jpg images up to 2 MB', function (UploadedFile $file) {
    Storage::fake(CompanyProfile::FILES_DISK);

    $this->post(route('company.files.store', ['type' => 'signature']), ['file' => $file])->assertSessionHasErrors('file');

    expect(CompanyProfile::current()->signature_path)->toBeNull();
})->with([
    'svg' => fn () => UploadedFile::fake()->create('firma.svg', 10, 'image/svg+xml'),
    'pdf' => fn () => UploadedFile::fake()->create('firma.pdf', 10, 'application/pdf'),
    'more than 2 MB' => fn () => UploadedFile::fake()->image('firma.png')->size(2049),
]);

test('the logo is visible to every user but the signature only to whoever manages the company', function () {
    Storage::fake(CompanyProfile::FILES_DISK);
    $this->post(route('company.files.store', ['type' => 'logo']), ['file' => UploadedFile::fake()->image('logo.png')]);
    $this->post(route('company.files.store', ['type' => 'signature']), ['file' => UploadedFile::fake()->image('firma.png')]);

    $this->actingAs(User::factory()->withRole(BaseRole::Driver)->create());

    $this->get(route('company.files.show', ['type' => 'logo']))->assertOk();
    $this->get(route('company.files.show', ['type' => 'signature']))->assertForbidden();
});

test('managing the company configuration requires its permission', function () {
    $this->actingAs(User::factory()->withRole(BaseRole::Driver)->create());

    $this->get(route('company.edit'))->assertForbidden();
    $this->put(route('company.update'), companyPayload())->assertForbidden();
    $this->post(route('company.files.store', ['type' => 'logo']), ['file' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
});
