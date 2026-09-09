import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

export interface ConfirmDeleteOptions {
    /** Dialog heading, e.g. "Delete project?". */
    title: string;
    /** Optional muted body text below the title. */
    description?: string;
    /** Pass the click event to stop propagation on DataTable row buttons. */
    event?: Event;
    onSuccess?: () => void;
}

/**
 * The standard destructive delete flow: stop the row-click from propagating,
 * ask for confirmation with the red "Delete" variant, then issue the
 * DELETE with preserved scroll. Replaces the identical block copied into
 * every list page.
 */
export function useConfirmDelete() {
    const { confirm } = useConfirm();

    async function confirmDelete(
        url: string,
        options: ConfirmDeleteOptions,
    ): Promise<void> {
        options.event?.stopPropagation();

        const confirmed = await confirm({
            title: options.title,
            description: options.description,
            confirmLabel: 'Delete',
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        router.delete(url, {
            preserveScroll: true,
            onSuccess: options.onSuccess,
        });
    }

    return { confirmDelete };
}
