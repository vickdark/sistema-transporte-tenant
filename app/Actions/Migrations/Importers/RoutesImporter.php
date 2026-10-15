<?php

namespace App\Actions\Migrations\Importers;

use App\Enums\RouteStatus;
use App\Enums\RouteType;
use App\Models\LegacyAlias;
use App\Models\TransportRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `rutas` legacy → rutas de la empresa, con su fecha de creación original e inicio y fin sin
 * espacios sobrantes. Fallar cerrado: tipo o estado desconocidos van a cuarentena.
 *
 * Las rutas repetidas (mismo inicio y fin sin distinguir mayúsculas ni espacios) se unifican:
 * entra la de id legacy más antiguo, activa si alguna del grupo lo estaba y con la primera
 * descripción disponible. Las demás no se cargan (omitidas) y quedan en `legacy_aliases`
 * apuntando a la que entró, para que las órdenes que las usaban encuentren su ruta.
 */
class RoutesImporter implements LegacyImporter
{
    public const LEGACY_TABLE = 'rutas';

    /**
     * Prioridad del estado de la ruta unificada: si alguna del grupo está activa, queda activa.
     */
    private const STATUS_PRIORITY = [RouteStatus::Active->value => 0, RouteStatus::Maintenance->value => 1, RouteStatus::Inactive->value => 2];

    public function import(ImportContext $context): void
    {
        $legacyRoutes = $context->legacy->table('rutas')
            ->select('ID as id', 'PuntoInicio as start_point', 'PuntoFin as end_point', 'Descripcion as description', 'TipoRuta as type', 'Estado as status', 'FechaCreacion as created_at')
            ->orderBy('ID')
            ->get();

        $context->total($legacyRoutes->count());

        $valid = collect();

        foreach ($legacyRoutes as $legacyRoute) {
            $context->read();

            try {
                $valid->push($this->parse($legacyRoute));
            } catch (QuarantinedRecord $record) {
                $context->quarantine(self::LEGACY_TABLE, $legacyRoute->id, $record);
            }
        }

        $groups = $valid->groupBy(fn (array $route): string => Str::lower("{$route['start_point']}|{$route['end_point']}"));

        foreach ($groups as $group) {
            DB::transaction(fn () => $this->importGroup($context, $group->sortBy('legacy_id')->values()));
            $context->tick();
        }

        $context->flush();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $group
     */
    private function importGroup(ImportContext $context, Collection $group): void
    {
        $canonical = $group->first();
        $duplicates = $group->slice(1);

        $route = TransportRoute::query()->where('legacy_source', self::LEGACY_TABLE)->where('legacy_id', (string) $canonical['legacy_id'])->first();
        $isNew = $route === null;
        $route ??= new TransportRoute;

        $route->forceFill([
            'start_point' => $canonical['start_point'],
            'end_point' => $canonical['end_point'],
            'description' => $group->pluck('description')->filter()->first(),
            'type' => $canonical['type'],
            'status' => $group->sortBy(fn (array $route): int => self::STATUS_PRIORITY[$route['status']->value])->first()['status'],
            'legacy_source' => self::LEGACY_TABLE,
            'legacy_id' => (string) $canonical['legacy_id'],
            'created_at' => $canonical['created_at'],
        ])->save();

        $isNew ? $context->inserted() : $context->updated();

        if ($duplicates->isEmpty()) {
            return;
        }

        $duplicateIds = $duplicates->pluck('legacy_id')->map(fn ($id): string => (string) $id);

        foreach ($duplicateIds as $legacyId) {
            LegacyAlias::query()->updateOrCreate(
                ['legacy_source' => self::LEGACY_TABLE, 'legacy_id' => $legacyId],
                ['target_id' => $route->id],
            );
            $context->skipped();
        }

        // Una corrida anterior pudo haberlas cargado por separado; aún no hay órdenes que las usen.
        TransportRoute::query()->where('legacy_source', self::LEGACY_TABLE)->whereIn('legacy_id', $duplicateIds)->delete();

        $context->warning(
            ($duplicates->count() + 1)." rutas \"{$route->name}\" eran la misma: se unificaron en la #{$canonical['legacy_id']} (también "
                .$duplicateIds->map(fn (string $id): string => "#{$id}")->implode(', ').').',
            self::LEGACY_TABLE,
            $canonical['legacy_id'],
            ['kept' => $canonical['legacy_id'], 'merged' => $duplicateIds->all()],
        );
    }

    /**
     * @return array{legacy_id: int|string, start_point: string, end_point: string, description: string|null, type: RouteType, status: RouteStatus, created_at: Carbon}
     */
    private function parse(object $legacyRoute): array
    {
        $payload = ['start_point' => $legacyRoute->start_point, 'end_point' => $legacyRoute->end_point];
        $type = match (self::normalized($legacyRoute->type)) {
            'urbana' => RouteType::Urban,
            'rural' => RouteType::Rural,
            'montanosa' => RouteType::Mountain,
            'costera' => RouteType::Coastal,
            'desertica' => RouteType::Desert,
            default => throw new QuarantinedRecord("Tipo de ruta desconocido: \"{$legacyRoute->type}\".", $payload),
        };
        $status = match (self::normalized($legacyRoute->status)) {
            'activa' => RouteStatus::Active,
            'inactiva' => RouteStatus::Inactive,
            'mantenimiento' => RouteStatus::Maintenance,
            default => throw new QuarantinedRecord("Estado de ruta desconocido: \"{$legacyRoute->status}\".", $payload),
        };

        $startPoint = self::clean($legacyRoute->start_point);
        $endPoint = self::clean($legacyRoute->end_point);

        if ($startPoint === null || $endPoint === null) {
            throw new QuarantinedRecord('No tiene punto de inicio o de fin.', $payload);
        }

        return [
            'legacy_id' => $legacyRoute->id,
            'start_point' => $startPoint,
            'end_point' => $endPoint,
            'description' => self::clean($legacyRoute->description),
            'type' => $type,
            'status' => $status,
            'created_at' => $legacyRoute->created_at ? Carbon::parse($legacyRoute->created_at) : now(),
        ];
    }

    private static function clean(mixed $value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }

    /**
     * Minúsculas y sin tildes, para comparar los enums del legacy.
     */
    private static function normalized(mixed $value): string
    {
        return Str::lower(Str::ascii(trim((string) $value)));
    }
}
