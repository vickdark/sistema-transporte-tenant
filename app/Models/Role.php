<?php

namespace App\Models;

use App\Enums\BaseRole;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Rol de una empresa (vive en su schema). Los roles base se identifican por `base_key`;
 * el protegido (Administrador) tiene siempre todos los permisos y no se borra ni se edita.
 *
 * @property int $id
 * @property string $name
 * @property string|null $base_key
 * @property string|null $description
 * @property bool $is_protected
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 */
class Role extends SpatieRole
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
        ];
    }

    public function baseRole(): ?BaseRole
    {
        return $this->base_key !== null ? BaseRole::tryFrom($this->base_key) : null;
    }

    public static function administrator(): self
    {
        return static::query()->where('base_key', BaseRole::Administrator->value)->firstOrFail();
    }
}
