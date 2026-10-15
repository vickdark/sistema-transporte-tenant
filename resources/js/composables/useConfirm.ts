import Swal, { type SweetAlertOptions } from 'sweetalert2';

interface ConfirmOptions {
    title: string;
    text?: string;
    confirmText?: string;
    /** Acción destructiva o difícil de revertir: botón rojo e ícono de advertencia. */
    destructive?: boolean;
}

interface TypedConfirmOptions extends ConfirmOptions {
    /** Texto exacto que hay que escribir para habilitar la confirmación (p. ej. el subdominio). */
    expected: string;
}

const DESTRUCTIVE_COLOR = '#dc2626';
const PRIMARY_COLOR = '#171717';

/** Opciones comunes: tema según el modo claro/oscuro de la app y textos en español. */
const baseOptions = (options: ConfirmOptions): SweetAlertOptions => ({
    theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
    icon: options.destructive ? 'warning' : 'question',
    title: options.title,
    text: options.text,
    showCancelButton: true,
    confirmButtonText: options.confirmText ?? 'Confirmar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: options.destructive ? DESTRUCTIVE_COLOR : PRIMARY_COLOR,
    reverseButtons: true,
    focusCancel: options.destructive ?? false,
});

/**
 * Confirmaciones de la interfaz con SweetAlert2. Toda acción que pida "¿estás seguro?" pasa por aquí.
 */
export function useConfirm() {
    const confirm = async (options: ConfirmOptions): Promise<boolean> => {
        const result = await Swal.fire(baseOptions(options));

        return result.isConfirmed;
    };

    /** Confirmación fuerte: solo se acepta escribiendo exactamente `expected`. */
    const confirmByTyping = async (options: TypedConfirmOptions): Promise<boolean> => {
        const result = await Swal.fire({
            ...baseOptions({ ...options, destructive: true }),
            input: 'text',
            inputLabel: `Escribe «${options.expected}» para confirmar`,
            inputPlaceholder: options.expected,
            inputAttributes: { autocomplete: 'off', autocapitalize: 'off', spellcheck: 'false' },
            inputValidator: (value) => (value === options.expected ? null : `Escribe «${options.expected}» exactamente.`),
        });

        return result.isConfirmed;
    };

    return { confirm, confirmByTyping };
}
