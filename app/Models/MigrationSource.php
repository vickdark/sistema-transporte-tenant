<?php

namespace App\Models;

use App\Enums\MigrationSourceType;
use Database\Factories\MigrationSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Fuente de datos legacy registrada en el panel central: la conexión a su base y, opcional, el
 * acceso SFTP a sus archivos. Las credenciales se guardan cifradas y nunca se envían al frontend.
 *
 * @property int $id
 * @property string $name
 * @property MigrationSourceType $type
 * @property array{host: string, port: int, database: string, username: string, password: string}|null $credentials
 * @property array{host: string, port: int, username: string, password: string|null, private_key: string|null, root: string}|null $files_connection
 *                                                                                                                                                  acceso SFTP a la carpeta del legacy que contiene `uploads/` (null = sin archivos)
 * @property string|null $notes
 */
class MigrationSource extends Model
{
    /** @use HasFactory<MigrationSourceFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'credentials',
        'files_connection',
        'notes',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'credentials',
        'files_connection',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MigrationSourceType::class,
            'credentials' => 'encrypted:array',
            'files_connection' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<CentralUser, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'created_by');
    }

    /**
     * @return HasMany<MigrationRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(MigrationRun::class);
    }
}
