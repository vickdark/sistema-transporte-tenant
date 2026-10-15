<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CompanyProfileRequest;
use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Configuración de la empresa: datos para sus documentos, habilitación del FUEC, logo y firma.
 * La razón social y el NIT se muestran pero los administra el panel central.
 */
class CompanyProfileController extends Controller
{
    /**
     * Columna de la ruta de cada archivo de la empresa.
     */
    private const FILE_COLUMNS = ['logo' => 'logo_path', 'signature' => 'signature_path'];

    public function edit(): Response
    {
        $profile = CompanyProfile::current();
        Gate::authorize('update', $profile);

        return Inertia::render('company/Edit', [
            'identity' => ['name' => tenant('name'), 'nit' => tenant('nit')],
            'profile' => [
                ...$profile->only(['address', 'phone', 'email', 'service_type', 'territorial_code', 'habilitation_resolution', 'habilitation_year']),
                'logo_url' => $this->fileUrl($profile, 'logo'),
                'signature_url' => $this->fileUrl($profile, 'signature'),
            ],
        ]);
    }

    public function update(CompanyProfileRequest $request): RedirectResponse
    {
        $profile = CompanyProfile::current();
        Gate::authorize('update', $profile);

        $profile->update($request->profile());

        return back()->with('success', 'Configuración guardada.');
    }

    /**
     * Upload (or replace) the logo or the signature. Images only: SVG is not accepted because it
     * can carry scripts.
     */
    public function storeFile(Request $request, string $type): RedirectResponse
    {
        $profile = CompanyProfile::current();
        Gate::authorize('update', $profile);

        $request->validate(
            ['file' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048']],
            attributes: ['file' => $type === 'logo' ? 'logo' : 'firma'],
        );

        $column = self::FILE_COLUMNS[$type];
        $previous = $profile->{$column};

        $profile->forceFill([$column => $request->file('file')->store('company', CompanyProfile::FILES_DISK)])->save();

        if ($previous) {
            Storage::disk(CompanyProfile::FILES_DISK)->delete($previous);
        }

        return back()->with('success', $type === 'logo' ? 'Logo actualizado.' : 'Firma actualizada.');
    }

    public function destroyFile(string $type): RedirectResponse
    {
        $profile = CompanyProfile::current();
        Gate::authorize('update', $profile);

        $column = self::FILE_COLUMNS[$type];

        if ($profile->{$column}) {
            Storage::disk(CompanyProfile::FILES_DISK)->delete($profile->{$column});
            $profile->forceFill([$column => null])->save();
        }

        return back()->with('success', $type === 'logo' ? 'Logo eliminado.' : 'Firma eliminada.');
    }

    /**
     * Serve a company file. The logo is visible to every user of the company; the signature only
     * to whoever manages the configuration (in documents it is embedded by the server).
     */
    public function showFile(string $type): StreamedResponse
    {
        $profile = CompanyProfile::current();

        if ($type === 'signature') {
            Gate::authorize('update', $profile);
        }

        $path = $profile->{self::FILE_COLUMNS[$type]};
        abort_unless($path && Storage::disk(CompanyProfile::FILES_DISK)->exists($path), 404);

        return Storage::disk(CompanyProfile::FILES_DISK)->response($path, headers: ['Cache-Control' => 'private, max-age=3600']);
    }

    private function fileUrl(CompanyProfile $profile, string $type): ?string
    {
        if (! $profile->{self::FILE_COLUMNS[$type]}) {
            return null;
        }

        // La versión cambia al reemplazar el archivo, así el navegador no muestra el anterior.
        return route('company.files.show', ['type' => $type, 'v' => $profile->updated_at?->timestamp]);
    }
}
