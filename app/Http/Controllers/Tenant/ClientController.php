<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ClientType;
use App\Enums\IdentificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ClientRequest;
use App\Http\Tables\Tenant\ClientsTable;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Clientes (legacy: módulo `clientes` y `clientes/inactivos`). Cada usuario trabaja con los
 * clientes de su dueño: los de su aliado o, si no pertenece a uno, los de la empresa. Los
 * clientes se inhabilitan y se habilitan; no se eliminan.
 */
class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Client::class);

        $table = new ClientsTable($request);

        return Inertia::render('clients/Index', [
            'clients' => $table->rows(),
            'query' => $table->state(),
            'types' => $this->options(ClientType::cases()),
            'can' => ['create' => $request->user()->can('create', Client::class)],
        ]);
    }

    public function export(Request $request, string $format): SymfonyResponse
    {
        Gate::authorize('viewAny', Client::class);

        return (new ClientsTable($request))->export($format);
    }

    public function show(Request $request, Client $client): Response
    {
        Gate::authorize('view', $client);

        return Inertia::render('clients/Show', [
            'client' => [
                ...$this->formData($client),
                'id' => $client->id,
                'name' => $client->displayName(),
                'type' => $client->type,
                'type_label' => $client->type->label(),
                'identification_type_label' => $client->identification_type->label(),
                'is_active' => $client->is_active,
                'owner' => $client->ally?->name,
                'users_count' => $client->users()->count(),
            ],
            'can' => [
                'update' => $request->user()->can('update', $client),
                'toggle_active' => $request->user()->can('toggleActive', $client),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Client::class);

        return $this->renderForm(null);
    }

    /**
     * El cliente queda a nombre del dueño de quien lo crea: su aliado o la empresa.
     */
    public function store(ClientRequest $request): RedirectResponse
    {
        Gate::authorize('create', Client::class);

        $client = new Client($request->clientData());
        $client->type = $request->clientType();
        $client->ally_id = $request->user()->ally_id;
        $client->save();

        return to_route('clients.index')->with('success', 'Cliente registrado correctamente.');
    }

    public function edit(Client $client): Response
    {
        Gate::authorize('update', $client);

        return $this->renderForm($client);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $client->update($request->clientData());

        return to_route('clients.index')->with('success', 'Cliente actualizado correctamente.');
    }

    public function deactivate(Client $client): RedirectResponse
    {
        Gate::authorize('toggleActive', $client);

        $client->forceFill(['is_active' => false])->save();

        return back()->with('success', 'Cliente inhabilitado correctamente.');
    }

    public function activate(Client $client): RedirectResponse
    {
        Gate::authorize('toggleActive', $client);

        $client->forceFill(['is_active' => true])->save();

        return back()->with('success', 'Cliente habilitado correctamente.');
    }

    private function renderForm(?Client $client): Response
    {
        return Inertia::render('clients/Form', [
            'client' => $client ? ['id' => $client->id, 'type' => $client->type, ...$this->formData($client)] : null,
            'types' => $this->options(ClientType::cases()),
            'identificationTypes' => $this->options(IdentificationType::cases()),
            'suggestedIdentificationTypes' => collect(ClientType::cases())
                ->mapWithKeys(fn (ClientType $type): array => [$type->value => $type->suggestedIdentificationType()->value]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Client $client): array
    {
        return [
            'identification_type' => $client->identification_type,
            ...$client->only([
                'identification', 'check_digit', 'branch_code', 'first_names', 'last_names',
                'business_name', 'trade_name', 'city', 'address', 'phone', 'extension',
            ]),
        ];
    }

    /**
     * @param  list<ClientType|IdentificationType>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn (ClientType|IdentificationType $case): array => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
