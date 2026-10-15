<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro legacy repetido que la migración unificó: su id legacy apunta (`target_id`) al
 * registro que sí se importó desde la misma tabla legacy.
 *
 * @property int $id
 * @property string $legacy_source
 * @property string $legacy_id
 * @property int $target_id
 */
class LegacyAlias extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['legacy_source', 'legacy_id', 'target_id'];

    /**
     * Id del registro importado al que corresponde un id legacy, contando los unificados.
     *
     * @param  class-string<Model>  $modelClass  modelo con columnas legacy_source / legacy_id
     */
    public static function resolve(string $modelClass, string $legacySource, int|string $legacyId): ?int
    {
        $id = $modelClass::query()->where('legacy_source', $legacySource)->where('legacy_id', (string) $legacyId)->value('id')
            ?? static::query()->where('legacy_source', $legacySource)->where('legacy_id', (string) $legacyId)->value('target_id');

        return $id !== null ? (int) $id : null;
    }
}
