import { useConfirm } from '@/composables/useConfirm';
import { router } from '@inertiajs/vue3';

/**
 * Inhabilitar y habilitar una ruta. No se eliminan (las órdenes las usan): inhabilitar la pasa a
 * Inactivas sin perder el registro.
 */
export function useRouteActivation() {
    const { confirm } = useConfirm();

    const deactivate = async (item: { id: number; name: string }) => {
        const confirmed = await confirm({
            title: '¿Inhabilitar la ruta?',
            text: `${item.name} pasará a Inactivas y no se ofrecerá en las órdenes nuevas. Podrás volver a habilitarla luego.`,
            confirmText: 'Sí, inhabilitar',
            destructive: true,
        });

        if (confirmed) {
            router.post(route('routes.deactivate', { route: item.id }), {}, { preserveScroll: true });
        }
    };

    const activate = async (item: { id: number; name: string }) => {
        const confirmed = await confirm({
            title: '¿Habilitar la ruta?',
            text: `${item.name} vuelve a estar activa.`,
            confirmText: 'Sí, habilitar',
        });

        if (confirmed) {
            router.post(route('routes.activate', { route: item.id }), {}, { preserveScroll: true });
        }
    };

    return { deactivate, activate };
}
