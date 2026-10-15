<?php

namespace App\Models;

use Database\Factories\CompanyProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuración de la empresa para sus documentos (vive en su schema, una sola fila con id 1).
 * La razón social y el NIT son del inquilino (`tenants`, panel central) y aquí solo se leen.
 *
 * @property int $id
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property string $service_type
 * @property string|null $territorial_code
 * @property string|null $habilitation_resolution
 * @property string|null $habilitation_year
 * @property string|null $logo_path
 * @property string|null $signature_path
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 */
class CompanyProfile extends Model
{
    /** @use HasFactory<CompanyProfileFactory> */
    use HasFactory;

    public const DEFAULT_SERVICE_TYPE = 'Transporte especial de pasajeros';

    /**
     * Archivos de la empresa (logo y firma) en el disco local, que la tenancy aísla por empresa.
     */
    public const FILES_DISK = 'local';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'address',
        'phone',
        'email',
        'service_type',
        'territorial_code',
        'habilitation_resolution',
        'habilitation_year',
    ];

    /**
     * La fila de la empresa activa. La primera vez se crea con los datos de contacto que el panel
     * central registró para el inquilino.
     */
    public static function current(): self
    {
        return static::query()->find(1) ?? tap(new static, function (self $profile): void {
            $tenant = tenant();
            $profile->forceFill([
                'id' => 1,
                'address' => collect([$tenant?->address, $tenant?->city])->filter()->implode(', ') ?: null,
                'phone' => $tenant?->phone,
                'email' => $tenant?->email,
                'service_type' => self::DEFAULT_SERVICE_TYPE,
            ])->save();
        });
    }

    /**
     * Prefijo del número del FUEC (dirección territorial, resolución y año de habilitación), o
     * null mientras falte alguno de los tres.
     */
    public function fuecPrefix(): ?string
    {
        if (! $this->territorial_code || ! $this->habilitation_resolution || ! $this->habilitation_year) {
            return null;
        }

        return "{$this->territorial_code} {$this->habilitation_resolution} {$this->habilitation_year}";
    }
}
