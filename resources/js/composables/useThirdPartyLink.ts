import { useConfirm } from '@/composables/useConfirm';
import { router } from '@inertiajs/vue3';

/** Textos de los módulos gemelos Aliados / Proveedores (los envía el controlador). */
export interface ThirdPartyKind {
    singular: string;
    plural: string;
    nit_label: string;
    twin_singular: string;
    twin_plural: string;
}

export interface ThirdPartyLinkUrls {
    convert: string;
    revert: string;
}

/**
 * Convertir en mixto y revertir, con la misma confirmación que el legacy.
 */
export function useThirdPartyLink(kind: ThirdPartyKind) {
    const { confirm } = useConfirm();

    const convert = async (name: string, urls: ThirdPartyLinkUrls) => {
        const confirmed = await confirm({
            title: `¿Convertir en ${kind.twin_singular}?`,
            text: `${name} quedará registrado también como ${kind.twin_singular} y será mixto. Si ya existe un ${kind.twin_singular} con el mismo NIT, se vincula y se actualiza con estos datos.`,
            confirmText: 'Sí, convertir',
        });

        if (confirmed) {
            router.post(urls.convert, {}, { preserveScroll: true });
        }
    };

    const revert = async (name: string, urls: ThirdPartyLinkUrls) => {
        const confirmed = await confirm({
            title: '¿Revertir la conversión?',
            text: `${name} dejará de ser mixto y volverá a ser un ${kind.singular} sencillo. Su registro como ${kind.twin_singular} seguirá existiendo por separado.`,
            confirmText: 'Sí, revertir',
            destructive: true,
        });

        if (confirmed) {
            router.delete(urls.revert, { preserveScroll: true });
        }
    };

    return { convert, revert };
}
