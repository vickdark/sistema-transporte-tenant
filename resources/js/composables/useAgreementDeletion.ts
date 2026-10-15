import { useConfirm } from '@/composables/useConfirm';
import { router } from '@inertiajs/vue3';

/**
 * Eliminar un convenio (como en el legacy); sus vehículos quedan sin convenio.
 */
export function useAgreementDeletion() {
    const { confirm } = useConfirm();

    const remove = async (agreement: { id: number; business_name: string }) => {
        const confirmed = await confirm({
            title: '¿Eliminar el convenio?',
            text: `Se elimina el convenio con ${agreement.business_name} y sus vehículos quedan sin convenio. No se puede deshacer.`,
            confirmText: 'Sí, eliminar',
            destructive: true,
        });

        if (confirmed) {
            router.delete(route('agreements.destroy', { agreement: agreement.id }));
        }
    };

    return { remove };
}
