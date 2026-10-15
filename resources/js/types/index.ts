import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User | null;
    /** Permisos del usuario en la empresa (solo para mostrar u ocultar opciones). */
    permissions: string[];
    role: string | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    /** Permiso necesario para ver la opción en el menú. */
    permission?: string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: { success: string | null; warning: string | null };
    tenant: Tenant | null;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface Tenant {
    id: string;
    name: string | null;
}

export interface User {
    id: number;
    name: string;
    username?: string;
    email: string | null;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

/**
 * Estado de una tabla resuelta en el servidor (App\Http\Tables\DataTable::state()): búsqueda,
 * orden, tamaño de página y los filtros propios de cada listado.
 */
export interface TableQuery {
    search: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    [filter: string]: string | number | null;
}

export interface TableColumn {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'right';
    /** Columna principal: encabeza la tarjeta en móvil. */
    primary?: boolean;
}

export interface SelectOption {
    value: string;
    label: string;
}
