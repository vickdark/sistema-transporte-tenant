<?php

namespace App\Enums;

/**
 * Documentos de un vehículo (los mismos ocho del legacy). La tarjeta de propiedad no vence; los
 * demás exigen fecha de vencimiento, que es la que usan el FUEC y las alertas.
 */
enum VehicleDocumentType: string
{
    case Soat = 'soat';
    case PropertyCard = 'property_card';
    case TechnicalInspection = 'technical_inspection';
    case OperationCard = 'operation_card';
    case BimonthlyInspection = 'bimonthly_inspection';
    case ContractualPolicy = 'contractual_policy';
    case NonContractualPolicy = 'non_contractual_policy';
    case AllRiskPolicy = 'all_risk_policy';

    public function label(): string
    {
        return match ($this) {
            self::Soat => 'SOAT',
            self::PropertyCard => 'Tarjeta de propiedad',
            self::TechnicalInspection => 'Revisión técnico-mecánica',
            self::OperationCard => 'Tarjeta de operación',
            self::BimonthlyInspection => 'Revisión bimensual',
            self::ContractualPolicy => 'Póliza contractual',
            self::NonContractualPolicy => 'Póliza extracontractual',
            self::AllRiskPolicy => 'Póliza todo riesgo',
        };
    }

    /**
     * Grupo en el que se muestra en los formularios.
     */
    public function group(): string
    {
        return match ($this) {
            self::PropertyCard, self::OperationCard => 'Tarjetas',
            self::TechnicalInspection, self::BimonthlyInspection => 'Revisiones',
            self::Soat, self::ContractualPolicy, self::NonContractualPolicy, self::AllRiskPolicy => 'Seguros',
        };
    }

    public function expires(): bool
    {
        return $this !== self::PropertyCard;
    }

    /**
     * Etiquetas de los campos en el formulario (el legacy las cambia en la revisión bimensual).
     *
     * @return array{number: string, issued_on: string, expires_on: string}
     */
    public function fieldLabels(): array
    {
        return match ($this) {
            self::BimonthlyInspection => ['number' => 'Código interno', 'issued_on' => 'Fecha de la inspección', 'expires_on' => 'Próxima revisión'],
            self::ContractualPolicy, self::NonContractualPolicy, self::AllRiskPolicy => ['number' => 'Número de póliza', 'issued_on' => 'Fecha de expedición', 'expires_on' => 'Fecha de vencimiento'],
            default => ['number' => 'Número del documento', 'issued_on' => 'Fecha de expedición', 'expires_on' => 'Fecha de vencimiento'],
        };
    }

    public function legacyText(): string
    {
        return match ($this) {
            self::Soat => 'SOAT',
            self::PropertyCard => 'Tarjeta de Propiedad',
            self::TechnicalInspection => 'Técnico Mecánica',
            self::OperationCard => 'Tarjeta de Operación',
            self::BimonthlyInspection => 'Técnico Mecánica Bimensual',
            self::ContractualPolicy => 'Póliza Contractual',
            self::NonContractualPolicy => 'Póliza Extracontractual',
            self::AllRiskPolicy => 'Póliza Todo Riesgo',
        };
    }

    public static function fromLegacyText(?string $text): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->legacyText() === trim((string) $text)) {
                return $case;
            }
        }

        return null;
    }
}
