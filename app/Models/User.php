<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario de una empresa de transporte. La tabla vive en el schema de cada empresa, así que
 * solo se puede consultar con la tenancy inicializada (o dentro de $tenant->run()).
 * Ingresa con su usuario o su correo (opcional) y tiene un solo rol (AssignUserRole).
 *
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string|null $email
 * @property string|null $legacy_source tabla legacy de la que se migró (ver App\Actions\Migrations)
 * @property string|null $legacy_id
 * @property int|null $ally_id aliado al que pertenece la cuenta (portal del aliado)
 * @property int|null $supplier_id proveedor al que pertenece la cuenta (portal del proveedor)
 * @property int|null $client_id cliente al que pertenece la cuenta (portal del cliente)
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Formato fijo de fechas: el panel central lee usuarios dentro de $tenant->run() y los usa
     * después, cuando la conexión del schema ya no existe para preguntarle su formato.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Guard de los usuarios de empresa. Roles y permisos se crean siempre con él: no se toma el
     * guard por defecto porque en el panel central ese es "central".
     */
    public const GUARD = 'web';

    /**
     * @var string
     */
    protected $guard_name = self::GUARD;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'invited_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ally, $this>
     */
    public function ally(): BelongsTo
    {
        return $this->belongsTo(Ally::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function hasPendingInvitation(): bool
    {
        return $this->invited_at !== null && $this->invitation_accepted_at === null;
    }

    /**
     * Huella de la contraseña actual que firma los enlaces de invitación: al definir
     * la contraseña cambia, y el enlace ya usado deja de ser válido.
     */
    public function invitationHash(): string
    {
        return hash_hmac('sha256', $this->password, config('app.key'));
    }
}
