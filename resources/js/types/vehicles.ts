/** Estado de un documento según su vencimiento (App\Models\VehicleDocument::status()). */
export type DocumentStatus = 'valid' | 'expiring' | 'expired' | 'no_expiry';

export interface VehicleDocumentData {
    id: number;
    number: string | null;
    issued_on: string | null;
    expires_on: string | null;
    status: DocumentStatus;
    file_url: string | null;
    replaced_at: string | null;
}

/** Un tipo de documento del vehículo con su vigente y su historial. */
export interface VehicleDocumentSlot {
    type: string;
    label: string;
    expires: boolean;
    field_labels: { number: string; issued_on: string; expires_on: string };
    current: VehicleDocumentData | null;
    history: VehicleDocumentData[];
}

export interface VehiclePhotoData {
    id: number;
    url: string;
}
