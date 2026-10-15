import { useConfirm } from '@/composables/useConfirm';
import { router } from '@inertiajs/vue3';

/**
 * Inhabilitar y habilitar un cliente, con la misma confirmación que el legacy.
 */
export function useClientActivation() {
    const { confirm } = useConfirm();

    const deactivate = async (client: { id: number; name: string }) => {
        const confirmed = await confirm({
            title: '¿Inhabilitar cliente?',
            text: `${client.name} pasará a Inactivos. Podrás volver a habilitarlo luego.`,
            confirmText: 'Sí, inhabilitar',
            destructive: true,
        });

        if (confirmed) {
            router.post(route('clients.deactivate', { client: client.id }), {}, { preserveScroll: true });
        }
    };

    const activate = async (client: { id: number; name: string }) => {
        const confirmed = await confirm({
            title: '¿Habilitar cliente?',
            text: `${client.name} vuelve al listado de clientes activos.`,
            confirmText: 'Sí, habilitar',
        });

        if (confirmed) {
            router.post(route('clients.activate', { client: client.id }), {}, { preserveScroll: true });
        }
    };

    return { deactivate, activate };
}
