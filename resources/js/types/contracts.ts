/** Contexto de las pantallas de contratos (propios o de aliados), lo envía ContractController. */
export interface ContractModule {
    kind: 'own' | 'ally';
    title: string;
    ally: { id: number; name: string } | null;
    can_change_ally: boolean;
    can_create: boolean;
    urls: {
        index: string;
        create: string;
        store: string;
        search_clients: string;
        /** Con el marcador `__client__` en lugar del id. */
        client: string;
        export_xlsx: string;
        export_pdf: string;
        forget_ally: string | null;
    };
}

export interface ContractData {
    id: number;
    number: string;
    purpose: string;
    purpose_label: string;
    starts_on: string;
    ends_on: string;
    responsible_identification: string | null;
    responsible_name: string | null;
    responsible_email: string | null;
    responsible_phone: string | null;
    update_url: string;
}

export const clientUrl = (module: ContractModule, clientId: number): string => module.urls.client.replace('__client__', String(clientId));

/** Fecha ISO (2026-10-15) en formato local (15/10/2026). */
export const formatDate = (date: string | null): string => (date ? date.split('-').reverse().join('/') : '—');
