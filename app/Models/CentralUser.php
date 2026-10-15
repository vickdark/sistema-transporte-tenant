<?php

namespace App\Models;

use Database\Factories\CentralUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Administrador de la plataforma. Se autentica con el guard `central` en los dominios centrales
 * y no pertenece a ningún tenant.
 */
class CentralUser extends Authenticatable
{
    /** @use HasFactory<CentralUserFactory> */
    use CentralConnection, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
