<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Contracts\ContractOwner;
use App\Enums\ClientType;
use App\Enums\ContractKind;
use App\Enums\ContractPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ContractRequest;
use App\Http\Tables\Tenant\ContractClientsTable;
use App\Models\Ally;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Contratos propios (legacy `contratos`, rutas `contracts.*`) y de aliados (legacy
 * `contratos_aliados`, rutas `ally-contracts.*`). Son módulos distintos con las mismas pantallas:
 * el dueño de los contratos de cada petición lo resuelve ContractOwner. Como en el legacy, el
 * listado se agrupa por cliente, solo se editan los contratos no vencidos y no se eliminan.
 */
class ContractController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $owner = ContractOwner::fromRequest($request);

        if ($owner->needsAlly()) {
            return to_route('ally-contracts.select-ally');
        }

        Gate::authorize('viewAny', [Contract::class, $owner->kind]);
        $table = new ContractClientsTable($request, $owner);

        return Inertia::render('contracts/Index', [
            ...$this->context($request, $owner),
            'clients' => $table->rows(),
            'query' => $table->state(),
        ]);
    }

    public function export(Request $request, string $format): SymfonyResponse
    {
        $owner = ContractOwner::fromRequest($request);
        abort_if($owner->needsAlly(), 404);
        Gate::authorize('viewAny', [Contract::class, $owner->kind]);

        return (new ContractClientsTable($request, $owner))->export($format);
    }

    /**
     * Contratos de un cliente (vigentes, futuros y vencidos).
     */
    public function client(Request $request, Client $client): Response|RedirectResponse
    {
        $owner = ContractOwner::fromRequest($request);

        if ($owner->needsAlly()) {
            return to_route('ally-contracts.select-ally');
        }

        Gate::authorize('viewAny', [Contract::class, $owner->kind]);

        $contracts = $owner->contracts($client->contracts()->getQuery())->orderByDesc('starts_on')->get();
        $canContract = $owner->clients()->whereKey($client->id)->exists();
        abort_if($contracts->isEmpty() && ! $canContract, 404);

        return Inertia::render('contracts/Client', [
            ...$this->context($request, $owner),
            'client' => [
                'id' => $client->id,
                'name' => $client->displayName(),
                'type_label' => $client->type->label(),
                'identification' => $client->identification,
                'phone' => $client->phone,
                'address' => $client->address,
                'can_contract' => $canContract,
            ],
            'contracts' => $contracts->map(fn (Contract $contract): array => [
                ...$this->contractData($contract),
                'status' => $contract->status(),
                'can_update' => $request->user()->can('update', $contract),
                'edit_url' => route("{$owner->routePrefix()}.edit", $contract),
            ]),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $owner = ContractOwner::fromRequest($request);

        if ($owner->needsAlly()) {
            return to_route('ally-contracts.select-ally');
        }

        Gate::authorize('create', [Contract::class, $owner->kind]);

        $client = $request->integer('client') ? $owner->clients()->find($request->integer('client')) : null;

        return $this->renderForm($request, $owner, null, $client);
    }

    public function store(ContractRequest $request): RedirectResponse
    {
        $owner = ContractOwner::fromRequest($request);
        abort_if($owner->needsAlly(), 404);
        Gate::authorize('create', [Contract::class, $owner->kind]);

        $contract = DB::transaction(function () use ($request, $owner): Contract {
            $contract = new Contract($request->contractData());
            $contract->forceFill([
                'kind' => $owner->kind,
                'number' => Contract::nextNumber($owner->kind),
                'client_id' => $request->clientId(),
                'ally_id' => $owner->allyId,
            ])->save();

            return $contract;
        });

        return to_route("{$owner->routePrefix()}.client", $contract->client_id)
            ->with('success', "Contrato {$contract->formattedNumber()} creado exitosamente.");
    }

    public function edit(Request $request, Contract $contract): Response
    {
        $owner = $this->ownerOf($request, $contract);
        Gate::authorize('update', $contract);

        return $this->renderForm($request, $owner, $contract, $contract->client);
    }

    public function update(ContractRequest $request, Contract $contract): RedirectResponse
    {
        $owner = $this->ownerOf($request, $contract);
        Gate::authorize('update', $contract);

        $contract->update($request->contractData());

        return to_route("{$owner->routePrefix()}.client", $contract->client_id)
            ->with('success', "Contrato {$contract->formattedNumber()} actualizado exitosamente.");
    }

    /**
     * Clientes a los que se les puede hacer un contrato, con su contrato vigente si lo tienen.
     */
    public function searchClients(Request $request): JsonResponse
    {
        $owner = ContractOwner::fromRequest($request);
        abort_if($owner->needsAlly(), 404);
        Gate::authorize('create', [Contract::class, $owner->kind]);

        $search = $request->string('search')->trim()->value();
        $today = today()->toDateString();

        $clients = $owner->clients()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('identification', "%{$search}%")
                ->orWhereLike('first_names', "%{$search}%")
                ->orWhereLike('last_names', "%{$search}%")
                ->orWhereLike('business_name', "%{$search}%")
                ->orWhereLike('trade_name', "%{$search}%")))
            ->with(['contracts' => fn ($query) => $owner->contracts($query)->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)])
            ->orderBy('business_name')->orderBy('first_names')
            ->limit(15)
            ->get();

        return response()->json($clients->map(fn (Client $client): array => [
            ...$this->clientOption($client),
            'current_contract' => ($current = $client->contracts->first())
                ? ['number' => $current->formattedNumber(), 'ends_on' => $current->ends_on->toDateString()]
                : null,
        ]));
    }

    /**
     * Contratos de aliados: un usuario de la empresa elige con qué aliado trabajar.
     */
    public function selectAlly(Request $request): Response|RedirectResponse
    {
        if ($request->user()->ally_id !== null) {
            return to_route('ally-contracts.index');
        }

        Gate::authorize('viewAny', [Contract::class, ContractKind::Ally]);

        return Inertia::render('contracts/SelectAlly', [
            'searchUrl' => route('ally-contracts.search-allies'),
        ]);
    }

    /**
     * Buscador de aliados de la pantalla de selección (por nombre o NIT), con cuántos contratos
     * tiene cada uno.
     */
    public function searchAllies(Request $request): JsonResponse
    {
        abort_if($request->user()->ally_id !== null, 403);
        Gate::authorize('viewAny', [Contract::class, ContractKind::Ally]);

        $search = $request->string('search')->trim()->value();

        return response()->json(Ally::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->whereLike('name', "%{$search}%")->orWhereLike('nit', "%{$search}%")))
            ->withCount('contracts')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Ally $ally): array => [
                'value' => $ally->id,
                'label' => $ally->name,
                'description' => ($ally->nit ? "NIT {$ally->nit}" : 'Sin NIT')." · {$ally->contracts_count} contratos",
            ]));
    }

    public function storeAlly(Request $request): RedirectResponse
    {
        abort_if($request->user()->ally_id !== null, 403);
        Gate::authorize('viewAny', [Contract::class, ContractKind::Ally]);

        $ally = Ally::query()->findOrFail($request->integer('ally_id'));
        $request->session()->put(ContractOwner::SESSION_KEY, $ally->id);

        return to_route('ally-contracts.index')->with('success', "Gestionando información del aliado: {$ally->name}");
    }

    public function forgetAlly(Request $request): RedirectResponse
    {
        $request->session()->forget(ContractOwner::SESSION_KEY);

        return to_route('ally-contracts.select-ally');
    }

    /**
     * El contrato debe ser de la clase de la ruta y, para los de aliados, del aliado con el que
     * se trabaja.
     */
    private function ownerOf(Request $request, Contract $contract): ContractOwner
    {
        $owner = ContractOwner::fromRequest($request);
        abort_if($contract->kind !== $owner->kind || ($owner->kind === ContractKind::Ally && $contract->ally_id !== $owner->allyId), 404);

        return $owner;
    }

    private function renderForm(Request $request, ContractOwner $owner, ?Contract $contract, ?Client $client): Response
    {
        return Inertia::render('contracts/Form', [
            ...$this->context($request, $owner),
            'contract' => $contract ? [...$this->contractData($contract), 'starts_on' => $contract->starts_on->toDateString(), 'ends_on' => $contract->ends_on->toDateString()] : null,
            'client' => $client ? $this->clientOption($client) : null,
            'purposes' => array_map(fn (ContractPurpose $purpose): array => ['value' => $purpose->value, 'label' => $purpose->label()], ContractPurpose::cases()),
        ]);
    }

    /**
     * Datos comunes de las pantallas: qué módulo es, con qué aliado se trabaja y sus rutas.
     *
     * @return array<string, mixed>
     */
    private function context(Request $request, ContractOwner $owner): array
    {
        $prefix = $owner->routePrefix();
        $ally = $owner->ally();

        return [
            'module' => [
                'kind' => $owner->kind,
                'title' => $owner->kind->label(),
                'ally' => $ally ? ['id' => $ally->id, 'name' => $ally->name] : null,
                'can_change_ally' => $owner->kind === ContractKind::Ally && $request->user()->ally_id === null,
                'can_create' => $request->user()->can('create', [Contract::class, $owner->kind]),
                'urls' => [
                    'index' => route("{$prefix}.index"),
                    'create' => route("{$prefix}.create"),
                    'store' => route("{$prefix}.store"),
                    'search_clients' => route("{$prefix}.search-clients"),
                    'client' => route("{$prefix}.client", ['client' => '__client__']),
                    'export_xlsx' => route("{$prefix}.export", ['format' => 'xlsx']),
                    'export_pdf' => route("{$prefix}.export", ['format' => 'pdf']),
                    'forget_ally' => $owner->kind === ContractKind::Ally ? route('ally-contracts.forget-ally') : null,
                ],
            ],
        ];
    }

    /**
     * Cliente en el formulario: si es una persona, ella misma es la responsable del contrato.
     *
     * @return array{id: int, name: string, identification: string, phone: string, is_company: bool}
     */
    private function clientOption(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->displayName(),
            'identification' => $client->identification,
            'phone' => $client->phone,
            'is_company' => $client->type === ClientType::Company,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contractData(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'number' => $contract->formattedNumber(),
            'purpose' => $contract->purpose,
            'purpose_label' => $contract->purpose->label(),
            'starts_on' => $contract->starts_on->toDateString(),
            'ends_on' => $contract->ends_on->toDateString(),
            'responsible_identification' => $contract->responsible_identification,
            'responsible_name' => $contract->responsible_name,
            'responsible_email' => $contract->responsible_email,
            'responsible_phone' => $contract->responsible_phone,
            'update_url' => route(ContractOwner::fromRequest(request())->routePrefix().'.update', $contract),
        ];
    }
}
