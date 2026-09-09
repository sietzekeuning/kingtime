import { router } from '@inertiajs/vue3';
import timeEntries from '@/routes/time-entries';

export type TimesheetView = 'day' | 'week' | 'month' | 'all';

const viewStorageKey = 'kingtime.timesheet.view';

/**
 * Moves the timesheet to another date (or back to today with `null`). Only
 * the timesheet props reload; the table filters stay in the URL, so the
 * "All entries" tab is unaffected.
 */
export function visitTimesheetDate(date: string | null): void {
    const params = new URLSearchParams(window.location.search);

    if (date) {
        params.set('date', date);
    } else {
        params.delete('date');
    }

    const query = params.toString();

    router.visit(`${timeEntries.index.url()}${query ? `?${query}` : ''}`, {
        only: ['timesheet', 'month'],
        preserveState: true,
        preserveScroll: true,
    });
}

/**
 * The view to open with: the `view` in the URL wins, then a shared table
 * URL opens the table, then the view used last time, and otherwise the week.
 */
export function initialTimesheetView(): TimesheetView {
    if (typeof window === 'undefined') {
        return 'week';
    }

    const fromUrl = new URLSearchParams(window.location.search).get('view');

    if (isTimesheetView(fromUrl)) {
        return fromUrl;
    }

    if (/[?&](filter\[|sort=|page=)/.test(window.location.search)) {
        return 'all';
    }

    try {
        const remembered = window.localStorage.getItem(viewStorageKey);

        if (isTimesheetView(remembered)) {
            return remembered;
        }
    } catch {
        // Storage can be unavailable; the default view is fine then.
    }

    return 'week';
}

/** Keeps the chosen view in the URL (for sharing) and in storage (for next time). */
export function rememberTimesheetView(view: TimesheetView): void {
    try {
        window.localStorage.setItem(viewStorageKey, view);
    } catch {
        // Ignore storage that is unavailable.
    }

    const url = new URL(window.location.href);
    url.searchParams.set('view', view);
    window.history.replaceState(window.history.state, '', url.toString());
}

function isTimesheetView(value: string | null): value is TimesheetView {
    return (
        value === 'day' ||
        value === 'week' ||
        value === 'month' ||
        value === 'all'
    );
}
