<?php

namespace App\Models;

use App\Enums\RouteStatus;
use App\Enums\RouteType;
use Database\Factories\TransportRouteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ruta (trayecto) de la empresa: punto de inicio y de fin. Su nombre es siempre "Inicio - Fin".
 * Se llama TransportRoute para no confundirla con las rutas web de Laravel; la tabla es `routes`.
 *
 * @property int $id
 * @property string $start_point
 * @property string $end_point
 * @property string|null $description
 * @property RouteType $type
 * @property RouteStatus $status
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 * @property-read string $name
 */
class TransportRoute extends Model
{
    /** @use HasFactory<TransportRouteFactory> */
    use HasFactory;

    protected $table = 'routes';

    /**
     * @var list<string>
     */
    protected $fillable = ['start_point', 'end_point', 'description', 'type', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RouteType::class,
            'status' => RouteStatus::class,
        ];
    }

    public function getNameAttribute(): string
    {
        return "{$this->start_point} - {$this->end_point}";
    }

    /**
     * Otra ruta con el mismo inicio y fin, sin distinguir mayúsculas ni espacios.
     */
    public static function findDuplicate(string $startPoint, string $endPoint, ?int $ignoreId = null): ?self
    {
        return static::query()
            ->whereRaw("lower(regexp_replace(trim(start_point), '\\s+', ' ', 'g')) = ?", [self::comparable($startPoint)])
            ->whereRaw("lower(regexp_replace(trim(end_point), '\\s+', ' ', 'g')) = ?", [self::comparable($endPoint)])
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->first();
    }

    private static function comparable(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/', ' ', trim($value)));
    }
}
