<?php

namespace App\Actions\Migrations\Importers;

use App\Actions\Users\AssignUserRole;
use App\Models\Ally;
use App\Models\Client;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Rules\Username;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Usuarios legacy → usuarios de la empresa, con su rol (ya importado por RolesImporter) y su
 * contraseña bcrypt tal cual. Nombre y correo salen de la entidad vinculada (empleado, aliado,
 * proveedor o cliente); el vínculo como tal se completa cuando exista el importador de cada
 * entidad (Fase 2), que lo relee de la fuente.
 *
 * Falla cerrado: un usuario sin rol importado, sin exactamente una entidad, con un formato que
 * no se puede conservar o que choca con una cuenta existente va a cuarentena.
 */
class UsersImporter implements LegacyImporter
{
    public const LEGACY_TABLE = 'usuarios';

    /**
     * Columna de vínculo en `usuarios` => entidad legacy. El orden es la prioridad para quedarse
     * con un correo repetido entre cuentas (PLAN_MIGRACION.md §5.4: primero el empleado).
     *
     * @var array<string, string>
     */
    private const LINKS = [
        'EmpleadoID' => 'empleado',
        'AliadoID' => 'aliado',
        'ProveedorID' => 'proveedor',
        'ClienteID' => 'cliente',
    ];

    /**
     * Aliados y proveedores ya importados: id legacy => id nuevo.
     *
     * @var array{allies: array<string, int>, suppliers: array<string, int>, clients: array<string, int>}
     */
    private array $actorIds = ['allies' => [], 'suppliers' => [], 'clients' => []];

    public function __construct(private AssignUserRole $assignUserRole) {}

    public function import(ImportContext $context): void
    {
        $legacyUsers = $context->legacy->table('usuarios')
            ->select('ID as id', 'NombreUsuario as username', 'Contrasena as password', 'rol_id as role_id', ...array_keys(self::LINKS))
            ->orderBy('ID')
            ->get();

        $context->total($legacyUsers->count());
        $actors = $this->loadActors($context->legacy, $legacyUsers);
        $emails = $this->assignEmails($context, $legacyUsers, $actors);
        $roles = Role::query()->where('legacy_source', RolesImporter::LEGACY_TABLE)->get()->keyBy('legacy_id');
        $this->actorIds = [
            'allies' => Ally::query()->where('legacy_source', ThirdPartiesImporter::ALLIES_TABLE)->pluck('id', 'legacy_id')->all(),
            'suppliers' => Supplier::query()->where('legacy_source', ThirdPartiesImporter::SUPPLIERS_TABLE)->pluck('id', 'legacy_id')->all(),
            'clients' => Client::query()->where('legacy_source', ClientsImporter::LEGACY_TABLE)->pluck('id', 'legacy_id')->all(),
        ];

        foreach ($legacyUsers as $legacyUser) {
            $context->read();

            try {
                DB::transaction(function () use ($context, $legacyUser, $actors, $roles, $emails): void {
                    $this->importUser($context, $legacyUser, $actors, $roles, $emails[$legacyUser->id] ?? null);
                });
            } catch (QuarantinedRecord $record) {
                $context->quarantine(self::LEGACY_TABLE, $legacyUser->id, $record);
            }

            $context->tick();
        }

        $context->flush();
    }

