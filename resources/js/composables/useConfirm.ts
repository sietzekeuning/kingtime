import { reactive } from 'vue';

export type ConfirmVariant = 'default' | 'destructive';

export interface ConfirmOptions {
    /** Bold heading. When `confirm()` is called with a plain string, that string becomes the title. */
    title?: string;
    /** Muted body text below the title. */
    description?: string;
    /** Label on the confirm button. */
    confirmLabel?: string;
    /** Label on the cancel button. */
    cancelLabel?: string;
    /** `destructive` paints the confirm button red, for deletes and other irreversible actions. */
    variant?: ConfirmVariant;
}

interface ConfirmState extends Required<ConfirmOptions> {
    open: boolean;
    resolve: ((value: boolean) => void) | null;
}

/**
 * Module-level singleton state shared between every `useConfirm()` call site and
 * the single `<ConfirmDialog />` mounted in the persistent layouts. Replaces the
 * blocking native `window.confirm()` with an async, on-brand shadcn AlertDialog.
 */
const state = reactive<ConfirmState>({
    open: false,
    title: 'Are you sure?',
    description: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    variant: 'default',
    resolve: null,
});

export const confirmState = state;

/**
 * Resolve the open confirm dialog with the user's choice. Called by
 * `<ConfirmDialog />` when the user picks confirm/cancel or dismisses the dialog.
 */
export function resolveConfirm(value: boolean): void {
    state.open = false;

    if (state.resolve) {
        state.resolve(value);
        state.resolve = null;
    }
}

export function useConfirm() {
    /**
     * Ask the user to confirm an action. Returns a promise that resolves to
     * `true` when confirmed and `false` when cancelled or dismissed.
     *
     * Pass a plain string for a quick yes/no question (it becomes the title),
     * or an options object to set a description, button labels, or the
     * destructive (red) variant.
     */
    function confirm(options: ConfirmOptions | string = {}): Promise<boolean> {
        const opts: ConfirmOptions =
            typeof options === 'string' ? { title: options } : options;

        return new Promise<boolean>((resolve) => {
            // A second confirm() while one is still open: dismiss the previous
            // one as cancelled so its awaiter doesn't hang forever.
            if (state.resolve) {
                state.resolve(false);
            }

            state.title = opts.title ?? 'Are you sure?';
            state.description = opts.description ?? '';
            state.confirmLabel = opts.confirmLabel ?? 'Confirm';
            state.cancelLabel = opts.cancelLabel ?? 'Cancel';
            state.variant = opts.variant ?? 'default';
            state.resolve = resolve;
            state.open = true;
        });
    }

    return { confirm };
}
