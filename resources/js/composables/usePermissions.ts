import { usePage } from '@inertiajs/vue3';

/**
 * Permisos del usuario de la empresa. Solo sirven para mostrar u ocultar opciones de la interfaz:
 * el servidor vuelve a autorizar cada acción con sus Policies.
 */
export function usePermissions() {
    const page = usePage();

    const can = (permission: string): boolean => page.props.auth.permissions.includes(permission);

    return { can };
}