    /**
     * @param  array<string, array<int, array{name: string|null, email: string|null}>>  $actors
     * @param  Collection<string, Role>  $roles
     */
    private function importUser(ImportContext $context, object $legacyUser, array $actors, Collection $roles, ?string $email): void
    {
        $links = $this->linksOf($legacyUser);
        $payload = ['username' => $legacyUser->username, 'role_id' => $legacyUser->role_id, 'links' => $links];

        if (count($links) !== 1) {
            throw new QuarantinedRecord(
                'Debe estar vinculado a exactamente una entidad (empleado, aliado, proveedor o cliente) y tiene '.count($links).'.',
                $payload,
            );
        }

        $link = array_key_first($links);
        $actor = $actors[$link][$links[$link]] ?? null;

        if ($actor === null) {
            throw new QuarantinedRecord('El '.self::LINKS[$link]." #{$links[$link]} al que está vinculado no existe en la fuente.", $payload);
        }

        $role = $roles->get((string) $legacyUser->role_id);

        if ($role === null) {
            throw new QuarantinedRecord(
                $legacyUser->role_id === null ? 'No tiene rol.' : "Su rol legacy #{$legacyUser->role_id} no está importado en la empresa.",
                $payload,
            );
        }

        $username = Str::lower(trim((string) $legacyUser->username));

        if (Validator::make(['username' => $username], ['username' => [new Username]])->fails()) {
            throw new QuarantinedRecord('El nombre de usuario no cumple el formato (3 a 50 caracteres: minúsculas, números, punto, guion o guion bajo).', $payload);
        }

        $passwordHash = (string) $legacyUser->password;

        if (! Hash::isHashed($passwordHash) || ! Hash::verifyConfiguration($passwordHash)) {
            throw new QuarantinedRecord('La contraseña no está en un formato bcrypt que se pueda conservar.', $payload);
        }

        $user = User::query()
            ->where('legacy_source', self::LEGACY_TABLE)
            ->where('legacy_id', (string) $legacyUser->id)
            ->first();

        $conflict = User::query()
            ->when($user, fn ($query) => $query->whereKeyNot($user->id))
            ->where(fn ($query) => $query->where('username', $username)->when($email, fn ($query) => $query->orWhere('email', $email)))
            ->first();

        if ($conflict !== null) {
            throw new QuarantinedRecord(
                "Choca con el usuario existente \"{$conflict->username}\" (mismo nombre de usuario o correo).",
                [...$payload, 'email' => $email, 'conflicting_user_id' => $conflict->id],
            );
        }

        $isNew = $user === null;
        $user ??= new User;

        $user->forceFill([
            'name' => trim((string) $actor['name']) ?: $username,
            'username' => $username,
            'email' => $email,
            'email_verified_at' => match (true) {
                $email === null => null,
                $user->email === $email && $user->email_verified_at !== null => $user->email_verified_at,
                default => now(),
            },
            'password' => $passwordHash,
            'legacy_source' => self::LEGACY_TABLE,
            'legacy_id' => (string) $legacyUser->id,
            // Si su aliado, proveedor o cliente ya se importó queda enlazado; si no, lo enlaza ese importador.
            'ally_id' => $link === 'AliadoID' ? ($this->actorIds['allies'][(string) $links[$link]] ?? null) : null,
            'supplier_id' => $link === 'ProveedorID' ? ($this->actorIds['suppliers'][(string) $links[$link]] ?? null) : null,
            'client_id' => $link === 'ClienteID' ? ($this->actorIds['clients'][(string) $links[$link]] ?? null) : null,
        ])->save();

        try {
            $this->assignUserRole->handle($user, $role);
        } catch (ValidationException $exception) {
            throw new QuarantinedRecord($exception->getMessage(), $payload);
        }

        $isNew ? $context->inserted() : $context->updated();
    }

