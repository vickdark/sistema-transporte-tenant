<?php

namespace App\Models;

use App\Enums\ContractKind;
use App\Enums\ContractPurpose;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contrato de transporte con un cliente: propio (de la empresa) o de un aliado. Un contrato está
 * vigente entre su fecha de inicio y de fin; los vencidos ya no se editan.
 *
 * @property int $id
 * @property ContractKind $kind
 * @property int $number número del contrato en el FUEC (consecutivo por clase)
 * @property int $client_id
 * @property int|null $ally_id aliado dueño (solo contratos de aliados)
 * @property ContractPurpose $purpose
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $responsible_identification
 * @property string|null $responsible_name
 * @property string|null $responsible_email
 * @property string|null $responsible_phone
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 * @property-read Client $client
 * @property-read Ally|null $ally
 */
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purpose',
        'starts_on',
        'ends_on',
        'responsible_identification',
        'responsible_name',
        'responsible_email',
        'responsible_phone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ContractKind::class,
            'purpose' => ContractPurpose::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Ally, $this>
     */
    public function ally(): BelongsTo
    {
        return $this->belongsTo(Ally::class);
    }

    /**
     * Contratos de una clase y un dueño: los propios, o los del aliado indicado.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOwnedBy(Builder $query, ContractKind $kind, ?int $allyId): void
    {
        $query->where('kind', $kind)->when(
            $kind === ContractKind::Ally,
            fn (Builder $query) => $query->where('ally_id', $allyId),
            fn (Builder $query) => $query->whereNull('ally_id'),
        );
    }

    /**
     * vigente, futuro o vencido según la fecha de hoy.
     *
     * @return 'current'|'upcoming'|'expired'
     */
    public function status(): string
    {
        $today = today();

        return match (true) {
            $this->ends_on->lt($today) => 'expired',
            $this->starts_on->gt($today) => 'upcoming',
            default => 'current',
        };
    }

    public function isEditable(): bool
    {
        return $this->status() !== 'expired';
    }

    /**
     * Número con 4 dígitos, como aparece en el FUEC.
     */
    public function formattedNumber(): string
    {
        return str_pad((string) $this->number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Siguiente número de la clase. Debe llamarse dentro de una transacción: el bloqueo evita que
     * dos contratos creados a la vez reciban el mismo número.
     */
    public static function nextNumber(ContractKind $kind): int
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["contracts.number.{$kind->value}"]);

        return (int) static::query()->where('kind', $kind)->max('number') + 1;
    }
}
