import { useConfirm } from '@/composables/useConfirm';
import { router } from '@inertiajs/vue3';

/**
 * Inhabilitar y habilitar un vehículo, con la misma confirmación que el legacy.
 */
export function useVehicleActivation() {
    const { confirm } = useConfirm();

    const deactivate = async (vehicle: { id: number; plate: string }) => {
        const confirmed = await confirm({
            title: '¿Inhabilitar vehículo?',
            text: `El vehículo ${vehicle.plate} pasará a Inactivos. Podrás volver a habilitarlo luego.`,
            confirmText: 'Sí, inhabilitar',
            destructive: true,
        });

        if (confirmed) {
            router.post(route('vehicles.deactivate', { vehicle: vehicle.id }), {}, { preserveScroll: true });
        }
    };

    const activate = async (vehicle: { id: number; plate: string }) => {
        const confirmed = await confirm({
            title: '¿Habilitar vehículo?',
            text: `El vehículo ${vehicle.plate} vuelve al listado de vehículos activos.`,
            confirmText: 'Sí, habilitar',
        });

        if (confirmed) {
            router.post(route('vehicles.activate', { vehicle: vehicle.id }), {}, { preserveScroll: true });
        }
    };

    return { deactivate, activate };
}