    /**
     * Correo de cada usuario legacy, tomado de su entidad. Un correo repetido entre cuentas se
     * queda en una sola (prioridad por tipo de entidad, luego el usuario más antiguo); las demás
     * se importan sin correo y siguen entrando con su nombre de usuario legacy.
     *
     * @param  Collection<int, object>  $legacyUsers
     * @param  array<string, array<int, array{name: string|null, email: string|null}>>  $actors
     * @return array<int|string, string|null>
     */
    private function assignEmails(ImportContext $context, Collection $legacyUsers, array $actors): array
    {
        $emails = [];
        $claims = [];
        $priorities = array_flip(array_keys(self::LINKS));

        foreach ($legacyUsers as $legacyUser) {
            $emails[$legacyUser->id] = null;
            $links = $this->linksOf($legacyUser);

            if (count($links) !== 1) {
                continue;
            }

            $link = array_key_first($links);
            $email = Str::lower(trim((string) ($actors[$link][$links[$link]]['email'] ?? '')));

            if ($email === '') {
                continue;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $context->warning(
                    "El correo \"{$email}\" de su ".self::LINKS[$link].' no es válido: la cuenta se importa sin correo. '
                        ."Sigue entrando con su usuario \"{$legacyUser->username}\" y su contraseña de siempre.",
                    self::LEGACY_TABLE,
                    $legacyUser->id,
                );

                continue;
            }

            $claims[$email][] = [
                'id' => $legacyUser->id,
                'username' => $legacyUser->username,
                'entity' => self::LINKS[$link],
                'priority' => $priorities[$link],
            ];
        }

        foreach ($claims as $email => $claimants) {
            usort($claimants, fn (array $a, array $b): int => [$a['priority'], $a['id']] <=> [$b['priority'], $b['id']]);
            $owner = array_shift($claimants);
            $emails[$owner['id']] = $email;

            foreach ($claimants as $claimant) {
                $reason = $owner['priority'] < $claimant['priority']
                    ? "por ser la cuenta de {$owner['entity']}"
                    : 'por ser la cuenta más antigua';

                $context->warning(
                    "Comparte el correo {$email} con el usuario \"{$owner['username']}\" (usuarios #{$owner['id']}), que se queda con él {$reason}. "
                        ."Esta cuenta se importa sin correo: sigue entrando con su usuario \"{$claimant['username']}\" y su contraseña de siempre; "
                        .'solo no podrá recuperar la contraseña por correo.',
                    self::LEGACY_TABLE,
                    $claimant['id'],
                    ['email' => $email, 'kept_by' => $owner['id']],
                );
            }
        }

        return $emails;
    }

    /**
     * Nombre y correo de las entidades vinculadas, por columna de vínculo y id legacy.
     *
     * @param  Collection<int, object>  $legacyUsers
     * @return array<string, array<int, array{name: string|null, email: string|null}>>
     */
    private function loadActors(ConnectionInterface $legacy, Collection $legacyUsers): array
    {
        $actors = [];

        foreach (array_keys(self::LINKS) as $link) {
            $actors[$link] = [];
            $ids = $legacyUsers->pluck($link)->filter(fn ($id): bool => $id !== null)->map(fn ($id): int => (int) $id)->unique();

            foreach ($ids->chunk(500) as $chunk) {
                foreach ($this->actorQuery($legacy, $link)->whereIn('ID', $chunk->values()->all())->get() as $row) {
                    $actors[$link][(int) $row->id] = [
                        // Un cliente persona no tiene razón social: se usan sus nombres y apellidos.
                        'name' => trim((string) $row->name) !== ''
                            ? $row->name
                            : trim(($row->first_names ?? '').' '.($row->last_names ?? '')),
                        'email' => $row->email ?? null,
                    ];
                }
            }
        }

        return $actors;
    }

    private function actorQuery(ConnectionInterface $legacy, string $link): Builder
    {
        return match ($link) {
            'EmpleadoID' => $legacy->table('empleados')->select('ID as id', 'Nombre as name', 'Correo as email'),
            'AliadoID' => $legacy->table('aliados')->select('id', 'nombre as name', 'email'),
            'ProveedorID' => $legacy->table('proveedores')->select('id', 'nombre as name', 'email'),
            'ClienteID' => $legacy->table('clientes')->select('ID as id', 'RazonSocial as name', 'Nombres as first_names', 'Apellidos as last_names'),
        };
    }

    /**
     * Vínculos que tiene el usuario legacy: columna => id de la entidad.
     *
     * @return array<string, int>
     */
    private function linksOf(object $legacyUser): array
    {
        $links = [];

        foreach (array_keys(self::LINKS) as $link) {
            if ($legacyUser->{$link} !== null) {
                $links[$link] = (int) $legacyUser->{$link};
            }
        }

        return $links;
    }
}
