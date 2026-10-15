<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenants\SendTenantUserInvitation;
use App\Actions\Users\AssignUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UserRequest;
use App\Http\Tables\Tenant\UsersTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Gestión de usuarios de la empresa (schema de la empresa activa).
 */
class UserController extends Controller
{
    public function __construct(
        private AssignUserRole $assignRole,
        private SendTenantUserInvitation $sendInvitation,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $table = new UsersTable($request);

        return Inertia::render('users/Index', [
            'users' => $table->rows(),
            'query' => $table->state(),
            'roles' => $this->roleOptions(),
            'can' => ['create' => $request->user()->can('create', User::class)],
        ]);
    }

    /**
     * Export the filtered list (same search, filters and order as the screen) to Excel or PDF.
     */
    public function export(Request $request, string $format): SymfonyResponse
    {
        Gate::authorize('viewAny', User::class);

        return (new UsersTable($request))->export($format);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('users/Create', [
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $password = $request->password();

        $user = DB::transaction(function () use ($request, $password): User {
            $user = User::forceCreate([
                ...$request->safe()->only(['name', 'username', 'email']),
                'password' => $password ?? Str::password(40),
                'email_verified_at' => $password !== null && $request->validated('email') ? now() : null,
            ]);

            $this->assignRole->handle($user, $request->role());

            return $user;
        });

        if ($password === null) {
            $this->sendInvitation->handle(tenant(), $user);

            return to_route('users.index')->with('success', "Usuario {$user->username} creado. Se envió la invitación a {$user->email}.");
        }

        return to_route('users.index')->with('success', "Usuario {$user->username} creado.");
    }

    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role_id' => $user->roles->first()?->id,
                'invitation_pending' => $user->hasPendingInvitation(),
            ],
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $password = $request->password();

        DB::transaction(function () use ($request, $user, $password): void {
            $user->fill($request->safe()->only(['name', 'username', 'email']));

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            if ($password !== null) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'invited_at' => null,
                ]);
            }

            $user->save();

            $this->assignRole->handle($user, $request->role());
        });

        if ($request->boolean('send_invitation')) {
            $this->sendInvitation->handle(tenant(), $user);

            return to_route('users.index')->with('success', "Usuario {$user->username} actualizado. Se envió la invitación a {$user->email}.");
        }

        return to_route('users.index')->with('success', "Usuario {$user->username} actualizado.");
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        AssignUserRole::ensureIsNotTheLastAdministrator($user, 'user');

        $user->delete();

        return back()->with('success', "Usuario {$user->username} eliminado.");
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function roleOptions(): array
    {
        return Role::query()
            ->orderByDesc('is_protected')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role): array => ['value' => $role->id, 'label' => $role->name])
            ->all();
    }
}
