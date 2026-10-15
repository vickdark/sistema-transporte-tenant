<?php

namespace App\Actions\Migrations\Importers;

use App\Enums\ClientType;
use App\Enums\ContractKind;
use App\Enums\ContractPurpose;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * `contratos_administracion` (propios) y `contratos_aliados` legacy → contratos de la empresa.
 *
 * - El número del contrato (el del FUEC) es el id legacy, así los nuevos siguen desde el último
 *   y ningún número se repite.
 * - Los de aliados pertenecen al aliado dueño de su cliente; si el cliente no es de un aliado no
 *   se sabe de quién es el contrato y va a cuarentena. Cliente no importado u objeto desconocido
 *   también van a cuarentena (fallar cerrado).
 * - Si el cliente es una persona y el contrato no trae responsable, el responsable es el mismo
 *   cliente (como hace la pantalla).
 * - Lo que rompe las reglas de la pantalla (fechas, solapes, responsable vacío de una empresa) se migra como
 *   está y se reporta en un resumen; al editar esos contratos se pedirá corregirlo.
 */
class ContractsImporter implements LegacyImporter
{
    private const TABLES = [
        'contratos_administracion' => ContractKind::Own,
        'contratos_aliados' => ContractKind::Ally,
    ];

    /**
     * Contratos que no cumplen una regla de la pantalla: motivo => ids legacy.
     *
     * @var array<string, array<string, list<int|string>>>
     */
    private array $issues = [];

    public function import(ImportContext $context): void
    {
        $records = collect(self::TABLES)->map(fn (ContractKind $kind, string $table) => $context->legacy->table($table)
            ->select('id', 'cliente_id as client_id', 'objeto_contrato as purpose', 'fecha_inicio as starts_on', 'fecha_fin as ends_on', 'nit', 'razon_social', 'correo_empresa', 'telefono_empresa')
            ->orderBy('id')
            ->get());

        $context->total($records->sum(fn (Collection $rows): int => $rows->count()));

        $clients = Client::query()->where('legacy_source', ClientsImporter::LEGACY_TABLE)->get()->keyBy('legacy_id');

        foreach ($records as $table => $rows) {
            foreach ($rows as $row) {
                $context->read();

                try {
                    DB::transaction(fn () => $this->importContract($context, $table, self::TABLES[$table], $row, $clients));
                } catch (QuarantinedRecord $record) {
                    $context->quarantine($table, $row->id, $record);
                }

                $context->tick();
            }

            $this->collectOverlaps($table, $rows);
        }

        $this->report($context);
        $context->flush();
    }

    /**
     * @param  Collection<string, Client>  $clients
     */
    private function importContract(ImportContext $context, string $table, ContractKind $kind, object $row, Collection $clients): void
    {
        $payload = ['client_id' => $row->client_id, 'purpose' => $row->purpose];
        $client = $clients->get((string) $row->client_id)
            ?? throw new QuarantinedRecord("Su cliente #{$row->client_id} no está importado. Migra primero los clientes.", $payload);
        $purpose = ContractPurpose::fromLegacyText((string) $row->purpose)
            ?? throw new QuarantinedRecord("Objeto de contrato desconocido: \"{$row->purpose}\".", $payload);

        if ($kind === ContractKind::Ally && $client->ally_id === null) {
            throw new QuarantinedRecord("Es un contrato de aliado, pero su cliente #{$row->client_id} es de la empresa: no se sabe de qué aliado es.", $payload);
        }

        if ($kind === ContractKind::Own && $client->ally_id !== null) {
            $this->issues[$table]['contrato propio con un cliente de un aliado'][] = $row->id;
        }

        $data = [
            'kind' => $kind,
            'number' => (int) $row->id,
            'client_id' => $client->id,
            'ally_id' => $kind === ContractKind::Ally ? $client->ally_id : null,
            'purpose' => $purpose,
            'starts_on' => Carbon::parse($row->starts_on)->toDateString(),
            'ends_on' => Carbon::parse($row->ends_on)->toDateString(),
            'responsible_identification' => self::clean($row->nit),
            'responsible_name' => self::clean($row->razon_social),
            'responsible_email' => self::clean($row->correo_empresa),
            'responsible_phone' => self::clean($row->telefono_empresa),
            'legacy_source' => $table,
            'legacy_id' => (string) $row->id,
        ];

        $responsible = ['responsible_identification', 'responsible_name', 'responsible_email', 'responsible_phone'];

        if ($client->type === ClientType::Person && collect($data)->only($responsible)->filter()->isEmpty()) {
            $data = [
                ...$data,
                'responsible_identification' => $client->identification,
                'responsible_name' => $client->displayName(),
                'responsible_phone' => $client->phone,
            ];
        }

        $this->collectRuleIssues($table, $row->id, $data, $client->type === ClientType::Company);

        $contract = Contract::query()->where('legacy_source', $table)->where('legacy_id', (string) $row->id)->first();
        $isNew = $contract === null;
        ($contract ?? new Contract)->forceFill($data)->save();

        $isNew ? $context->inserted() : $context->updated();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function collectRuleIssues(string $table, int|string $legacyId, array $data, bool $isCompanyClient): void
    {
        $checks = [
            'fecha de fin anterior a la de inicio' => $data['ends_on'] < $data['starts_on'],
            'fecha de fin después del 31 de diciembre del año de inicio' => $data['ends_on'] > substr($data['starts_on'], 0, 4).'-12-31',
            'datos del responsable incompletos (cliente empresa)' => $isCompanyClient && in_array(null, [$data['responsible_identification'], $data['responsible_name'], $data['responsible_email'], $data['responsible_phone']], true),
        ];

        foreach (array_keys(array_filter($checks)) as $reason) {
            $this->issues[$table][$reason][] = $legacyId;
        }
    }

    /**
     * Contratos del mismo cliente cuyas fechas se cruzan (el legacy los creaba desde las órdenes).
     *
     * @param  Collection<int, object>  $rows
     */
    private function collectOverlaps(string $table, Collection $rows): void
    {
        foreach ($rows->groupBy('client_id') as $clientRows) {
            $sorted = $clientRows->sortBy('starts_on')->values();

            foreach ($sorted as $index => $row) {
                $next = $sorted->get($index + 1);

                if ($next !== null && $next->starts_on <= $row->ends_on) {
                    $this->issues[$table]['fechas que se cruzan con otro contrato del mismo cliente'][] = $next->id;
                }
            }
        }
    }

    private function report(ImportContext $context): void
    {
        foreach ($this->issues as $table => $reasons) {
            $label = self::TABLES[$table] === ContractKind::Own ? 'contratos propios' : 'contratos de aliados';

            foreach ($reasons as $reason => $legacyIds) {
                $context->warning(
                    count($legacyIds)." {$label} con {$reason}. Se migran como están; al editarlos se pedirá corregirlo.",
                    $table,
                    payload: ['legacy_ids' => array_values(array_unique($legacyIds))],
                );
            }
        }
    }

    private static function clean(mixed $value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }
}
